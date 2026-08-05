<?php
declare(strict_types=1);

/**
 * Shared page chrome. Call layout_header([...]) then layout_footer().
 * Options: title, active (nav key), wide (bool), bare (bool — no nav/footer).
 */

/**
 * The SiteForge mark, inlined so CSS can recolour it — the navy facets flip to
 * white on the navy navigation bar and footer.
 */
function brand_mark(string $class = 'brand-mark'): string
{
    return <<<SVG
    <svg class="{$class}" viewBox="0 0 120 132" aria-hidden="true" focusable="false">
      <path class="mk-teal-s" d="M28 53 L28 88" stroke-width="3" stroke-linecap="round" fill="none"/>
      <path class="mk-teal-s" d="M92 53 L92 88" stroke-width="3" stroke-linecap="round" fill="none"/>
      <path class="mk-teal-s" d="M12 72 L38 124 L60 80" fill="none" stroke-width="18" stroke-linejoin="miter"/>
      <path class="mk-navy-s" d="M60 80 L82 124 L108 72" fill="none" stroke-width="18" stroke-linejoin="miter"/>
      <path class="mk-navy-s" d="M33 45 L45 38" stroke-width="3.5" stroke-linecap="round" fill="none"/>
      <path class="mk-navy-s" d="M87 45 L75 38" stroke-width="3.5" stroke-linecap="round" fill="none"/>
      <circle class="mk-navy" cx="28" cy="48" r="5.5"/>
      <circle class="mk-navy" cx="92" cy="48" r="5.5"/>
      <path class="mk-teal" d="M60 6 L44 22 L44 62 L60 76 Z"/>
      <path class="mk-navy" d="M60 6 L76 22 L76 62 L60 76 Z"/>
      <path class="mk-cursor" d="M52 26 L52 49.8 L57.9 44.2 L61.8 52.6 L66 50.8 L62.1 42.5 L68.8 42.1 Z"/>
    </svg>
    SVG;
}

/** The SITEFORGE wordmark: SITE in navy, FORGE in teal. */
function brand_wordmark(): string
{
    return '<span class="brand-word">SITE<span>FORGE</span></span>';
}

function layout_header(array $options = []): void
{
    $title  = $options['title']  ?? 'SiteForge';
    $active = $options['active'] ?? '';
    $bare   = $options['bare']   ?? false;
    $user   = current_user();

    $nav = [
        'home'     => ['index.php',    'Home'],
        'create'   => ['create.php',   'Create'],
        'library'  => ['library.php',  'Library'],
        'settings' => ['settings.php', 'Settings'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; SITEFORGE</title>
<meta name="description" content="SITEFORGE — build, design, create. Describe the website you want, watch it get built, then edit it in plain English.">
<meta name="theme-color" content="#1A2B4C">

<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap">

<link rel="stylesheet" href="assets/css/app.css">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="<?= $bare ? 'is-bare' : '' ?>">

<?php if (!$bare): ?>
<header class="site-header" id="siteHeader">
    <div class="shell header-inner">
        <a class="brand" href="index.php" aria-label="SITEFORGE home">
            <?= brand_mark() ?>
            <?= brand_wordmark() ?>
        </a>

        <nav class="main-nav" aria-label="Main">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= e($href) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="header-actions">
            <?php if ($user): ?>
                <a class="avatar" href="settings.php" title="<?= e($user['email']) ?>">
                    <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                </a>
                <a class="btn btn-ghost btn-sm" href="logout.php">Sign out</a>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="login.php">Sign in</a>
                <a class="btn btn-primary btn-sm" href="register.php">Start building</a>
            <?php endif; ?>
            <button class="burger" id="burger" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <div class="mobile-nav" id="mobileNav">
        <?php foreach ($nav as $key => [$href, $label]): ?>
            <a href="<?= e($href) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($user): ?>
            <a href="logout.php">Sign out</a>
        <?php else: ?>
            <a href="login.php">Sign in</a>
            <a href="register.php">Start building</a>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>

<main class="site-main">
<?php
}

/**
 * Closes the page. Options: bare (bool), scripts (extra script paths, loaded
 * after app.js).
 */
function layout_footer(array $options = []): void
{
    $extra = '';
    foreach ($options['scripts'] ?? [] as $src) {
        $extra .= '<script src="' . e($src) . '"></script>' . "\n";
    }

    if ($options['bare'] ?? false) {
        echo "</main>\n<script src=\"assets/js/app.js\"></script>\n" . $extra . "</body>\n</html>";
        return;
    }
    ?>
</main>

<footer class="site-footer">
    <div class="shell footer-inner">
        <div class="footer-brand">
            <a class="brand" href="index.php">
                <?= brand_mark() ?>
                <?= brand_wordmark() ?>
            </a>
            <p class="footer-tagline">Build. Design. Create.</p>
            <p>Describe the website you want, watch it get built, then change it by describing the change.</p>
        </div>
        <nav aria-label="Footer">
            <h4>Product</h4>
            <a href="index.php">Home</a>
            <a href="create.php">Create</a>
            <a href="library.php">Library</a>
            <a href="settings.php">Settings</a>
        </nav>
    </div>
    <div class="shell footer-base">
        <span>&copy; <?= date('Y') ?> SITEFORGE</span>
        <span>Built with PHP &amp; SQLite</span>
    </div>
</footer>

<script src="assets/js/app.js"></script>
<?= $extra ?>
</body>
</html>
<?php
}
