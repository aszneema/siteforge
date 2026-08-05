<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/projects.php';

$user = current_user();
$engine = SiteForge\GeneratorFactory::describe(
    (string) config('generator', 'auto'),
    (string) config('anthropic_api_key', '')
);

$recent = $user ? array_slice(project_list((int) $user['id']), 0, 3) : [];

layout_header(['title' => 'Build a website by describing it', 'active' => 'home']);
?>

<section class="hero">
    <div class="shell hero-inner">
        <div class="hero-copy" data-reveal>
            <span class="pill">
                <span class="pill-dot"></span>
                <?= e($engine['label']) ?> &middot; ready
            </span>

            <h1>Describe the website.<br><span class="grad">Watch it get built.</span></h1>

            <p class="hero-sub">
                Build. Design. Create. Type what you want in plain English, add your images,
                pick HTML or PHP, and SITEFORGE writes the whole thing. Don't like something?
                Say so, and it changes.
            </p>

            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="create.php">Start building</a>
                <a class="btn btn-ghost btn-lg" href="library.php">Open the library</a>
            </div>

            <ul class="hero-facts">
                <li><strong data-count="4">0</strong><span>output formats</span></li>
                <li><strong data-count="16">0</strong><span>industry styles</span></li>
                <li><strong>&infin;</strong><span>edits, versioned</span></li>
            </ul>
        </div>

        <div class="hero-demo" data-reveal>
            <div class="demo-window">
                <div class="demo-bar">
                    <span></span><span></span><span></span>
                    <em>create.php</em>
                </div>
                <div class="demo-body">
                    <div class="demo-msg demo-user">
                        A site for a bakery called Marlow &amp; Bean. Warm colours, show the menu,
                        and a section for opening hours.
                    </div>
                    <div class="demo-msg demo-bot">
                        <span class="demo-step is-done">Reading your brief</span>
                        <span class="demo-step is-done">Choosing a palette</span>
                        <span class="demo-step is-live">Writing pages<span class="dots"><i></i><i></i><i></i></span></span>
                    </div>
                    <div class="demo-progress"><span></span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="shell">
        <header class="section-head" data-reveal>
            <span class="eyebrow">How it works</span>
            <h2>Three steps, no build tools</h2>
        </header>

        <div class="grid grid-3">
            <article class="card" data-reveal>
                <span class="card-num">01</span>
                <h3>Describe it</h3>
                <p>Say what the site is for, who it's for, and anything you care about — colours,
                   sections, tone. Attach images if you have them.</p>
            </article>
            <article class="card" data-reveal>
                <span class="card-num">02</span>
                <h3>Watch it build</h3>
                <p>Progress shows live on the Create page and in your Library, so you can see
                   exactly where a build has got to.</p>
            </article>
            <article class="card" data-reveal>
                <span class="card-num">03</span>
                <h3>Edit in plain English</h3>
                <p>"Make it dark." "Add a pricing section." "Rename it to Riverside Dental."
                   Every change is saved as a new version you can roll back to.</p>
            </article>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="shell">
        <header class="section-head" data-reveal>
            <span class="eyebrow">Output formats</span>
            <h2>Pick what you'll actually deploy</h2>
        </header>

        <div class="grid grid-4">
            <?php foreach (site_types() as $key => $type): ?>
                <article class="type-card" data-reveal>
                    <span class="type-icon"><?= $type['icon'] ?></span>
                    <h3><?= e($type['label']) ?></h3>
                    <p><?= e($type['hint']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($recent): ?>
<section class="section">
    <div class="shell">
        <header class="section-head section-head-row" data-reveal>
            <div>
                <span class="eyebrow">Pick up where you left off</span>
                <h2>Recent projects</h2>
            </div>
            <a class="btn btn-ghost" href="library.php">View all</a>
        </header>

        <div class="grid grid-3">
            <?php foreach ($recent as $project): ?>
                <a class="project-card" href="project.php?id=<?= (int) $project['id'] ?>" data-reveal>
                    <span class="status status-<?= e($project['status']) ?>"><?= e(ucfirst($project['status'])) ?></span>
                    <h3><?= e($project['name']) ?></h3>
                    <p class="muted"><?= e(site_type_label((string) $project['site_type'])) ?>
                       &middot; v<?= (int) $project['current_version'] ?>
                       &middot; <?= e(human_time((string) $project['updated_at'])) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="shell">
        <div class="cta-panel" data-reveal>
            <div>
                <h2>Ready to build something?</h2>
                <p><?= e($engine['note']) ?></p>
            </div>
            <a class="btn btn-primary btn-lg" href="<?= $user ? 'create.php' : 'register.php' ?>">
                <?= $user ? 'Create a site' : 'Get started free' ?>
            </a>
        </div>
    </div>
</section>

<?php layout_footer(); ?>
