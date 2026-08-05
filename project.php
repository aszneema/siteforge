<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();

$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$project = project_get($projectId, (int) $user['id']);
if (!$project) {
    http_response_code(404);
    layout_header(['title' => 'Not found']);
    echo '<section class="section"><div class="shell empty"><h1>Project not found</h1>'
       . '<p class="muted">It may have been deleted.</p>'
       . '<a class="btn btn-primary" href="library.php">Back to the library</a></div></section>';
    layout_footer();
    exit;
}

$versions   = project_versions($projectId);
$messages   = messages_for($projectId);
$current    = (int) $project['current_version'];
$viewing    = isset($_GET['v']) ? (int) $_GET['v'] : $current;
if ($viewing < 1 || !version_get($projectId, $viewing)) {
    $viewing = $current;
}

$versionRow = $viewing > 0 ? version_get($projectId, $viewing) : null;
$files      = $versionRow ? version_files((int) $versionRow['id']) : [];
$entry      = $viewing > 0 ? version_entry_file($projectId, $viewing, (string) $project['site_type']) : '';
$pages      = array_values(array_filter(
    array_column($files, 'path'),
    static fn ($p) => (bool) preg_match('/\.(html?|php)$/i', $p)
));

$isBuilding = in_array((string) $project['status'], ['queued', 'building'], true);

layout_header(['title' => (string) $project['name'], 'active' => 'library']);
?>

<section class="workspace" id="workspace"
         data-project="<?= $projectId ?>"
         data-version="<?= $viewing ?>"
         data-csrf="<?= e(csrf_token()) ?>"
         data-building="<?= $isBuilding ? '1' : '0' ?>">

    <div class="shell">

        <!-- Header ------------------------------------------------------- -->
        <header class="workspace-head">
            <div class="workspace-title">
                <a class="back-link" href="library.php">&larr; Library</a>
                <h1 id="projectName" data-id="<?= $projectId ?>"><?= e($project['name']) ?></h1>
                <div class="badges">
                    <span class="badge"><?= e(site_type_label((string) $project['site_type'])) ?></span>
                    <?php if ($project['engine'] !== ''): ?>
                        <span class="badge"><?= $project['engine'] === 'claude' ? 'Claude' : 'Offline builder' ?></span>
                    <?php endif; ?>
                    <span class="badge badge-soft"><?= count($versions) ?> version<?= count($versions) === 1 ? '' : 's' ?></span>
                    <span class="status status-<?= $isBuilding ? 'building' : e((string) $project['status']) ?>" id="projectStatus">
                        <?= e(ucfirst((string) $project['status'])) ?>
                    </span>
                </div>
            </div>

            <div class="workspace-actions">
                <button class="btn btn-ghost btn-sm js-rename-project">Rename</button>
                <?php if ($viewing > 0): ?>
                    <a class="btn btn-ghost btn-sm" href="preview.php?p=<?= $projectId ?>&v=<?= $viewing ?>" target="_blank" rel="noopener">Open preview</a>
                    <a class="btn btn-primary btn-sm" href="download.php?p=<?= $projectId ?>&v=<?= $viewing ?>">Download ZIP</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($isBuilding): ?>
            <div class="progress-panel" id="progressPanel">
                <div class="progress-top">
                    <strong id="progressStage"><?= e($project['stage']) ?></strong>
                    <span id="progressPercent"><?= (int) $project['progress'] ?>%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-bar" id="progressBar" style="width: <?= (int) $project['progress'] ?>%"></div>
                </div>
            </div>
        <?php elseif ((string) $project['status'] === 'failed' && $project['error']): ?>
            <div class="alert alert-bad"><strong>That build failed.</strong><br><?= nl2br(e((string) $project['error'])) ?></div>
        <?php endif; ?>

        <div class="workspace-grid">

            <!-- Preview -------------------------------------------------- -->
            <div class="workspace-main">
                <?php if ($viewing > 0): ?>
                    <div class="preview-toolbar">
                        <label class="page-select">
                            <span class="sr-only">Page</span>
                            <select id="pageSelect">
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?= e($page) ?>" <?= $page === $entry ? 'selected' : '' ?>><?= e($page) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="viewport-toggle" role="group" aria-label="Preview width">
                            <button type="button" class="vp is-active" data-width="1280" title="Desktop">Desktop</button>
                            <button type="button" class="vp" data-width="820" title="Tablet">Tablet</button>
                            <button type="button" class="vp" data-width="390" title="Mobile">Mobile</button>
                        </div>

                        <button type="button" class="btn btn-ghost btn-sm" id="reloadPreview">Reload</button>
                    </div>

                    <!-- The frame renders at a real device width and is scaled
                         down to fit, so "Desktop" means 1280px even in a
                         narrow panel. -->
                    <div class="preview-stage" id="previewStage">
                        <div class="preview-viewport" id="previewViewport">
                            <iframe id="previewFrame"
                                    src="preview.php?p=<?= $projectId ?>&v=<?= $viewing ?>&f=<?= rawurlencode($entry) ?>"
                                    title="Preview of <?= e($project['name']) ?>"></iframe>
                        </div>
                    </div>

                    <?php if ($viewing !== $current && $current > 0): ?>
                        <div class="version-notice">
                            You're viewing <strong>v<?= $viewing ?></strong>, but <strong>v<?= $current ?></strong> is the latest.
                            <button class="btn btn-sm btn-primary js-restore" data-version="<?= $viewing ?>">Restore v<?= $viewing ?></button>
                            <a class="btn btn-sm btn-ghost" href="project.php?id=<?= $projectId ?>">Back to latest</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="preview-stage preview-stage-empty">
                        <div class="empty">
                            <span class="spinner spinner-lg" aria-hidden="true"></span>
                            <h2><?= $isBuilding ? 'Building your site…' : 'Nothing built yet' ?></h2>
                            <p class="muted"><?= $isBuilding
                                ? 'The preview appears here as soon as the first version is written.'
                                : 'This project has no versions. Ask for a change below to build one.' ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -------------------------------------------------- -->
            <aside class="workspace-side">
                <div class="tabs" role="tablist">
                    <button class="tab is-active" data-tab="chat" role="tab" aria-selected="true">Edit</button>
                    <button class="tab" data-tab="versions" role="tab" aria-selected="false">Versions</button>
                    <button class="tab" data-tab="files" role="tab" aria-selected="false">Files</button>
                </div>

                <!-- Edit chat -->
                <div class="tab-panel is-active" data-panel="chat">
                    <div class="chat chat-compact" id="chat">
                        <?php if (!$messages): ?>
                            <div class="msg msg-bot">
                                <span class="msg-avatar" aria-hidden="true">W</span>
                                <div class="msg-body"><p>Describe any change and I'll rebuild the site.</p></div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($messages as $message):
                            $role = (string) $message['role'];
                            $class = $role === 'user' ? 'msg-user' : ($role === 'error' ? 'msg-error' : 'msg-bot');
                            ?>
                            <div class="msg <?= $class ?>">
                                <?php if ($role !== 'user'): ?>
                                    <span class="msg-avatar" aria-hidden="true"><?= $role === 'error' ? '!' : 'W' ?></span>
                                <?php endif; ?>
                                <div class="msg-body">
                                    <p><?= nl2br(e((string) $message['body'])) ?></p>
                                    <time><?= e(human_time((string) $message['created_at'])) ?></time>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form class="composer composer-compact" id="editForm" autocomplete="off">
                        <label class="sr-only" for="instruction">Describe your change</label>
                        <textarea id="instruction" rows="3" maxlength="4000"
                                  placeholder="e.g. Make it dark, add a pricing section, and change the headline to &quot;Care you can count on&quot;."></textarea>

                        <div class="composer-error" id="editError" role="alert" hidden></div>

                        <div class="composer-tools composer-tools-compact">
                            <div class="uploads" id="uploads">
                                <label class="upload-add">
                                    <input type="file" id="fileInput" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden>
                                    <span aria-hidden="true">+</span>
                                    <span class="sr-only">Add images</span>
                                </label>
                            </div>
                            <button class="btn btn-primary" type="submit" id="applyBtn">
                                <span class="btn-label">Apply change</span>
                                <span class="spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Versions -->
                <div class="tab-panel" data-panel="versions">
                    <?php if (!$versions): ?>
                        <p class="muted pad">No versions yet.</p>
                    <?php else: ?>
                        <ol class="timeline">
                            <?php foreach ($versions as $version):
                                $no = (int) $version['version_no'];
                                ?>
                                <li class="<?= $no === $viewing ? 'is-current' : '' ?>">
                                    <div class="timeline-dot" aria-hidden="true"></div>
                                    <div class="timeline-body">
                                        <div class="timeline-head">
                                            <strong>v<?= $no ?></strong>
                                            <?php if ($no === $current): ?><span class="badge badge-soft">latest</span><?php endif; ?>
                                            <time><?= e(human_time((string) $version['created_at'])) ?></time>
                                        </div>
                                        <p><?= e((string) $version['summary']) ?></p>
                                        <p class="muted small"><?= (int) $version['file_count'] ?> files
                                           &middot; <?= $version['engine'] === 'claude' ? 'Claude' : 'Offline builder' ?></p>
                                        <div class="timeline-actions">
                                            <a class="btn btn-ghost btn-sm" href="project.php?id=<?= $projectId ?>&v=<?= $no ?>">View</a>
                                            <a class="btn btn-ghost btn-sm" href="download.php?p=<?= $projectId ?>&v=<?= $no ?>">ZIP</a>
                                            <?php if ($no !== $current): ?>
                                                <button class="btn btn-ghost btn-sm js-restore" data-version="<?= $no ?>">Restore</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>

                <!-- Files -->
                <div class="tab-panel" data-panel="files">
                    <?php if (!$files): ?>
                        <p class="muted pad">No files yet.</p>
                    <?php else: ?>
                        <ul class="file-list">
                            <?php foreach ($files as $file): ?>
                                <li>
                                    <a href="preview.php?p=<?= $projectId ?>&v=<?= $viewing ?>&f=<?= rawurlencode((string) $file['path']) ?>"
                                       target="_blank" rel="noopener">
                                        <span class="file-name"><?= e((string) $file['path']) ?></span>
                                        <span class="file-size"><?= e(human_size((int) $file['size'])) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php layout_footer(['scripts' => ['assets/js/project.js']]); ?>
