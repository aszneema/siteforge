<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login_api();
$userId = (int) $user['id'];

// Read-only endpoint: drop the session lock immediately so polling never
// blocks the generation request running alongside it.
session_write_close();

// Poll one project, or every project in the library at once.
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $project = project_get($id, $userId);
    if (!$project) {
        json_error('That project does not exist.', 404);
    }
    json_out(['ok' => true, 'project' => status_row($project)]);
}

$rows = array_map('status_row', project_list($userId));
json_out(['ok' => true, 'projects' => $rows]);

function status_row(array $project): array
{
    return [
        'id'       => (int) $project['id'],
        'name'     => $project['name'],
        'status'   => $project['status'],
        'progress' => (int) $project['progress'],
        'stage'    => $project['stage'],
        'version'  => (int) $project['current_version'],
        'engine'   => $project['engine'],
        'error'    => $project['error'],
        'updated'  => human_time((string) $project['updated_at']),
    ];
}
