<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only.', 405);
}
csrf_guard($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    json_error('No file was uploaded.');
}

$file = $_FILES['file'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_error(match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is larger than the server allows.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        default => 'The upload failed. Please try again.',
    });
}

$maxBytes = 5 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    json_error('Images must be 5 MB or smaller.');
}
if (!is_uploaded_file($file['tmp_name'])) {
    json_error('That upload could not be verified.');
}

// Trust the file's actual contents, not the name or the browser-supplied type.
// SVG is deliberately excluded: it can carry script, and these files end up
// embedded in generated pages and previews.
$allowed = [
    IMAGETYPE_PNG  => ['png',  'image/png'],
    IMAGETYPE_JPEG => ['jpg',  'image/jpeg'],
    IMAGETYPE_GIF  => ['gif',  'image/gif'],
    IMAGETYPE_WEBP => ['webp', 'image/webp'],
];

$info = @getimagesize($file['tmp_name']);
if ($info === false || !isset($allowed[$info[2]])) {
    json_error('That file is not a PNG, JPEG, GIF or WebP image.');
}
[$ext, $mime] = $allowed[$info[2]];

$originalName = (string) ($file['name'] ?? 'image');
$baseName = slugify(pathinfo($originalName, PATHINFO_FILENAME), 'image');
$stored = bin2hex(random_bytes(16)) . '.' . $ext;
$target = config('storage_path') . '/uploads/' . $stored;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    json_error('Could not save that image on the server.', 500);
}
@chmod($target, 0644);

$projectId = isset($_POST['project_id']) && (int) $_POST['project_id'] > 0
    ? (int) $_POST['project_id']
    : null;

$stmt = db()->prepare(
    'INSERT INTO assets (user_id, project_id, filename, stored, mime, size) VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    (int) $user['id'],
    $projectId,
    $baseName . '.' . $ext,
    $stored,
    $mime,
    (int) filesize($target),
]);

$id = (int) db()->lastInsertId();

json_out([
    'ok'   => true,
    'id'   => $id,
    'name' => $baseName . '.' . $ext,
    'size' => human_size((int) filesize($target)),
    'url'  => 'api/asset.php?id=' . $id,
]);
