<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once SF_ROOT . '/includes/pipeline.php';

$user = require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only.', 405);
}

$body = json_input();
csrf_guard($body['csrf'] ?? null);

$projectId = (int) ($body['project_id'] ?? 0);
$action = ($body['action'] ?? 'create') === 'edit' ? 'edit' : 'create';
$input = trim((string) ($body['input'] ?? ''));
$assetIds = is_array($body['asset_ids'] ?? null) ? $body['asset_ids'] : [];

$project = project_get($projectId, (int) $user['id']);
if (!$project) {
    json_error('That project does not exist.', 404);
}
if ($input === '') {
    json_error($action === 'create'
        ? 'Describe the website you want before building.'
        : 'Describe the change you want to make.');
}
if (mb_strlen($input) > 4000) {
    json_error('That description is too long — please keep it under 4000 characters.');
}

// Generation can take a while. Release the session lock first so the browser's
// status polling is not queued behind this request.
session_write_close();

// These requests are long-running by nature; PHP's default limit would kill
// a Claude generation part-way through.
@set_time_limit(600);
@ini_set('max_execution_time', '600');

try {
    $result = run_generation($project, $action, $input, $assetIds);
} catch (Throwable $e) {
    json_out([
        'ok'    => false,
        'error' => $e->getMessage(),
        'project_id' => $projectId,
    ], 200); // the project row already records the failure; the UI renders it
}

json_out([
    'ok'         => true,
    'project_id' => $projectId,
    'version'    => $result['version'],
    'summary'    => $result['summary'],
    'engine'     => $result['engine'],
]);
