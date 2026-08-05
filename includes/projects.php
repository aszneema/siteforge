<?php
declare(strict_types=1);

/**
 * Projects, versions and the on-disk files that belong to each version.
 * Every generation (and every edit) creates a new immutable version, so a user
 * can always look back at — or restore — an earlier state of their site.
 */

function project_dir(int $projectId): string
{
    return config('storage_path') . '/projects/' . $projectId;
}

function version_dir(int $projectId, int $versionNo): string
{
    return project_dir($projectId) . '/v' . $versionNo;
}

function project_create(int $userId, string $name, string $brief, string $siteType): int
{
    $name = trim($name) !== '' ? trim($name) : 'Untitled site';
    if (!array_key_exists($siteType, site_types())) {
        $siteType = 'html';
    }

    $stmt = db()->prepare(
        'INSERT INTO projects (user_id, name, slug, site_type, brief, status, progress, stage)
         VALUES (?, ?, ?, ?, ?, ?, 0, ?)'
    );
    $stmt->execute([$userId, $name, slugify($name), $siteType, $brief, 'queued', 'Queued']);

    return (int) db()->lastInsertId();
}

function project_get(int $id, ?int $userId = null): ?array
{
    $sql = 'SELECT * FROM projects WHERE id = ?';
    $args = [$id];
    if ($userId !== null) {
        $sql .= ' AND user_id = ?';
        $args[] = $userId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetch() ?: null;
}

function project_list(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM projects WHERE user_id = ? ORDER BY updated_at DESC, id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function project_progress(int $id, int $percent, string $stage, ?string $status = null): void
{
    $percent = max(0, min(100, $percent));
    if ($status !== null) {
        $stmt = db()->prepare(
            "UPDATE projects SET progress = ?, stage = ?, status = ?, updated_at = datetime('now') WHERE id = ?"
        );
        $stmt->execute([$percent, $stage, $status, $id]);
    } else {
        $stmt = db()->prepare(
            "UPDATE projects SET progress = ?, stage = ?, updated_at = datetime('now') WHERE id = ?"
        );
        $stmt->execute([$percent, $stage, $id]);
    }
}

function project_fail(int $id, string $message): void
{
    $stmt = db()->prepare(
        "UPDATE projects SET status = 'failed', stage = 'Failed', error = ?, updated_at = datetime('now') WHERE id = ?"
    );
    $stmt->execute([mb_substr($message, 0, 900), $id]);
}

function project_rename(int $id, int $userId, string $name): bool
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 80) {
        return false;
    }
    $stmt = db()->prepare(
        "UPDATE projects SET name = ?, slug = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$name, slugify($name), $id, $userId]);
    return $stmt->rowCount() > 0;
}

function project_delete(int $id, int $userId): bool
{
    $stmt = db()->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    rrmdir(project_dir($id));
    return true;
}

// --- Versions ---------------------------------------------------------------

function project_versions(int $projectId): array
{
    $stmt = db()->prepare('SELECT * FROM versions WHERE project_id = ? ORDER BY version_no DESC');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function version_get(int $projectId, int $versionNo): ?array
{
    $stmt = db()->prepare('SELECT * FROM versions WHERE project_id = ? AND version_no = ?');
    $stmt->execute([$projectId, $versionNo]);
    return $stmt->fetch() ?: null;
}

function version_files(int $versionId): array
{
    $stmt = db()->prepare('SELECT path, size FROM files WHERE version_id = ? ORDER BY path');
    $stmt->execute([$versionId]);
    return $stmt->fetchAll();
}

/**
 * Persist a generated file set as the project's next version.
 *
 * @param array<string,string> $files  relative path => file contents
 */
function version_store(
    int $projectId,
    array $files,
    string $prompt,
    string $summary,
    string $engine,
    ?array $spec = null
): int {
    if ($files === []) {
        throw new RuntimeException('The generator returned no files.');
    }

    $next = (int) db()->query(
        'SELECT COALESCE(MAX(version_no), 0) + 1 FROM versions WHERE project_id = ' . $projectId
    )->fetchColumn();

    $dir = version_dir($projectId, $next);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the project folder on disk.');
    }

    $written = [];
    foreach ($files as $path => $content) {
        $clean = version_clean_path((string) $path);
        if ($clean === null) {
            continue;
        }
        $target = $dir . '/' . $clean;
        $parent = dirname($target);
        if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
            continue;
        }
        if (file_put_contents($target, (string) $content) === false) {
            throw new RuntimeException('Could not write ' . $clean . ' to disk.');
        }
        $written[$clean] = strlen((string) $content);
    }

    if ($written === []) {
        throw new RuntimeException('None of the generated files had a usable filename.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO versions (project_id, version_no, prompt, summary, engine, spec, file_count)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $projectId,
            $next,
            mb_substr($prompt, 0, 4000),
            mb_substr($summary, 0, 2000),
            $engine,
            $spec !== null ? json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            count($written),
        ]);
        $versionId = (int) $pdo->lastInsertId();

        $insertFile = $pdo->prepare('INSERT INTO files (version_id, path, size) VALUES (?, ?, ?)');
        foreach ($written as $path => $size) {
            $insertFile->execute([$versionId, $path, $size]);
        }

        $pdo->prepare(
            "UPDATE projects SET current_version = ?, status = 'ready', progress = 100,
                    stage = 'Ready', error = NULL, engine = ?, updated_at = datetime('now')
             WHERE id = ?"
        )->execute([$next, $engine, $projectId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $next;
}

/** Normalise a generator-supplied path; null when it is unusable or unsafe. */
function version_clean_path(string $path): ?string
{
    $path = trim(str_replace('\\', '/', $path));
    $path = ltrim($path, './');

    if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
        return null;
    }
    if (preg_match('#^[a-zA-Z]:#', $path) || strlen($path) > 180) {
        return null;
    }

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        $segment = preg_replace('/[^A-Za-z0-9._-]/', '-', $segment) ?? '';
        $segment = trim($segment, '-');
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
        $segments[] = $segment;
    }
    if (count($segments) > 6) {
        return null;
    }

    $clean = implode('/', $segments);
    $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
    $allowed = ['html', 'htm', 'php', 'css', 'js', 'json', 'svg', 'txt', 'md',
                'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'webmanifest', 'htaccess'];

    return in_array($ext, $allowed, true) ? $clean : null;
}

/** @return array<string,string> relative path => contents */
function version_read_files(int $projectId, int $versionNo): array
{
    $dir = version_dir($projectId, $versionNo);
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
        $files[$relative] = (string) file_get_contents($file->getPathname());
    }
    ksort($files);

    return $files;
}

/** The page a preview should open first. */
function version_entry_file(int $projectId, int $versionNo, string $siteType): string
{
    $candidates = $siteType === 'php'
        ? ['index.php', 'index.html']
        : ['index.html', 'index.php', 'index.htm'];

    foreach ($candidates as $candidate) {
        if (is_file(version_dir($projectId, $versionNo) . '/' . $candidate)) {
            return $candidate;
        }
    }

    foreach (array_keys(version_read_files($projectId, $versionNo)) as $path) {
        if (preg_match('/\.(html?|php)$/i', $path)) {
            return $path;
        }
    }

    return 'index.html';
}

/** Copy an old version forward so it becomes the newest one. */
function version_restore(int $projectId, int $versionNo, string $engine): int
{
    $files = version_read_files($projectId, $versionNo);
    if ($files === []) {
        throw new RuntimeException('That version has no files on disk any more.');
    }
    $old = version_get($projectId, $versionNo);

    return version_store(
        $projectId,
        $files,
        'Restore version ' . $versionNo,
        'Restored the site to version ' . $versionNo . '.',
        $engine,
        $old && $old['spec'] ? json_decode((string) $old['spec'], true) : null
    );
}

// --- Chat log ---------------------------------------------------------------

function message_add(int $projectId, string $role, string $body): void
{
    $stmt = db()->prepare('INSERT INTO messages (project_id, role, body) VALUES (?, ?, ?)');
    $stmt->execute([$projectId, $role, mb_substr($body, 0, 6000)]);
}

function messages_for(int $projectId): array
{
    $stmt = db()->prepare('SELECT * FROM messages WHERE project_id = ? ORDER BY id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

// --- Uploaded images --------------------------------------------------------

function assets_for(int $userId, ?int $projectId = null): array
{
    if ($projectId !== null) {
        $stmt = db()->prepare(
            'SELECT * FROM assets WHERE user_id = ? AND (project_id = ? OR project_id IS NULL) ORDER BY id DESC'
        );
        $stmt->execute([$userId, $projectId]);
    } else {
        $stmt = db()->prepare('SELECT * FROM assets WHERE user_id = ? ORDER BY id DESC LIMIT 60');
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll();
}
