<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/database.php';
require PROJECT_DIR . '/src/security.php';
require PROJECT_DIR . '/src/accounts.php';

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
    if (!in_array('mysql', PDO::getAvailableDrivers(), true) || !defined('PASSWORD_ARGON2ID')) {
        throw new RuntimeException('Enable PDO MySQL and Argon2id before running OmniTrace.');
    }
    $command = $argv[1] ?? '';
    if ($command === 'init-db' && $argc === 2) {
        initialize_database();
        echo "Database initialized.\n";
    } elseif ($command === 'import-sqlite' && $argc === 3) {
        require PROJECT_DIR . '/src/legacy_import.php';
        printf("Imported %d records. Users can sign in again.\n", import_legacy_database($argv[2]));
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
        if (!create_account($email, $name, $hash, $role)) {
            throw new RuntimeException('That email address is already registered.');
        }
        echo "Staff account created.\n";
    } elseif ($command === 'issue-reset' && $argc === 3) {
        $email = email_address($argv[2]);
        $code = transaction(function () use ($email): string {
            $account = row('SELECT id FROM users WHERE email = ? FOR UPDATE', [$email]);
            if ($account === null) {
                throw new RuntimeException('Account not found.');
            }
            $code = random_token();
            $digest = token_hash($code);
            $expires = time() + 900;
            sql(
                'INSERT INTO password_resets (user_id, token_hash, expires) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE token_hash = ?, expires = ?',
                [$account['id'], $digest, $expires, $digest, $expires],
            );
            return $code;
        });
        echo $code . PHP_EOL;
    } else {
        throw new RuntimeException(
            "Usage\nphp bin/omnitrace.php init-db\nphp bin/omnitrace.php import-sqlite FILE\nphp bin/omnitrace.php create-staff admin|analyst EMAIL NAME [--password-stdin]\nphp bin/omnitrace.php issue-reset EMAIL",
        );
    }
} catch (PDOException $error) {
    fwrite(STDERR, "Database operation failed. Check MySQL connection settings, schema, and permissions.\n");
    exit(1);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
