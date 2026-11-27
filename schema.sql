PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    organization TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('client', 'analyst', 'admin'))
);

CREATE TABLE IF NOT EXISTS auth_sessions (
    token_hash TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_by_user ON auth_sessions(user_id);

CREATE TABLE IF NOT EXISTS password_resets (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT UNIQUE NOT NULL,
    expires INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS cases (
    id TEXT PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES users(id),
    analyst_id INTEGER REFERENCES users(id),
    title TEXT NOT NULL,
    plan TEXT NOT NULL CHECK (plan IN ('basic', 'insight')),
    service TEXT NOT NULL,
    purpose TEXT NOT NULL,
    context TEXT NOT NULL,
    consent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL DEFAULT 'submitted' CHECK (status IN
        ('submitted', 'assigned', 'in_review', 'revisions', 'published', 'rejected')),
    decision_note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS cases_by_client ON cases(client_id, created_at);
CREATE INDEX IF NOT EXISTS cases_by_analyst ON cases(analyst_id, created_at);

CREATE TABLE IF NOT EXISTS evidence (
    id TEXT PRIMARY KEY,
    case_id TEXT NOT NULL REFERENCES cases(id),
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    accessed TEXT NOT NULL,
    notes TEXT NOT NULL,
    attachment BLOB,
    attachment_type TEXT
);
CREATE INDEX IF NOT EXISTS evidence_by_case ON evidence(case_id);

CREATE TABLE IF NOT EXISTS reports (
    case_id TEXT PRIMARY KEY REFERENCES cases(id),
    summary TEXT NOT NULL,
    findings TEXT NOT NULL,
    limitations TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS case_events (
    id INTEGER PRIMARY KEY,
    case_id TEXT NOT NULL REFERENCES cases(id),
    actor_id INTEGER NOT NULL REFERENCES users(id),
    event TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS events_by_case ON case_events(case_id, id);
