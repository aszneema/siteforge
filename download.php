<?php
declare(strict_types=1);

/** Packages a version of a generated site as a ZIP the user can download. */

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();

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

if (!class_exists(ZipArchive::class)) {
    http_response_code(500);
    exit('This server does not have PHP\'s zip extension enabled, so downloads are unavailable.');
}

$files = version_read_files($projectId, $version);
if ($files === []) {
    http_response_code(404);
    exit('That version has no files on disk.');
}

session_write_close();

$slug = slugify((string) $project['name'], 'website');
$zipName = $slug . '-v' . $version . '.zip';
$tmp = tempnam(sys_get_temp_dir(), 'wm-zip-');
if ($tmp === false) {
    http_response_code(500);
    exit('Could not create a temporary file for the download.');
}

$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit('Could not build the ZIP archive.');
}

foreach ($files as $path => $contents) {
    $zip->addFromString($slug . '/' . $path, $contents);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmp));
header('X-Content-Type-Options: nosniff');

readfile($tmp);
@unlink($tmp);
