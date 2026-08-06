<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();
$projects = project_list((int) $user['id']);

$counts = ['all' => count($projects), 'building' => 0, 'ready' => 0, 'failed' => 0];
foreach ($projects as $project) {
    $status = (string) $project['status'];
    if (isset($counts[$status])) {
        $counts[$status]++;
    } elseif ($status === 'queued') {
        $counts['building']++;
    }
}

layout_header(['title' => 'Library', 'active' => 'library']);
?>

<section class="section library" id="library" data-csrf="<?= e(csrf_token()) ?>">
    <div class="shell">

        <header class="section-head section-head-row" data-reveal>
            <div>
                <span class="eyebrow">Your work</span>
                <h1>Library</h1>
                <p class="muted">Every site you've built, with its progress and version history.</p>
            </div>
            <a class="btn btn-primary" href="create.php">New website</a>
        </header>

        <?php if (!$projects): ?>

            <div class="empty" data-reveal>
                <div class="empty-art" aria-hidden="true"></div>
                <h2>Nothing here yet</h2>
                <p class="muted">Describe a website on the Create page and it'll show up here while it builds.</p>
                <a class="btn btn-primary btn-lg" href="create.php">Build your first site</a>
            </div>

        <?php else: ?>

            <div class="library-tools" data-reveal>
                <div class="filters" role="tablist">
                    <button class="filter is-active" data-filter="all" role="tab" aria-selected="true">
                        All <span><?= $counts['all'] ?></span>
                    </button>
                    <button class="filter" data-filter="building" role="tab" aria-selected="false">
                        Building <span><?= $counts['building'] ?></span>
                    </button>
                    <button class="filter" data-filter="ready" role="tab" aria-selected="false">
                        Ready <span><?= $counts['ready'] ?></span>
                    </button>
                    <button class="filter" data-filter="failed" role="tab" aria-selected="false">
                        Failed <span><?= $counts['failed'] ?></span>
                    </button>
                </div>
                <label class="search">
                    <span class="sr-only">Search projects</span>
                    <input type="search" id="librarySearch" placeholder="Search projects…" autocomplete="off">
                </label>
            </div>

            <div class="library-grid" id="libraryGrid">
                <?php foreach ($projects as $project):
                    $id = (int) $project['id'];
                    $status = (string) $project['status'];
                    $bucket = in_array($status, ['queued', 'building'], true) ? 'building' : $status;
                    $version = (int) $project['current_version'];
                    ?>
                    <article class="project" data-id="<?= $id ?>" data-bucket="<?= e($bucket) ?>"
                             data-name="<?= e(mb_strtolower((string) $project['name'])) ?>" data-reveal>

                        <?php $isApp = ($project['kind'] ?? 'site') === 'app'; ?>
                        <div class="project-thumb">
                            <?php if ($isApp): ?>
                                <iframe src="live.php?p=<?= $id ?>"
                                        title="Preview of <?= e($project['name']) ?>"
                                        loading="lazy" scrolling="no" tabindex="-1"></iframe>
                                <a class="project-thumb-link" href="build.php?id=<?= $id ?>"><span>Open</span></a>
                            <?php elseif ($version > 0): ?>
                                <iframe src="preview.php?p=<?= $id ?>&v=<?= $version ?>"
                                        title="Preview of <?= e($project['name']) ?>"
                                        loading="lazy" scrolling="no" tabindex="-1"></iframe>
                                <a class="project-thumb-link" href="project.php?id=<?= $id ?>">
                                    <span>Open</span>
                                </a>
                            <?php else: ?>
                                <div class="project-thumb-empty" data-thumb-empty>
                                    <span class="spinner spinner-lg" aria-hidden="true"></span>
                                    <span class="js-stage"><?= e($project['stage']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="project-body">
                            <div class="project-top">
                                <h2><a href="<?= ($project["kind"] ?? "site") === "app" ? "build.php?id=" . $id : "project.php?id=" . $id ?>"><?= e($project["name"]) ?></a></h2>
                                <span class="status status-<?= e($bucket) ?>" data-status><?= e(ucfirst($status)) ?></span>
                            </div>

                            <p class="muted small">
                                <?= $isApp ? 'App with data' : e(site_type_label((string) $project['site_type'])) ?>
                                <?php if (!$isApp): ?>
                                    &middot; <span data-version><?= $version > 0 ? 'v' . $version : 'not built' ?></span>
                                <?php endif; ?>
                                &middot; <span data-updated><?= e(human_time((string) $project['updated_at'])) ?></span>
                            </p>

                            <div class="project-progress" data-progress-wrap <?= $bucket === 'building' ? '' : 'hidden' ?>>
                                <div class="progress-track">
                                    <div class="progress-bar" data-bar style="width: <?= (int) $project['progress'] ?>%"></div>
                                </div>
                                <span class="small muted" data-stage><?= e($project['stage']) ?></span>
                            </div>

                            <?php if ($status === 'failed' && $project['error']): ?>
                                <p class="alert alert-bad small" data-error><?= e((string) $project['error']) ?></p>
                            <?php else: ?>
                                <p class="alert alert-bad small" data-error hidden></p>
                            <?php endif; ?>

                            <div class="project-actions">
                                <?php if (($project['kind'] ?? 'site') === 'app'): ?>
                                    <a class="btn btn-ghost btn-sm" href="build.php?id=<?= $id ?>">Open</a>
                                    <a class="btn btn-ghost btn-sm" href="live.php?p=<?= $id ?>" target="_blank" rel="noopener">View live</a>
                                <?php else: ?>
                                <a class="btn btn-ghost btn-sm" href="project.php?id=<?= $id ?>">Open</a>
                                <?php if ($version > 0): ?>
                                    <a class="btn btn-ghost btn-sm" href="download.php?p=<?= $id ?>&v=<?= $version ?>">Download</a>
                                <?php endif; ?>
                                <?php endif; ?>
                                <button class="btn btn-ghost btn-sm js-rename" data-id="<?= $id ?>">Rename</button>
                                <button class="btn btn-ghost btn-sm btn-danger js-delete" data-id="<?= $id ?>">Delete</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="empty-filtered muted" id="noMatches" hidden>No projects match that filter.</p>

        <?php endif; ?>
    </div>
</section>

<?php layout_footer(['scripts' => ['assets/js/library.js']]); ?>
