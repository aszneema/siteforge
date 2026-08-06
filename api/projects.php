<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login_api();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only.', 405);
}

$body = json_input();
csrf_guard($body['csrf'] ?? null);

$action = (string) ($body['action'] ?? '');

switch ($action) {

    // An app you build by clicking — pages and collections, stored in the
    // database. Nothing to generate, so it's ready immediately.
    case 'start_app':
        require_once SF_ROOT . '/includes/builder.php';

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            json_error('Give your app a name.');
        }
        if (mb_strlen($name) > 80) {
            $name = mb_substr($name, 0, 80);
        }

        $id = project_create($userId, $name, (string) ($body['brief'] ?? ''), 'html');
        db()->prepare("UPDATE projects SET kind = 'app', status = 'ready', progress = 100, stage = 'Ready' WHERE id = ?")
            ->execute([$id]);
        app_scaffold($id, $name);

        json_out(['ok' => true, 'project_id' => $id, 'name' => $name, 'url' => 'build.php?id=' . $id]);

    // Create the project row up front so the browser has an id to poll while
    // the (much slower) generation request runs.
    case 'start':
        $name = trim((string) ($body['name'] ?? ''));
        $brief = trim((string) ($body['brief'] ?? ''));
        $siteType = (string) ($body['site_type'] ?? 'html');

        if ($brief === '') {
            json_error('Describe the website you want before building.');
        }
        if (mb_strlen($brief) > 4000) {
            json_error('That description is too long — please keep it under 4000 characters.');
        }
        if ($name === '') {
            $name = derive_project_name($brief);
        }
        if (mb_strlen($name) > 80) {
            $name = mb_substr($name, 0, 80);
        }

        $id = project_create($userId, $name, $brief, $siteType);
        json_out(['ok' => true, 'project_id' => $id, 'name' => $name]);

    case 'rename':
        $id = (int) ($body['project_id'] ?? 0);
        $name = trim((string) ($body['name'] ?? ''));
        if (!project_rename($id, $userId, $name)) {
            json_error('Could not rename that project. Check the name is between 1 and 80 characters.');
        }
        json_out(['ok' => true, 'name' => $name]);

    case 'delete':
        $id = (int) ($body['project_id'] ?? 0);
        if (!project_delete($id, $userId)) {
            json_error('That project does not exist.', 404);
        }
        json_out(['ok' => true]);

    case 'restore':
        $id = (int) ($body['project_id'] ?? 0);
        $versionNo = (int) ($body['version'] ?? 0);
        $project = project_get($id, $userId);
        if (!$project) {
            json_error('That project does not exist.', 404);
        }
        if (!version_get($id, $versionNo)) {
            json_error('That version does not exist.', 404);
        }
        try {
            $new = version_restore($id, $versionNo, (string) $project['engine']);
            message_add($id, 'assistant', 'Restored the site to version ' . $versionNo . '.');
        } catch (Throwable $e) {
            json_error($e->getMessage(), 500);
        }
        json_out(['ok' => true, 'version' => $new]);

    default:
        json_error('Unknown action.');
}

/** Make a sensible project name out of the first line of the brief. */
function derive_project_name(string $brief): string
{
    if (preg_match('/(?:called|named)\s+["\x{201C}]?([^"\x{201D}.\n]{2,50})["\x{201D}]?/iu', $brief, $m)) {
        return trim($m[1]);
    }

    $first = trim(strtok($brief, ".\n") ?: $brief);
    $first = preg_replace('/^\s*(please\s+)?(build|make|create|design|i\s+(?:want|need))\b\s*/i', '', $first) ?? $first;
    $first = preg_replace('/^\s*(me\s+)?(a|an|the)\s+/i', '', $first) ?? $first;
    $first = trim($first);

    if ($first === '') {
        return 'Untitled site';
    }

    $words = preg_split('/\s+/', $first) ?: [];
    $name = implode(' ', array_slice($words, 0, 6));

    return ucfirst(mb_substr($name, 0, 60));
}
