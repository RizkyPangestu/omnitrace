<?php
declare(strict_types=1);

require __DIR__ . '/../src/database.php';
require PROJECT_DIR . '/src/security.php';
require PROJECT_DIR . '/src/render.php';
require PROJECT_DIR . '/src/accounts.php';
require PROJECT_DIR . '/src/cases.php';

umask(0077);
ini_set('display_errors', '0');

function allow_methods(string $method, string ...$allowed): void
{
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        reject(405, 'This method is unavailable for this page.');
    }
}

function dispatch(string $path, string $method, ?array $user): void
{
    $post = $method === 'POST';
    $publicPages = ['/' => 'home', '/index.php' => 'home', '/services' => 'services', '/sample-report' => 'sample_report'];
    if (isset($publicPages[$path])) {
        allow_methods($method, 'GET', 'HEAD');
        render($publicPages[$path], ['user' => $user]);
        return;
    }
    switch ($path) {
        case '/login':
            allow_methods($method, 'GET', 'HEAD', 'POST');
            login($user, $post);
            return;
        case '/register':
            allow_methods($method, 'GET', 'HEAD', 'POST');
            register($user, $post);
            return;
        case '/reset-password':
            allow_methods($method, 'GET', 'HEAD', 'POST');
            reset_password($post);
            return;
        case '/logout':
            allow_methods($method, 'POST');
            logout($user);
        case '/profile':
            allow_methods($method, 'GET', 'HEAD', 'POST');
            profile($user, $post);
            return;
        case '/profile/password':
            allow_methods($method, 'POST');
            change_password($user);
        case '/workspace':
            allow_methods($method, 'GET', 'HEAD');
            dashboard($user);
            return;
        case '/cases/new':
            allow_methods($method, 'GET', 'HEAD', 'POST');
            new_case($user, $post);
            return;
    }
    if (!preg_match('#^/cases/([a-f0-9]{32})(/.*)?$#D', $path, $matches)) {
        reject(404, 'Page not found.');
    }

    [, $caseId] = $matches;
    $action = $matches[2] ?? '';
    switch ($action) {
        case '':
            allow_methods($method, 'GET', 'HEAD');
            case_detail($caseId, $user);
            return;
        case '/decision':
            allow_methods($method, 'POST');
            decide_case($caseId, $user);
        case '/evidence':
            allow_methods($method, 'POST');
            add_evidence($caseId, $user);
        case '/report':
            allow_methods($method, 'POST');
            save_report($caseId, $user);
        case '/report/download':
            allow_methods($method, 'GET', 'HEAD');
            download_report($caseId, $user);
    }
    if (preg_match('#^/evidence/([a-f0-9]{32})/attachment$#D', $action, $evidence)) {
        allow_methods($method, 'GET', 'HEAD');
        download_evidence($caseId, $evidence[1], $user);
    }
    reject(404, 'Page not found.');
}

security_headers();
$user = null;
try {
    validate_transport();
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_REQUEST) {
        reject(413, 'The request exceeds 3 MB.');
    }
    open_session();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        verify_csrf();
    }
    $user = load_user();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    dispatch(is_string($path) ? $path : '/', $method, $user);
} catch (HttpError $error) {
    http_response_code($error->status);
    render('error', ['code' => $error->status, 'message' => $error->getMessage(), 'user' => $user]);
} catch (Throwable $error) {
    error_log('OmniTrace: ' . $error->getMessage());
    http_response_code(500);
    render('error', ['code' => 500, 'message' => 'An internal error occurred.', 'user' => $user]);
}
