<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();

$mode = (string) config('generator', 'auto');
$key = (string) config('anthropic_api_key', '');
$engine = SiteForge\GeneratorFactory::describe($mode, $key);
$status = SiteForge\GeneratorFactory::status($key);
$hasSdk = $status['sdk'];

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
                <div><dt>Claude</dt><dd><?= $status['key'] && $status['sdk']
                    ? '<span class="ok">ready</span> <span class="muted small">(' . e((string) config('model')) . ')</span>'
                    : '<span class="warn">' . ($status['key'] ? 'SDK missing' : 'no API key') . '</span>' ?></dd></div>
                <div><dt>Local AI</dt><dd><?= $status['ollama_up']
                    ? '<span class="ok">ready</span> <span class="muted small">(' . e($status['ollama_model'])
                      . ' &middot; ' . count($status['ollama_models']) . ' model'
                      . (count($status['ollama_models']) === 1 ? '' : 's') . ' installed)</span>'
                    : '<span class="warn">not running</span> <span class="muted small">at <code>'
                      . e($status['ollama_url']) . '</code></span>' ?></dd></div>
                <div><dt>Template builder</dt><dd><span class="ok">always available</span></dd></div>
            </dl>

            <?php if (!$status['ollama_up']): ?>
                <div class="callout">
                    <h3>Turn on free AI generation — no API key</h3>
                    <p>Run a language model on this machine. It writes copy from your actual brief
                       (your history, your products, your prices) instead of using pre-written template text.
                       Free, offline, and nothing leaves your computer.</p>
                    <pre><code># 1. install from https://ollama.com/download, then:
ollama pull llama3.1</code></pre>
                    <p class="muted small mb-0">SiteForge picks it up automatically — no restart needed.
                       Around 5&nbsp;GB to download once; generation then takes roughly 30–90 seconds per site.</p>
                </div>
            <?php endif; ?>

            <?php if (!$status['key']): ?>
                <div class="callout">
                    <h3>Or use Claude for the best quality</h3>
                    <p>The API key is read from the environment or <code>config.php</code> — deliberately not
                       from a form on this page, so it never gets stored in the database or posted through a browser.</p>
                    <pre><code>export ANTHROPIC_API_KEY="sk-ant-…"</code></pre>
                    <p class="muted small mb-0">When a key is present Claude is used in preference to the local model.
                       Costs a few cents per site.</p>
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
