<?php
declare(strict_types=1);

/** Shared PDO connection. Creates and migrates the SQLite file on first call. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $file = config('storage_path') . '/webmaker.sqlite';
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    db_migrate($pdo);

    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        name          TEXT NOT NULL,
        email         TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS projects (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name            TEXT NOT NULL,
        slug            TEXT NOT NULL,
        site_type       TEXT NOT NULL DEFAULT 'html',
        brief           TEXT NOT NULL DEFAULT '',
        status          TEXT NOT NULL DEFAULT 'queued',
        progress        INTEGER NOT NULL DEFAULT 0,
        stage           TEXT NOT NULL DEFAULT 'Queued',
        engine          TEXT NOT NULL DEFAULT '',
        error           TEXT,
        current_version INTEGER NOT NULL DEFAULT 0,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS versions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        version_no  INTEGER NOT NULL,
        prompt      TEXT NOT NULL DEFAULT '',
        summary     TEXT NOT NULL DEFAULT '',
        engine      TEXT NOT NULL DEFAULT '',
        spec        TEXT,
        file_count  INTEGER NOT NULL DEFAULT 0,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS files (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        version_id INTEGER NOT NULL REFERENCES versions(id) ON DELETE CASCADE,
        path       TEXT NOT NULL,
        size       INTEGER NOT NULL DEFAULT 0
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS messages (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        role       TEXT NOT NULL,
        body       TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS assets (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
        filename   TEXT NOT NULL,
        stored     TEXT NOT NULL,
        mime       TEXT NOT NULL DEFAULT '',
        size       INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    // --- Builder: sites you assemble by clicking, not by generating ---------
    // A site has pages; a page either holds plain content or lists a collection.
    // A collection ("Games") has fields ("Title", "Rating") and entries.

    // ALTER TABLE has no IF NOT EXISTS in SQLite, so check before adding.
    $columns = $pdo->query('PRAGMA table_info(projects)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('kind', $columns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN kind TEXT NOT NULL DEFAULT 'site'");
    }

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS collections (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id    INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        name          TEXT NOT NULL,
        singular      TEXT NOT NULL DEFAULT '',
        slug          TEXT NOT NULL,
        sort_field    TEXT NOT NULL DEFAULT '',
        sort_dir      TEXT NOT NULL DEFAULT 'desc',
        position      INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS fields (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
        label         TEXT NOT NULL,
        key           TEXT NOT NULL,
        type          TEXT NOT NULL DEFAULT 'text',
        options       TEXT NOT NULL DEFAULT '',
        required      INTEGER NOT NULL DEFAULT 0,
        position      INTEGER NOT NULL DEFAULT 0
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS entries (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
        data          TEXT NOT NULL DEFAULT '{}',
        created_at    TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS pages (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id    INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        title         TEXT NOT NULL,
        slug          TEXT NOT NULL,
        intro         TEXT NOT NULL DEFAULT '',
        body          TEXT NOT NULL DEFAULT '',
        collection_id INTEGER REFERENCES collections(id) ON DELETE SET NULL,
        is_home       INTEGER NOT NULL DEFAULT 0,
        position      INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_collections_project ON collections(project_id, position)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fields_collection ON fields(collection_id, position)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_entries_collection ON entries(collection_id, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_project ON pages(project_id, position)');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id, updated_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_versions_project ON versions(project_id, version_no DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_project ON messages(project_id, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_files_version ON files(version_id)');
}
