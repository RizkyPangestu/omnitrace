<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/database.php';
require PROJECT_DIR . '/src/security.php';

umask(0077);

function read_password(bool $fromPipe): string
{
    if ($fromPipe) {
        if (stream_isatty(STDIN)) {
            throw new RuntimeException('Pipe the password from a secure prompt.');
        }
        $password = fgets(STDIN, 1024);
        if ($password === false) {
            throw new RuntimeException('No password was received.');
        }
        return rtrim($password, "\r\n");
    }
    if (PHP_OS_FAMILY === 'Windows' || !stream_isatty(STDIN) || !function_exists('exec')) {
        throw new RuntimeException('Use --password-stdin with a secure prompt. See README.md.');
    }

    $terminalOutput = [];
    exec('stty -g 2>/dev/null', $terminalOutput, $status);
    if ($status !== 0 || !isset($terminalOutput[0])) {
        throw new RuntimeException('Could not open a hidden password prompt.');
    }
    $restore = 'stty ' . escapeshellarg($terminalOutput[0]) . ' 2>/dev/null';
    register_shutdown_function(static function () use ($restore): void { exec($restore); });
    exec('stty -echo 2>/dev/null', result_code: $status);
    if ($status !== 0) {
        throw new RuntimeException('Could not disable password echo.');
    }
    try {
        fwrite(STDERR, 'Password (12–128 characters): ');
        $password = rtrim((string) fgets(STDIN, 1024), "\r\n");
        fwrite(STDERR, "\nConfirm password: ");
        $confirmation = rtrim((string) fgets(STDIN, 1024), "\r\n");
        fwrite(STDERR, "\n");
    } finally {
        exec($restore);
    }
    if ($password !== $confirmation) {
        throw new RuntimeException('The passwords must match.');
    }
    return $password;
}

try {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true) || !defined('PASSWORD_ARGON2ID')) {
        throw new RuntimeException('Enable PDO SQLite and Argon2id before running OmniTrace.');
    }
    $command = $argv[1] ?? '';
    if ($command === 'init-db' && $argc === 2) {
        initialize_database();
        echo "Database initialized.\n";
    } elseif ($command === 'create-staff' && ($argc === 5 || ($argc === 6 && $argv[5] === '--password-stdin'))) {
        [, , $role, $rawEmail, $rawName] = $argv;
        if (!in_array($role, ['admin', 'analyst'], true)) {
            throw new RuntimeException('Use the admin or analyst role.');
        }
        $name = trim($rawName);
        $length = text_length($name);
        if ($length === false || $length < 1 || $length > 100) {
            throw new RuntimeException('Name must contain 1–100 characters.');
        }
        $email = email_address($rawEmail);
        $hash = hash_password(read_password($argc === 6));
        if (sql(
            'INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, ?)
             ON CONFLICT(email) DO NOTHING',
            [$email, $name, $hash, $role],
        )->rowCount() !== 1) {
            throw new RuntimeException('That email address is already registered.');
        }
        echo "Staff account created.\n";
    } elseif ($command === 'issue-reset' && $argc === 3) {
        $account = row('SELECT id FROM users WHERE email = ?', [email_address($argv[2])]);
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }
        $code = random_token();
        sql(
            'INSERT INTO password_resets VALUES (?, ?, ?) ON CONFLICT(user_id) DO UPDATE
             SET token_hash = excluded.token_hash, expires = excluded.expires',
            [$account['id'], token_hash($code), time() + 900],
        );
        echo $code . PHP_EOL;
    } else {
        throw new RuntimeException(
            "Usage\nphp bin/omnitrace.php init-db\nphp bin/omnitrace.php create-staff admin|analyst EMAIL NAME [--password-stdin]\nphp bin/omnitrace.php issue-reset EMAIL",
        );
    }
} catch (PDOException $error) {
    fwrite(STDERR, "Database operation failed. Check the path and permissions.\n");
    exit(1);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
