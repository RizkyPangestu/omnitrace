CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    organization VARCHAR(160) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    role VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CHECK (role IN ('client', 'analyst', 'admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS auth_sessions (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    expires BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY sessions_by_user (user_id),
    KEY sessions_by_expiry (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS password_resets (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    expires BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    expires BIGINT UNSIGNED NOT NULL,
    KEY rate_limits_by_expiry (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS cases (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    analyst_id BIGINT UNSIGNED,
    title VARCHAR(140) NOT NULL,
    plan VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    service VARCHAR(80) NOT NULL,
    purpose TEXT NOT NULL,
    context TEXT NOT NULL,
    consent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'submitted',
    decision_note VARCHAR(1200) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (plan IN ('basic', 'insight')),
    CHECK (status IN ('submitted', 'assigned', 'in_review', 'revisions', 'published', 'rejected')),
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (analyst_id) REFERENCES users(id),
    KEY cases_by_client (client_id, created_at),
    KEY cases_by_analyst (analyst_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS evidence (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    entry_number BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
    case_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(140) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    accessed CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    notes TEXT NOT NULL,
    attachment MEDIUMBLOB,
    attachment_type VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    KEY evidence_by_case (case_id, entry_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS reports (
    case_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    summary TEXT NOT NULL,
    findings TEXT NOT NULL,
    limitations TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS case_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    case_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    `event` VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (actor_id) REFERENCES users(id),
    KEY events_by_case (case_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
