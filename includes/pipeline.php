<?php
declare(strict_types=1);

use SiteForge\GeneratorFactory;

require_once __DIR__ . '/projects.php';

/**
 * Runs one generation (a first build or an edit) end to end:
 * generator → files → new version → chat log.
 *
 * @param string   $action    'create' or 'edit'
 * @param string   $input     the brief (create) or the instruction (edit)
 * @param int[]    $assetIds  uploaded images to include
 * @return array{version:int,summary:string,engine:string}
 */
function run_generation(array $project, string $action, string $input, array $assetIds = []): array
{
    $projectId = (int) $project['id'];
    $siteType  = (string) $project['site_type'];

    $progress = static function (int $percent, string $stage) use ($projectId): void {
        project_progress($projectId, $percent, $stage);
    };

    project_progress($projectId, 5, $action === 'create' ? 'Starting build' : 'Reading your change', 'building');
    message_add($projectId, 'user', $input);

    try {
        $generator = GeneratorFactory::make(
            (string) config('generator', 'auto'),
            (string) config('anthropic_api_key', ''),
            (string) config('model', 'claude-opus-5')
        );

        $images = prepare_images($assetIds, (int) $project['user_id'], $siteType);

        if ($action === 'create') {
            $result = $generator->create([
                'brief'     => $input,
                'site_type' => $siteType,
                'name'      => (string) $project['name'],
                'images'    => $images,
                'progress'  => $progress,
            ]);
            $prompt = $input;
        } else {
            $currentVersion = (int) $project['current_version'];
            if ($currentVersion < 1) {
                throw new RuntimeException('This project has no built version to edit yet.');
            }
            $version = version_get($projectId, $currentVersion);

            $result = $generator->edit([
                'instruction' => $input,
                'site_type'   => $siteType,
                'name'        => (string) $project['name'],
                'files'       => version_read_files($projectId, $currentVersion),
                'spec'        => $version && $version['spec'] ? json_decode((string) $version['spec'], true) : null,
                'images'      => $images,
                'progress'    => $progress,
            ]);
            $prompt = $input;
        }

        project_progress($projectId, 92, 'Saving version');

        $versionNo = version_store(
            $projectId,
            $result->files,
            $prompt,
            $result->summary,
            $generator->id(),
            $result->spec
        );

        message_add($projectId, 'assistant', $result->summary);

        return [
            'version' => $versionNo,
            'summary' => $result->summary,
            'engine'  => $generator->id(),
        ];
    } catch (Throwable $e) {
        project_fail($projectId, $e->getMessage());
        message_add($projectId, 'error', $e->getMessage());
        throw $e;
    }
}

/**
 * Load uploaded images and work out where they should sit inside the site.
 *
 * @return array<int,array{name:string,path:string,contents:string}>
 */
function prepare_images(array $assetIds, int $userId, string $siteType): array
{
    $assetIds = array_values(array_filter(array_map('intval', $assetIds)));
    if ($assetIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
    $stmt = db()->prepare(
        "SELECT * FROM assets WHERE user_id = ? AND id IN ({$placeholders}) ORDER BY id"
    );
    $stmt->execute(array_merge([$userId], $assetIds));

    $base = $siteType === 'php' ? 'assets/img/' : 'img/';
    $images = [];
    $used = [];

    foreach ($stmt->fetchAll() as $row) {
        $file = config('storage_path') . '/uploads/' . $row['stored'];
        if (!is_file($file)) {
            continue;
        }

        $name = pathinfo((string) $row['filename'], PATHINFO_FILENAME);
        $ext = strtolower(pathinfo((string) $row['filename'], PATHINFO_EXTENSION)) ?: 'png';
        $slug = slugify($name, 'image');

        $candidate = $slug . '.' . $ext;
        $n = 2;
        while (isset($used[$candidate])) {
            $candidate = $slug . '-' . $n++ . '.' . $ext;
        }
        $used[$candidate] = true;

        $images[] = [
            'name'     => (string) $row['filename'],
            'path'     => $base . $candidate,
            'contents' => (string) file_get_contents($file),
        ];
    }

    return $images;
}
