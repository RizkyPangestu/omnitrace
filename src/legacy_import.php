<?php
declare(strict_types=1);

function read_legacy_table(PDO $legacy, string $table, string $columns, string $order): PDOStatement
{
    try {
        // The table, columns, and sort key come only from the migration map below.
        return $legacy->query("SELECT $columns FROM $table ORDER BY $order");
    } catch (PDOException $error) {
        throw new RuntimeException("Could not read the legacy OmniTrace table $table.", previous: $error);
    }
}

function import_legacy_database(string $filename): int
{
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('Enable PDO SQLite for this one-time import.');
    }
    if (!is_file($filename) || !is_readable($filename)) {
        throw new RuntimeException('Choose a readable legacy database file.');
    }
    try {
        $legacy = new PDO('sqlite:' . realpath($filename), options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ]);
    } catch (PDOException $error) {
        throw new RuntimeException('Could not open the legacy SQLite database.', previous: $error);
    }

    $tables = [
        'users' => ['id, email, name, organization, password_hash, role', 'id'],
        'cases' => ['id, client_id, analyst_id, title, plan, service, purpose, context, consent_at, status, decision_note, created_at', 'rowid'],
        'evidence' => ['id, case_id, title, url, accessed, notes, attachment, attachment_type', 'rowid'],
        'reports' => ['case_id, summary, findings, limitations, updated_at', 'case_id'],
        'case_events' => ['id, case_id, actor_id, `event`, created_at', 'id'],
    ];

    $legacy->beginTransaction();
    try {
        // This next-key lock also protects the empty range against concurrent inserts.
        database()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        return transaction(function () use ($legacy, $tables): int {
            if (row('SELECT id FROM users ORDER BY id LIMIT 1 FOR UPDATE') !== null) {
                throw new RuntimeException('Import requires an empty MySQL database. No data was changed.');
            }
            $copied = 0;
            foreach ($tables as $table => [$columns, $order]) {
                $result = read_legacy_table($legacy, $table, $columns, $order);
                $placeholders = implode(', ', array_fill(0, $result->columnCount(), '?'));
                $insert = database()->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
                while (($record = $result->fetch()) !== false) {
                    $index = 1;
                    foreach ($record as $key => $value) {
                        $type = match (true) {
                            $value === null => PDO::PARAM_NULL,
                            $key === 'attachment' => PDO::PARAM_LOB,
                            is_int($value) => PDO::PARAM_INT,
                            default => PDO::PARAM_STR,
                        };
                        $insert->bindValue($index++, $value, $type);
                    }
                    $insert->execute();
                    $copied++;
                }
                $result->closeCursor();
            }
            return $copied;
        });
    } finally {
        $legacy->rollBack();
    }
}
