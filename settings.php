<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();

$mode = (string) config('generator', 'auto');
$key = (string) config('anthropic_api_key', '');
$engine = SiteForge\GeneratorFactory::describe($mode, $key);
$hasSdk = class_exists(Anthropic\Client::class);

$projects = project_list((int) $user['id']);
$versionCount = (int) db()->query(
    'SELECT COUNT(*) FROM versions v JOIN projects p ON p.id = v.project_id WHERE p.user_id = ' . (int) $user['id']
)->fetchColumn();

// Rough on-disk size of this user's generated sites.
$bytes = 0;
foreach ($projects as $project) {
    $dir = project_dir((int) $project['id']);
    if (!is_dir($dir)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile()) {
            $bytes += $file->getSize();
        }
    }
}

layout_header(['title' => 'Settings', 'active' => 'settings']);
?>

<section class="section">
    <div class="shell narrow">

        <header class="section-head" data-reveal>
            <span class="eyebrow">Settings</span>
            <h1>Your setup</h1>
        </header>

        <!-- Engine ---------------------------------------------------------->
        <div class="panel" data-reveal>
            <div class="panel-head">
                <h2>Generation engine</h2>
                <span class="status status-<?= $engine['engine'] === 'claude' ? 'ready' : 'building' ?>">
                    <?= e($engine['label']) ?>
                </span>
            </div>

            <p class="muted"><?= e($engine['note']) ?></p>

            <dl class="kv">
                <div><dt>Mode</dt><dd><code><?= e($mode) ?></code> <span class="muted small">(config.php &rarr; <code>generator</code>)</span></dd></div>
                <div><dt>Model</dt><dd><code><?= e((string) config('model')) ?></code></dd></div>
                <div><dt>API key</dt><dd><?= $key !== ''
                    ? '<span class="ok">detected</span> <span class="muted small">(' . e(substr($key, 0, 7)) . '&hellip;' . e(substr($key, -4)) . ')</span>'
                    : '<span class="warn">not set</span>' ?></dd></div>
                <div><dt>Anthropic SDK</dt><dd><?= $hasSdk
                    ? '<span class="ok">installed</span>'
                    : '<span class="warn">missing</span> <span class="muted small">— run <code>composer install</code></span>' ?></dd></div>
            </dl>

            <?php if ($key === ''): ?>
                <div class="callout">
                    <h3>Turn on Claude generation</h3>
                    <p>The API key is read from the environment or <code>config.php</code> — deliberately not
                       from a form on this page, so it never gets stored in the database or posted through a browser.</p>
                    <p class="mb-0">Set it before starting your server:</p>
                    <pre><code>export ANTHROPIC_API_KEY="sk-ant-…"</code></pre>
                    <p class="muted small mb-0">Until then, SiteForge builds sites with its offline template engine —
                       everything else in the app works exactly the same.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Account --------------------------------------------------------->
        <div class="panel" data-reveal>
            <div class="panel-head"><h2>Account</h2></div>
            <dl class="kv">
                <div><dt>Name</dt><dd><?= e($user['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
                <div><dt>Joined</dt><dd><?= e(date('j F Y', strtotime((string) $user['created_at']) ?: time())) ?></dd></div>
            </dl>
            <a class="btn btn-ghost" href="logout.php">Sign out</a>
        </div>

        <!-- Storage --------------------------------------------------------->
        <div class="panel" data-reveal>
            <div class="panel-head"><h2>Your work</h2></div>
            <div class="stat-row">
                <div class="stat-tile"><strong><?= count($projects) ?></strong><span>projects</span></div>
                <div class="stat-tile"><strong><?= $versionCount ?></strong><span>versions saved</span></div>
                <div class="stat-tile"><strong><?= e(human_size($bytes)) ?></strong><span>on disk</span></div>
            </div>
            <p class="muted small">Generated sites live in <code>storage/projects/</code>. Deleting a project
               from the Library removes its files too.</p>
        </div>

        <!-- Output formats -------------------------------------------------->
        <div class="panel" data-reveal>
            <div class="panel-head"><h2>Output formats</h2></div>
            <dl class="kv">
                <?php foreach (site_types() as $key => $type): ?>
                    <div><dt><?= e($type['label']) ?></dt><dd><?= e($type['hint']) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>

    </div>
</section>

<?php layout_footer(); ?>
