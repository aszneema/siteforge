<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['wm_config'][$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Send a JSON response and stop. */
function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): never
{
    json_out(['ok' => false, 'error' => $message], $status);
}

/** Read a JSON request body into an array. */
function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Verify CSRF for a form POST or a fetch() call, aborting on failure. */
function csrf_guard(?string $token = null): void
{
    $token ??= $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_check($token)) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            json_error('Your session expired. Reload the page and try again.', 419);
        }
        http_response_code(419);
        exit('Session expired — please go back and reload the page.');
    }
}

function slugify(string $text, string $fallback = 'site'): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? substr($slug, 0, 60) : $fallback;
}

function human_time(string $timestamp): string
{
    $then = strtotime($timestamp);
    if ($then === false) {
        return $timestamp;
    }
    $diff = time() - $then;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h ago';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd ago';
    return date('j M Y', $then);
}

function human_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

/** The site types a user can generate. */
function site_types(): array
{
    return [
        'html' => [
            'label' => 'Static HTML',
            'hint'  => 'Multi-page HTML, CSS and vanilla JS. Runs anywhere.',
            'icon'  => '&lt;/&gt;',
            'ext'   => 'html',
        ],
        'php' => [
            'label' => 'PHP',
            'hint'  => 'PHP pages with shared header/footer includes.',
            'icon'  => 'php',
            'ext'   => 'php',
        ],
        'landing' => [
            'label' => 'Landing page',
            'hint'  => 'One long single-page site, everything on one screen.',
            'icon'  => '&#9776;',
            'ext'   => 'html',
        ],
        'bootstrap' => [
            'label' => 'Bootstrap 5',
            'hint'  => 'HTML built on the Bootstrap 5 grid and components.',
            'icon'  => 'B',
            'ext'   => 'html',
        ],
    ];
}

function site_type_label(string $type): string
{
    return site_types()[$type]['label'] ?? ucfirst($type);
}

/** Human name for the engine recorded on a version. */
function engine_label(string $engine): string
{
    return match ($engine) {
        'claude' => 'Claude',
        'ollama' => 'Local AI',
        'local'  => 'Template builder',
        default  => 'Unknown',
    };
}

/** Guess a content type from a file path, for preview.php. */
function mime_for(string $path): string
{
    return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'html', 'htm' => 'text/html; charset=utf-8',
        'css'         => 'text/css; charset=utf-8',
        'js', 'mjs'   => 'text/javascript; charset=utf-8',
        'json'        => 'application/json; charset=utf-8',
        'svg'         => 'image/svg+xml',
        'png'         => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'ico'         => 'image/x-icon',
        'txt', 'md'   => 'text/plain; charset=utf-8',
        default       => 'application/octet-stream',
    };
}

/**
 * Resolve a user-supplied relative path inside a base directory.
 * Returns null when the path escapes the base (traversal, symlink, absolute).
 */
function safe_path(string $base, string $relative): ?string
{
    $base = realpath($base);
    if ($base === false) {
        return null;
    }
    $relative = str_replace('\\', '/', $relative);
    $relative = ltrim($relative, '/');
    $candidate = realpath($base . '/' . $relative);
    if ($candidate === false) {
        return null;
    }
    if ($candidate !== $base && !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $candidate;
}

/** Recursively delete a directory. */
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}
