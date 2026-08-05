<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login_api();
session_write_close();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = db()->prepare('SELECT * FROM assets WHERE id = ? AND user_id = ?');
$stmt->execute([$id, (int) $user['id']]);
$asset = $stmt->fetch();

if (!$asset) {
    http_response_code(404);
    exit('Not found');
}

$path = config('storage_path') . '/uploads/' . basename((string) $asset['stored']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: ' . ($asset['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode((string) $asset['filename']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

readfile($path);
