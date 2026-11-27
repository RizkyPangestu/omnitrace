<?php
declare(strict_types=1);

const SESSION_AGE = 8 * 60 * 60;
const MAX_REQUEST = 3 * 1024 * 1024;
const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$RjVVZkZNd3hTTHRDWUU0WQ$8W/MjBH5G7xN6QR8nfEcIOtGAHsj7JLr7SFyWVI6BLM';

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function reject(int $status, string $message): never
{
    throw new HttpError($status, $message);
}

function escape(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function random_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function token_hash(string $value): string
{
    return hash('sha256', $value);
}

function input(array $values, string $key): string
{
    $value = $values[$key] ?? '';
    return is_string($value) ? $value : '';
}

function text_length(string $value): int|false
{
    return preg_match_all('/./us', $value);
}

function field(string $key, string $label, int $max, int $min = 1): string
{
    $value = trim(input($_POST, $key), " \t\n\r\v\f");
    $length = text_length($value);
    if ($length === false || $length < $min || $length > $max || str_contains($value, "\0")) {
        reject(422, "$label must contain {$min}–{$max} characters.");
    }
    return $value;
}

function email_address(string $value): string
{
    $address = strtolower(trim($value));
    if (strlen($address) > 254 || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
        reject(422, 'Enter a valid email address.');
    }
    return $address;
}

function hash_password(string $password): string
{
    $length = text_length($password);
    if ($length === false || $length < 12 || $length > 128 || str_contains($password, "\0")) {
        reject(422, 'Use a password of 12–128 characters.');
    }
    return password_hash($password, PASSWORD_ARGON2ID);
}

function password_matches(?array $account, string $password): bool
{
    if (strlen($password) > 512 || str_contains($password, "\0")) {
        return false;
    }
    $hash = $account['password_hash'] ?? '';
    $isSupported = str_starts_with($hash, '$argon2id$');
    // A fixed dummy hash keeps rejected logins on the same verification path.
    $hashToCheck = $isSupported ? $hash : DUMMY_PASSWORD_HASH;
    return password_verify($password, $hashToCheck) && $isSupported;
}

function validate_source_url(string $value): string
{
    $parsed = parse_url($value);
    if ($parsed === false || !in_array($parsed['scheme'] ?? '', ['http', 'https'], true)
        || empty($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])
        || ($parsed['port'] ?? 1) === 0 || str_contains($value, '\\')
        || preg_match('/[\x00-\x20\x7F]/', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        reject(422, 'Use an HTTP or HTTPS source URL without credentials.');
    }
    return $value;
}

function secure_request(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    return in_array($https, ['on', '1'], true);
}

function local_mode(): bool
{
    return getenv('OMNITRACE_LOCAL') === '1';
}

function security_headers(): void
{
    header_remove("X-Powered-By");
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; script-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    if (!local_mode() && secure_request()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function validate_transport(): void
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/D', '', $host);
    $allowed = array_map('trim', explode(',', strtolower(environment('OMNITRACE_HOSTS', 'localhost,127.0.0.1,[::1]'))));
    if ($host === '' || !in_array($host, $allowed, true)) {
        reject(400, 'Request host is unavailable.');
    }
    if (local_mode()) {
        if (!in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
            || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
            reject(400, 'Local mode is restricted to loopback.');
        }
    } elseif (!secure_request()) {
        reject(400, 'Use HTTPS to open OmniTrace.');
    }
}

function open_session(): void
{
    $directory = data_directory() . '/sessions';
    private_directory($directory);
    session_save_path($directory);
    session_name(local_mode() ? 'omni_php_session' : '__Host-omni_php_session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) SESSION_AGE);
    session_cache_limiter('');
    session_set_cookie_params([
        'lifetime' => SESSION_AGE,
        'path' => '/',
        'domain' => '',
        'secure' => !local_mode(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Could not start the session.');
    }
}

function csrf_input(): void
{
    $_SESSION['csrf'] ??= random_token();
    echo '<input type="hidden" name="_csrf" value="' . escape($_SESSION['csrf']) . '">';
}

function verify_csrf(): void
{
    $expected = $_SESSION['csrf'] ?? '';
    if ($expected === '' || !hash_equals($expected, input($_POST, '_csrf'))) {
        reject(400, 'The form expired. Reload the page and try again.');
    }
}

function throttle(string $action, int $max, int $window, ?string $subject = null): void
{
    $now = time();
    $key = token_hash($action . ':' . ($subject ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
    transaction(function () use ($key, $max, $window, $now): void {
        sql('DELETE FROM rate_limits WHERE expires <= ?', [$now]);
        $current = row('SELECT attempts FROM rate_limits WHERE bucket = ?', [$key]);
        if ($current !== null && (int) $current['attempts'] >= $max) {
            reject(429, 'Too many attempts. Try again later.');
        }
        sql(
            'INSERT INTO rate_limits (bucket, attempts, expires) VALUES (?, 1, ?)
             ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1',
            [$key, $now + $window],
        );
    });
}

function load_user(): ?array
{
    if (!isset($_SESSION['sid'])) {
        return null;
    }
    $account = row(
        'SELECT users.* FROM users JOIN auth_sessions ON users.id = auth_sessions.user_id
         WHERE auth_sessions.token_hash = ? AND auth_sessions.expires > ?',
        [token_hash($_SESSION['sid']), time()],
    );
    if ($account === null) {
        $_SESSION = [];
    }
    return $account;
}

function start_authentication(array $user): void
{
    $token = random_token();
    $now = time();
    transaction(function () use ($user, $token, $now): void {
        $current = row('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if ($current === null || $current['password_hash'] !== $user['password_hash']) {
            reject(409, 'The account changed. Sign in again.');
        }
        sql('DELETE FROM auth_sessions WHERE expires <= ?', [$now]);
        end_authentication();
        sql('INSERT INTO auth_sessions VALUES (?, ?, ?)', [token_hash($token), $user['id'], $now + SESSION_AGE]);
    });
    rotate_session();
    $_SESSION['sid'] = $token;
}

function end_authentication(): void
{
    if (isset($_SESSION['sid'])) {
        sql('DELETE FROM auth_sessions WHERE token_hash = ?', [token_hash($_SESSION['sid'])]);
    }
}

function rotate_session(): void
{
    $_SESSION = [];
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not rotate the session.');
    }
}

function require_user(?array $user, string ...$roles): array
{
    if ($user === null) {
        redirect('/login');
    }
    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        reject(403, 'This action is unavailable for your role.');
    }
    return $user;
}
