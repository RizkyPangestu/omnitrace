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

function private_directory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create the session directory.');
    }
}

function database(): PDO
{
    static $connection;
    if ($connection === null) {
        $host = environment('OMNITRACE_DB_HOST', '127.0.0.1');
        $port = filter_var(environment('OMNITRACE_DB_PORT', '3306'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $name = environment('OMNITRACE_DB_NAME', 'omnitrace');
        $username = environment('OMNITRACE_DB_USER', 'omnitrace');
        $password = getenv('OMNITRACE_DB_PASSWORD');
        if ($port === false || !preg_match('/^[a-zA-Z0-9.-]+$/D', $host)
            || !preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $name)) {
            throw new RuntimeException('Check the MySQL host, port, and database name.');
        }
        if ($password === false || $password === '') {
            throw new RuntimeException('Set OMNITRACE_DB_PASSWORD for the MySQL application account.');
        }
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ];
        $tlsCa = getenv('OMNITRACE_DB_SSL_CA');
        if ($tlsCa !== false && $tlsCa !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $tlsCa;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $username, $password, $options);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $connection = $pdo;
    }
    return $connection;
}

function initialize_database(): void
{
    $schema = file_get_contents(PROJECT_DIR . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Could not read schema.sql.');
    }
    $connection = database();
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') {
            $connection->exec($statement);
        }
    }
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
    $connection->beginTransaction();
    try {
        $result = $operation();
        $connection->commit();
        return $result;
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}
