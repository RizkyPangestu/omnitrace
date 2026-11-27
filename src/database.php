<?php
declare(strict_types=1);

const PROJECT_DIR = __DIR__ . '/..';

function environment(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function data_directory(): string
{
    return environment('OMNITRACE_DATA_DIR', PROJECT_DIR . '/var');
}

function database_path(): string
{
    return environment('OMNITRACE_DB', data_directory() . '/omni.sqlite3');
}

function private_directory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create the data directory.');
    }
}

function database(): PDO
{
    static $connection;
    if ($connection === null) {
        $path = database_path();
        if (!is_file($path)) {
            throw new RuntimeException('Initialize the database with bin/omnitrace.php.');
        }
        $connection = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('PRAGMA busy_timeout = 10000');
    }
    return $connection;
}

function initialize_database(): void
{
    $path = database_path();
    private_directory(dirname($path));
    if (!is_file($path)) {
        $file = @fopen($path, 'x');
        if ($file !== false) {
            fclose($file);
        } elseif (!is_file($path)) {
            throw new RuntimeException('Could not create the database.');
        }
    }
    database()->exec('PRAGMA journal_mode = WAL');
    database()->exec(file_get_contents(PROJECT_DIR . '/schema.sql'));
}

function sql(string $query, array $values = []): PDOStatement
{
    $statement = database()->prepare($query);
    $statement->execute($values);
    return $statement;
}

function row(string $query, array $values = []): ?array
{
    $result = sql($query, $values)->fetch();
    return $result === false ? null : $result;
}

function transaction(callable $operation): mixed
{
    $connection = database();
    $connection->exec('BEGIN IMMEDIATE');
    try {
        $result = $operation();
        $connection->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $connection->exec('ROLLBACK');
        throw $error;
    }
}
