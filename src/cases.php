<?php
declare(strict_types=1);

const PLANS = ['basic' => 'Basic · Rp420.000', 'insight' => 'Insight · Rp760.000'];
const SERVICES = ['Corporate due diligence', 'Litigation support', 'Digital footprint verification'];
const STATUS_LABELS = [
    'submitted' => 'Under review',
    'assigned' => 'In progress',
    'in_review' => 'Report under review',
    'revisions' => 'In progress',
    'published' => 'Published',
    'rejected' => 'Rejected',
];
const ROLE_TITLES = ['client' => 'Investigation workspace', 'analyst' => 'Analyst desk', 'admin' => 'Investigation control'];
const FILE_TYPES = [
    'pdf' => ['%PDF-', 'application/pdf'],
    'png' => ["\x89PNG\x0D\x0A\x1A\x0A", 'image/png'],
    'jpg' => ["\xFF\xD8\xFF", 'image/jpeg'],
];
const MAX_ATTACHMENT = 2 * 1024 * 1024;

function case_for(string $caseId, array $user, bool $lock = false): array
{
    $query = $lock
        ? 'SELECT cases.* FROM cases WHERE cases.id = ?'
        : 'SELECT cases.*, owner.name AS client_name, analyst.name AS analyst_name
           FROM cases JOIN users AS owner ON owner.id = cases.client_id
           LEFT JOIN users AS analyst ON analyst.id = cases.analyst_id WHERE cases.id = ?';
    $parameters = [$caseId];
    if ($user['role'] === 'client') {
        $query .= ' AND cases.client_id = ?';
        $parameters[] = $user['id'];
    } elseif ($user['role'] === 'analyst') {
        $query .= ' AND cases.analyst_id = ?';
        $parameters[] = $user['id'];
    }
    $case = row($query . ($lock ? ' FOR UPDATE' : ''), $parameters);
    if ($case === null) {
        reject(404, 'Case not found.');
    }
    return $case;
}

function case_history(string $caseId, int $actorId, string $event): void
{
    sql('INSERT INTO case_events (case_id, actor_id, `event`) VALUES (?, ?, ?)', [$caseId, $actorId, $event]);
}

function dashboard(?array $user): void
{
    $user = require_user($user);
    $query = 'SELECT id, title, status, service, created_at FROM cases';
    $params = [];
    if ($user['role'] === 'client') {
        $query .= ' WHERE client_id = ?';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'analyst') {
        $query .= ' WHERE analyst_id = ?';
        $params[] = $user['id'];
    }
    $cases = sql($query . ' ORDER BY created_at DESC, id', $params)->fetchAll();
    $published = 0;
    $open = 0;
    foreach ($cases as $case) {
        $published += (int) ($case['status'] === 'published');
        $open += (int) (!in_array($case['status'], ['published', 'rejected'], true));
    }
    render('dashboard', compact('user', 'cases', 'published', 'open'));
}

function new_case(?array $user, bool $post): void
{
    $plan = input($_GET, 'plan');
    if ($user === null) {
        redirect('/login' . (isset(PLANS[$plan]) ? '?plan=' . $plan : ''));
    }
    $user = require_user($user, 'client');
    if ($post) {
        $plan = field('plan', 'Plan', 20);
        $service = field('service', 'Service', 80);
        if (!isset(PLANS[$plan]) || !in_array($service, SERVICES, true)) {
            reject(422, 'Choose an available plan and service.');
        }
        if (input($_POST, 'consent') !== 'yes') {
            reject(422, 'Confirm that you are authorized to submit this information.');
        }
        $title = field('title', 'Request title', 140);
        $purpose = field('purpose', 'Purpose', 4000);
        $context = field('context', 'Initial information', 8000, 0);
        $caseId = bin2hex(random_bytes(16));
        transaction(function () use ($caseId, $user, $title, $plan, $service, $purpose, $context): void {
            sql(
                'INSERT INTO cases (id, client_id, title, plan, service, purpose, context)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$caseId, $user['id'], $title, $plan, $service, $purpose, $context],
            );
            case_history($caseId, (int) $user['id'], 'Investigation brief received');
        });
        flash('Investigation request submitted.');
        redirect('/cases/' . $caseId);
    }
    render('new_case', compact('user', 'plan'));
}

function case_detail(string $caseId, ?array $user): void
{
    $user = require_user($user);
    $case = case_for($caseId, $user);
    $staff = $user['role'] !== 'client';
    $report = $staff || $case['status'] === 'published'
        ? row('SELECT * FROM reports WHERE case_id = ?', [$caseId])
        : null;
    $evidence = $staff
        ? sql('SELECT id, title, url, accessed, notes, attachment_type FROM evidence
               WHERE case_id = ? ORDER BY entry_number DESC', [$caseId])->fetchAll()
        : [];
    $events = sql(
        'SELECT case_events.`event`, case_events.created_at, users.role
         FROM case_events JOIN users ON users.id = case_events.actor_id
         WHERE case_id = ? ORDER BY case_events.id DESC',
        [$caseId],
    )->fetchAll();
    $analysts = $user['role'] === 'admin' && $case['status'] === 'submitted'
        ? sql("SELECT id, name, email FROM users WHERE role = 'analyst' ORDER BY name, email")->fetchAll()
        : [];
    render('case', compact('user', 'case', 'report', 'evidence', 'events', 'analysts'));
}

function decide_case(string $caseId, ?array $user): never
{
    $user = require_user($user, 'admin');
    $decision = input($_POST, 'decision');
    transaction(function () use ($caseId, $user, $decision): void {
        $case = case_for($caseId, $user, lock: true);
        $status = $case['status'];
        if ($decision === 'assign' && $status === 'submitted') {
            $analyst = row("SELECT id FROM users WHERE id = ? AND role = 'analyst'", [field('analyst_id', 'Analyst', 20)]);
            if ($analyst === null) {
                reject(422, 'Choose a registered analyst.');
            }
            sql("UPDATE cases SET status = 'assigned', analyst_id = ? WHERE id = ?", [$analyst['id'], $caseId]);
            $event = 'Analyst assigned';
        } elseif ($decision === 'reject' && $status === 'submitted') {
            $reason = field('reason', 'Rejection reason', 1200);
            sql("UPDATE cases SET status = 'rejected', decision_note = ? WHERE id = ?", [$reason, $caseId]);
            $event = 'Request rejected';
        } elseif ($decision === 'publish' && $status === 'in_review'
            && row('SELECT case_id FROM reports WHERE case_id = ?', [$caseId]) !== null) {
            sql("UPDATE cases SET status = 'published', decision_note = '' WHERE id = ?", [$caseId]);
            $event = 'Final report published';
        } elseif ($decision === 'revise' && $status === 'in_review') {
            $reason = field('reason', 'Revision notes', 1200);
            sql("UPDATE cases SET status = 'revisions', decision_note = ? WHERE id = ?", [$reason, $caseId]);
            $event = 'Report returned for revision';
        } else {
            reject(409, 'The decision is unavailable for this case status.');
        }
        case_history($caseId, (int) $user['id'], $event);
    });
    flash('Decision saved.');
    redirect('/cases/' . $caseId);
}

function uploaded_attachment(): array
{
    $file = $_FILES['attachment'] ?? null;
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || (is_int($file['size'] ?? null) && $file['size'] > MAX_ATTACHMENT)) {
        reject(422, 'Attachment must be 2 MB or smaller.');
    }
    $path = $file['tmp_name'] ?? '';
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        reject(422, 'Could not read the uploaded attachment.');
    }
    $bytes = file_get_contents($path, length: MAX_ATTACHMENT + 1);
    if ($bytes === false) {
        reject(422, 'Could not read the uploaded attachment.');
    }
    if (strlen($bytes) > MAX_ATTACHMENT) {
        reject(422, 'Attachment must be 2 MB or smaller.');
    }
    foreach (FILE_TYPES as $kind => [$signature]) {
        if (str_starts_with($bytes, $signature)) {
            return [$bytes, $kind];
        }
    }
    reject(422, 'Attach a PDF, PNG, or JPEG file.');
}

function add_evidence(string $caseId, ?array $user): never
{
    $user = require_user($user, 'analyst');
    case_for($caseId, $user);
    $title = field('title', 'Evidence title', 140);
    $url = validate_source_url(field('url', 'Source URL', 2048));
    $accessed = field('accessed', 'Source access time', 16);
    $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $accessed, new DateTimeZone('UTC'));
    if ($time === false || $time->format('Y-m-d\TH:i') !== $accessed || str_starts_with($accessed, '0000')) {
        reject(422, 'Enter a valid source access date and time in UTC.');
    }
    $notes = field('notes', 'Findings and context', 6000);
    [$attachment, $kind] = uploaded_attachment();
    transaction(function () use ($caseId, $user, $title, $url, $accessed, $notes, $attachment, $kind): void {
        $case = case_for($caseId, $user, lock: true);
        if (!in_array($case['status'], ['assigned', 'revisions'], true)) {
            reject(409, 'Evidence can be added while a case is in progress.');
        }
        $statement = database()->prepare(
            'INSERT INTO evidence (id, case_id, title, url, accessed, notes, attachment, attachment_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ([bin2hex(random_bytes(16)), $caseId, $title, $url, $accessed, $notes] as $index => $value) {
            $statement->bindValue($index + 1, $value);
        }
        $statement->bindValue(7, $attachment, $attachment === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $statement->bindValue(8, $kind, $kind === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();
        case_history($caseId, (int) $user['id'], 'Source evidence recorded');
    });
    flash('Evidence note saved.');
    redirect('/cases/' . $caseId . '#evidence');
}

function download_file(string $content, string $type, string $name): never
{
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($content));
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo $content;
    }
    exit;
}

function download_evidence(string $caseId, string $evidenceId, ?array $user): never
{
    $user = require_user($user, 'analyst', 'admin');
    case_for($caseId, $user);
    $evidence = row(
        'SELECT attachment, attachment_type FROM evidence WHERE id = ? AND case_id = ?',
        [$evidenceId, $caseId],
    );
    $kind = $evidence['attachment_type'] ?? '';
    if ($evidence === null || $evidence['attachment'] === null || !isset(FILE_TYPES[$kind])) {
        reject(404, 'Attachment not found.');
    }
    download_file($evidence['attachment'], FILE_TYPES[$kind][1], "evidence-{$evidenceId}.{$kind}");
}

function save_report(string $caseId, ?array $user): never
{
    $user = require_user($user, 'analyst');
    case_for($caseId, $user);
    $mode = input($_POST, 'mode');
    if (!in_array($mode, ['draft', 'submit'], true)) {
        reject(422, 'Choose to save the draft or submit it for review.');
    }
    $summary = field('summary', 'Executive summary', 6000);
    $findings = field('findings', 'Findings', 12000);
    $limitations = field('limitations', 'Limitations', 6000);
    transaction(function () use ($caseId, $user, $mode, $summary, $findings, $limitations): void {
        $case = case_for($caseId, $user, lock: true);
        if (!in_array($case['status'], ['assigned', 'revisions'], true)) {
            reject(409, 'The report is locked at this case status.');
        }
        sql(
            'INSERT INTO reports (case_id, summary, findings, limitations) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE summary = ?, findings = ?, limitations = ?, updated_at = CURRENT_TIMESTAMP',
            [$caseId, $summary, $findings, $limitations, $summary, $findings, $limitations],
        );
        if ($mode === 'submit') {
            sql("UPDATE cases SET status = 'in_review', decision_note = '' WHERE id = ?", [$caseId]);
            case_history($caseId, (int) $user['id'], 'Report submitted for review');
        }
    });
    flash($mode === 'submit' ? 'Report submitted for review.' : 'Report draft saved.');
    redirect('/cases/' . $caseId . '#report');
}

function download_report(string $caseId, ?array $user): never
{
    $user = require_user($user);
    $case = case_for($caseId, $user);
    $report = $case['status'] === 'published' ? row('SELECT * FROM reports WHERE case_id = ?', [$caseId]) : null;
    if ($report === null) {
        reject(404, 'Published report not found.');
    }
    $body = "OmniTrace | {$case['title']}\nCase {$caseId}\n\nExecutive summary\n{$report['summary']}\n\n"
        . "Findings\n{$report['findings']}\n\nLimitations\n{$report['limitations']}\n";
    download_file($body, 'text/plain; charset=UTF-8', "omnitrace-{$caseId}.txt");
}
