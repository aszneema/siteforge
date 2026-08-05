<?php
declare(strict_types=1);

/**
 * Serves the files of a generated site so it can be previewed in an iframe.
 *
 * Relative links inside previewed HTML are rewritten to point back at this
 * script, so navigating between the generated pages works inside the preview.
 *
 * PHP projects are executed (see 'preview_execute_php' in config.php) — this
 * is the user's own generated code, running only for the project's owner.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();
session_write_close();

$projectId = isset($_GET['p']) ? (int) $_GET['p'] : 0;
$project = project_get($projectId, (int) $user['id']);
if (!$project) {
    http_response_code(404);
    exit('Project not found.');
}

$version = isset($_GET['v']) ? (int) $_GET['v'] : (int) $project['current_version'];
if ($version < 1 || !version_get($projectId, $version)) {
    http_response_code(404);
    exit('That version has not been built yet.');
}

$dir = version_dir($projectId, $version);
$requested = (string) ($_GET['f'] ?? '');
if ($requested === '') {
    $requested = version_entry_file($projectId, $version, (string) $project['site_type']);
}

$file = safe_path($dir, $requested);
if ($file === null || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p style="font:16px system-ui;padding:2rem">Not found in this version: <code>'
        . e($requested) . '</code></p>');
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$relative = ltrim(str_replace('\\', '/', substr($file, strlen(realpath($dir) ?: $dir))), '/');

// Never let a preview be framed by another site, and keep the browser from
// sniffing a served file into something executable.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

// --- PHP pages -------------------------------------------------------------
if ($extension === 'php') {
    if (!config('preview_execute_php', true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "// PHP execution is disabled in config.php — showing source instead.\n\n";
        echo file_get_contents($file);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo preview_rewrite(render_php_page($file), $projectId, $version, $relative);
    exit;
}

// --- Static files ----------------------------------------------------------
$mime = mime_for($file);
header('Content-Type: ' . $mime);

if (str_starts_with($mime, 'text/html')) {
    echo preview_rewrite((string) file_get_contents($file), $projectId, $version, $relative);
    exit;
}

header('Content-Length: ' . filesize($file));
readfile($file);
exit;

// ---------------------------------------------------------------------------

/**
 * Execute a generated PHP page in its own directory and capture the output.
 * Wrapped in a function so the page cannot see this script's variables.
 */
function render_php_page(string $file): string
{
    $cwd = getcwd();
    @chdir(dirname($file));

    ob_start();
    try {
        (static function (string $__wm_file): void {
            require $__wm_file;
        })($file);
    } catch (Throwable $e) {
        ob_end_clean();
        if ($cwd !== false) {
            @chdir($cwd);
        }
        return '<!DOCTYPE html><meta charset="utf-8"><body style="font:15px/1.6 system-ui;padding:2rem;color:#b91c1c">'
             . '<h2 style="margin:0 0 .5rem">This generated page threw an error</h2><pre style="white-space:pre-wrap">'
             . e($e->getMessage()) . '</pre><p style="color:#475569">Fix it with an edit, or download the project and '
             . 'debug it locally.</p></body>';
    }
    $html = (string) ob_get_clean();

    if ($cwd !== false) {
        @chdir($cwd);
    }

    return $html;
}

/**
 * Point relative links and asset URLs back at this previewer.
 */
function preview_rewrite(string $html, int $projectId, int $version, string $currentPath): string
{
    $baseDir = dirname($currentPath);
    if ($baseDir === '.' || $baseDir === '/' || $baseDir === '\\') {
        $baseDir = '';
    }

    $rewrite = static function (array $m) use ($projectId, $version, $baseDir): string {
        [$all, $attr, $quote, $url] = $m;
        $trimmed = trim($url);

        // Leave absolute URLs, anchors and non-navigational schemes alone.
        if ($trimmed === ''
            || str_starts_with($trimmed, '#')
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $trimmed)
            || str_starts_with($trimmed, '//')
        ) {
            return $all;
        }

        // Split off any anchor / query so it survives the rewrite.
        $suffix = '';
        if (($hash = strpos($trimmed, '#')) !== false) {
            $suffix = substr($trimmed, $hash);
            $trimmed = substr($trimmed, 0, $hash);
        }
        if ($trimmed === '') {
            return $all;
        }

        $path = str_starts_with($trimmed, '/')
            ? ltrim($trimmed, '/')
            : ($baseDir !== '' ? $baseDir . '/' . $trimmed : $trimmed);

        // Normalise "." and ".." segments.
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $clean = implode('/', $segments);
        if ($clean === '') {
            return $all;
        }

        $target = 'preview.php?p=' . $projectId . '&v=' . $version . '&f=' . rawurlencode($clean) . $suffix;

        return $attr . '=' . $quote . $target . $quote;
    };

    return preg_replace_callback(
        '/\b(href|src)\s*=\s*(["\'])(.*?)\2/i',
        $rewrite,
        $html
    ) ?? $html;
}
