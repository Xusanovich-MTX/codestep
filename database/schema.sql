CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL COLLATE NOCASE UNIQUE CHECK (username COLLATE BINARY = lower(username) AND length(username) > 0),
    password_hash TEXT NOT NULL CHECK (length(password_hash) > 0),
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE TABLE IF NOT EXISTS auth_throttle (
    bucket TEXT PRIMARY KEY,
    failures INTEGER NOT NULL CHECK (failures >= 0),
    expires_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS solution_drafts (
    owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    topic_id INTEGER NOT NULL CHECK (topic_id > 0),
    problem_number TEXT NOT NULL,
    language TEXT NOT NULL CHECK (language IN ('cpp', 'python')),
    code TEXT NOT NULL CHECK (length(CAST(code AS BLOB)) <= 65536),
    revision INTEGER NOT NULL CHECK (revision > 0),
    updated_at TEXT NOT NULL,
    PRIMARY KEY (owner_id, topic_id, problem_number, language)
);

CREATE TABLE IF NOT EXISTS solution_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    topic_id INTEGER NOT NULL CHECK (topic_id > 0),
    problem_number TEXT NOT NULL,
    language TEXT NOT NULL CHECK (language IN ('cpp', 'python')),
    code TEXT NOT NULL CHECK (length(CAST(code AS BLOB)) <= 65536),
    code_hash TEXT NOT NULL CHECK (length(code_hash) = 64),
    output TEXT NOT NULL CHECK (length(CAST(output AS BLOB)) <= 65536),
    verdict TEXT NOT NULL CHECK (verdict IN ('accepted', 'wrong_answer', 'structure_passed', 'error')),
    checks_passed INTEGER NOT NULL CHECK (checks_passed >= 0),
    total_checks INTEGER NOT NULL CHECK (total_checks >= 0 AND checks_passed <= total_checks),
    test_stdin TEXT NOT NULL CHECK (length(CAST(test_stdin AS BLOB)) <= 16384),
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS solution_runs_owner_id ON solution_runs(owner_id, id DESC);
CREATE INDEX IF NOT EXISTS solution_drafts_owner_updated ON solution_drafts(owner_id, updated_at DESC);

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    display_name TEXT NOT NULL CHECK (length(display_name) BETWEEN 1 AND 80),
    bio TEXT NOT NULL DEFAULT '' CHECK (length(bio) <= 280),
    avatar BLOB CHECK (avatar IS NULL OR length(avatar) <= 2097152),
    avatar_mime TEXT CHECK (avatar_mime IS NULL OR avatar_mime IN ('image/jpeg', 'image/png', 'image/webp')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
    CHECK ((avatar IS NULL) = (avatar_mime IS NULL))
);

CREATE TRIGGER IF NOT EXISTS users_create_profile AFTER INSERT ON users
BEGIN
    INSERT INTO user_profiles (user_id, display_name) VALUES (NEW.id, NEW.username);
END;

-- Upgrade existing accounts without replacing a profile already edited by its owner.
INSERT OR IGNORE INTO user_profiles (user_id, display_name, updated_at)
SELECT id, username, created_at FROM users;

PRAGMA user_version = 2;
