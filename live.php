<?php
declare(strict_types=1);

/**
 * The live site: renders a built app straight from the database.
 *
 * Public — no login. Anyone with the link sees the site as visitors would.
 *   live.php?p=12                  home page
 *   live.php?p=12&page=all-games   a page by slug
 *   live.php?p=12&css=1            the stylesheet
 *   live.php?p=12&img=8            an uploaded image
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/builder.php';

session_write_close();

$projectId = isset($_GET['p']) ? (int) $_GET['p'] : 0;
$project = project_get($projectId);

if (!$project || $project['kind'] !== 'app') {
    http_response_code(404);
    exit('Site not found.');
}

$spec = live_spec($project);

// --- Stylesheet -------------------------------------------------------------
if (isset($_GET['css'])) {
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo SiteForge\CssKit::build(SiteForge\Blueprint::theme($spec), $spec);

    // Bits only a data-driven site needs.
    echo <<<'CSS'

    /* --- Entries -------------------------------------------------- */
    .entry-line {
      display: flex; justify-content: space-between; align-items: baseline; gap: 1rem;
      padding: .55rem 0; border-bottom: 1px solid var(--border); font-size: .95rem;
    }
    .entry-line:last-child { border-bottom: 0; }
    .entry-line > span { color: var(--text); }
    .entry-line strong { color: var(--ink); text-align: right; }
    .stars { letter-spacing: .06em; color: var(--primary); font-size: 1.05rem; }
    .card h3 a { color: var(--ink); }
    .card h3 a:hover { color: var(--primary); }
    CSS;
    exit;
}

// --- Uploaded images --------------------------------------------------------
if (isset($_GET['img'])) {
    $stmt = db()->prepare('SELECT * FROM assets WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['img'], (int) $project['user_id']]);
    $asset = $stmt->fetch();

    $path = $asset ? config('storage_path') . '/uploads/' . basename((string) $asset['stored']) : null;
    if (!$asset || !$path || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }

    header('Content-Type: ' . ($asset['mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

// --- Page -------------------------------------------------------------------
$pages = pages_for($projectId);
if ($pages === []) {
    http_response_code(404);
    exit('This site has no pages yet.');
}

$slug = (string) ($_GET['page'] ?? '');
$page = $slug !== '' ? page_by_slug($projectId, $slug) : page_home($projectId);
if (!$page) {
    $page = $pages[0];
}

$collection = $page['collection_id'] ? collection_get((int) $page['collection_id'], $projectId) : null;
$fields = $collection ? fields_for((int) $collection['id']) : [];
$entries = $collection ? entries_for((int) $collection['id'], $collection) : [];

$entryId = isset($_GET['entry']) ? (int) $_GET['entry'] : 0;
$entry = $entryId && $collection ? entry_get($entryId, (int) $collection['id']) : null;

$name = (string) $project['name'];
$title = $entry ? entry_title($fields, $entry['values']) . ' — ' . $name : $page['title'] . ' — ' . $name;

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(mb_substr((string) $page['intro'], 0, 155)) ?>">
<link rel="stylesheet" href="live.php?p=<?= $projectId ?>&amp;css=1">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="siteHeader">
  <div class="container header-inner">
    <a class="brand" href="live.php?p=<?= $projectId ?>">
      <span class="brand-mark" aria-hidden="true"></span>
      <span><?= e($name) ?></span>
    </a>
    <nav class="nav" id="nav" aria-label="Main navigation">
      <?php foreach ($pages as $item): ?>
        <a href="live.php?p=<?= $projectId ?>&amp;page=<?= e((string) $item['slug']) ?>"
           <?= (int) $item['id'] === (int) $page['id'] && !$entry ? 'class="is-active" aria-current="page"' : '' ?>>
          <?= e((string) $item['title']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="header-side">
      <button class="burger" id="burger" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main id="main">

<?php if ($entry): // ---------------- a single entry ---------------------- ?>

  <section class="page-header">
    <div class="container">
      <p class="eyebrow"><a href="live.php?p=<?= $projectId ?>&amp;page=<?= e((string) $page['slug']) ?>">&larr; <?= e((string) $collection['name']) ?></a></p>
      <h1><?= e(entry_title($fields, $entry['values'])) ?></h1>
    </div>
  </section>

  <section class="section">
    <div class="container narrow">
      <?= live_entry_detail($fields, $entry['values'], $projectId) ?>
    </div>
  </section>

<?php else: // ------------------- a normal page -------------------------- ?>

  <section class="page-header">
    <div class="container">
      <h1><?= e((string) $page['title']) ?></h1>
      <?php if (trim((string) $page['intro']) !== ''): ?>
        <p><?= e((string) $page['intro']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <section class="section">
      <div class="container narrow">
        <?php foreach (preg_split('/\n\s*\n/', (string) $page['body']) as $paragraph): ?>
          <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($collection): ?>
    <section class="section section-alt">
      <div class="container">
        <?php if ($entries === []): ?>
          <div class="empty">
            <h2>Nothing here yet</h2>
            <p>No <?= e(mb_strtolower((string) $collection['name'])) ?> have been added.</p>
          </div>
        <?php else: ?>
          <header class="section-head">
            <h2 class="section-title"><?= count($entries) ?> <?= e(mb_strtolower(count($entries) === 1
                ? ($collection['singular'] ?: $collection['name'])
                : $collection['name'])) ?></h2>
          </header>
          <div class="card-grid">
            <?php foreach ($entries as $row): ?>
              <?= live_entry_card($fields, $row, $projectId, (string) $page['slug']) ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

<?php endif; ?>

</main>

<footer class="site-footer">
  <div class="container footer-base">
    <span>&copy; <?= date('Y') ?> <?= e($name) ?></span>
    <span>Built with SiteForge</span>
  </div>
</footer>

<script>
(function () {
  var burger = document.getElementById('burger'), nav = document.getElementById('nav');
  if (burger && nav) burger.addEventListener('click', function () {
    burger.setAttribute('aria-expanded', String(nav.classList.toggle('is-open')));
  });
  var header = document.getElementById('siteHeader');
  if (header) {
    var s = function () { header.classList.toggle('is-scrolled', window.scrollY > 12); };
    s(); window.addEventListener('scroll', s, { passive: true });
  }
})();
</script>
</body>
</html>
<?php

// ---------------------------------------------------------------------------

/** A minimal spec so the live site can borrow the generated-site stylesheet. */
function live_spec(array $project): array
{
    return [
        'name'     => (string) $project['name'],
        'palette'  => 'indigo',
        'style'    => 'modern',
        'dark'     => false,
        'sections' => ['hero'],
        'components' => [],
        'motion'   => 'subtle',
    ];
}

/** Render one entry as a card in the listing. */
function live_entry_card(array $fields, array $row, int $projectId, string $pageSlug): string
{
    $values = $row['values'];
    $href = 'live.php?p=' . $projectId . '&page=' . rawurlencode($pageSlug) . '&entry=' . (int) $row['id'];

    $image = '';
    $lines = '';
    $titleShown = false;

    foreach ($fields as $field) {
        $key = (string) $field['key'];
        $value = $values[$key] ?? null;
        if ($value === null || $value === '' || $value === false) {
            continue;
        }

        if ($field['type'] === 'image' && $image === '') {
            $image = '<img class="tile-visual" src="live.php?p=' . $projectId . '&img=' . (int) $value
                   . '" alt="" loading="lazy" style="aspect-ratio:16/10;object-fit:cover;border-radius:var(--radius) var(--radius) 0 0">';
            continue;
        }
        if (!$titleShown && in_array($field['type'], ['text', 'longtext'], true)) {
            $titleShown = true;
            continue; // shown as the card heading
        }
        if ($field['type'] === 'longtext') {
            continue; // too long for a card
        }

        $lines .= '<div class="entry-line"><span>' . e((string) $field['label']) . '</span>'
                . live_value($field, $value, $projectId) . '</div>';
    }

    return '<article class="card" style="padding:0;overflow:hidden">' . $image
         . '<div style="padding:1.5rem 1.4rem">'
         . '<h3 style="margin-bottom:.6rem"><a href="' . $href . '">'
         . e(entry_title($fields, $values)) . '</a></h3>'
         . $lines . '</div></article>';
}

/** Render every field of one entry, for the detail page. */
function live_entry_detail(array $fields, array $values, int $projectId): string
{
    $out = '';
    foreach ($fields as $field) {
        $value = $values[$field['key']] ?? null;
        if ($value === null || $value === '' || $value === false) {
            continue;
        }

        if ($field['type'] === 'image') {
            $out .= '<img src="live.php?p=' . $projectId . '&img=' . (int) $value
                  . '" alt="" style="width:100%;border-radius:var(--radius-lg);margin-bottom:1.5rem">';
            continue;
        }
        if ($field['type'] === 'longtext') {
            $out .= '<h3>' . e((string) $field['label']) . '</h3><p>' . nl2br(e((string) $value)) . '</p>';
            continue;
        }

        $out .= '<div class="entry-line"><span>' . e((string) $field['label']) . '</span>'
              . live_value($field, $value, $projectId) . '</div>';
    }

    return $out !== '' ? $out : '<p>This entry has no details yet.</p>';
}

/** Format a single value according to its field type. */
function live_value(array $field, mixed $value, int $projectId): string
{
    switch ($field['type']) {
        case 'rating':
            $stars = (int) $value;
            return '<strong class="stars" aria-label="' . $stars . ' out of 5">'
                 . str_repeat('★', $stars) . '<span style="opacity:.25">' . str_repeat('★', 5 - $stars) . '</span></strong>';

        case 'checkbox':
            return '<strong>' . ($value ? 'Yes' : 'No') . '</strong>';

        case 'url':
            return '<strong><a href="' . e((string) $value) . '" rel="noopener nofollow" target="_blank">Visit</a></strong>';

        case 'date':
            $time = strtotime((string) $value);
            return '<strong>' . e($time ? date('j M Y', $time) : (string) $value) . '</strong>';

        case 'image':
            return '<strong><img src="live.php?p=' . $projectId . '&img=' . (int) $value
                 . '" alt="" style="height:36px;border-radius:6px"></strong>';

        default:
            return '<strong>' . e((string) $value) . '</strong>';
    }
}
