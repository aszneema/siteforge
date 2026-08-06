<?php
declare(strict_types=1);

/**
 * The builder workspace: the pages and collections that make up an app.
 * Plain forms throughout — nothing here needs JavaScript to work.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/builder.php';

$user = require_login();

$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$project = project_get($projectId, (int) $user['id']);

if (!$project || $project['kind'] !== 'app') {
    http_response_code(404);
    layout_header(['title' => 'Not found']);
    echo '<section class="section"><div class="shell empty"><h1>App not found</h1>'
       . '<p class="muted">It may have been deleted, or it might be a generated site rather than a built one.</p>'
       . '<a class="btn btn-primary" href="library.php">Back to the library</a></div></section>';
    layout_footer();
    exit;
}

$notice = '';
$problem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'add_collection':
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException('Give your collection a name — "Games", "Recipes", "Reviews".');
                }
                $newId = collection_create($projectId, $name, (string) ($_POST['singular'] ?? ''));
                redirect('collection.php?c=' . $newId . '&new=1');

            case 'add_page':
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('Give your page a name.');
                }
                $collectionId = (int) ($_POST['collection_id'] ?? 0) ?: null;
                page_create($projectId, $title, (string) ($_POST['intro'] ?? ''), $collectionId);
                $notice = 'Added the "' . $title . '" page.';
                break;

            case 'save_page':
                $pageId = (int) ($_POST['page_id'] ?? 0);
                if (!page_get($pageId, $projectId)) {
                    throw new RuntimeException('That page no longer exists.');
                }
                page_update(
                    $pageId,
                    $projectId,
                    (string) ($_POST['title'] ?? ''),
                    (string) ($_POST['intro'] ?? ''),
                    (string) ($_POST['body'] ?? ''),
                    (int) ($_POST['collection_id'] ?? 0) ?: null
                );
                $notice = 'Saved.';
                break;

            case 'delete_page':
                page_delete((int) ($_POST['page_id'] ?? 0), $projectId);
                $notice = 'Page deleted.';
                break;

            case 'rename_site':
                if (!project_rename($projectId, (int) $user['id'], (string) ($_POST['name'] ?? ''))) {
                    throw new RuntimeException('That name did not work — 1 to 80 characters please.');
                }
                $project = project_get($projectId, (int) $user['id']);
                $notice = 'Renamed.';
                break;
        }
    } catch (Throwable $e) {
        $problem = $e->getMessage();
    }
}

$pages = pages_for($projectId);
$collections = collections_for($projectId);
$editingId = isset($_GET['page']) ? (int) $_GET['page'] : 0;
$editing = $editingId ? page_get($editingId, $projectId) : null;

layout_header(['title' => (string) $project['name'], 'active' => 'library']);
?>

<section class="workspace">
  <div class="shell">

    <header class="workspace-head">
      <div class="workspace-title">
        <a class="back-link" href="library.php">&larr; Library</a>
        <h1><?= e((string) $project['name']) ?></h1>
        <div class="badges">
          <span class="badge">App with data</span>
          <span class="badge badge-soft"><?= count($pages) ?> page<?= count($pages) === 1 ? '' : 's' ?></span>
          <span class="badge badge-soft"><?= count($collections) ?> collection<?= count($collections) === 1 ? '' : 's' ?></span>
        </div>
      </div>
      <div class="workspace-actions">
        <a class="btn btn-primary btn-sm" href="live.php?p=<?= $projectId ?>" target="_blank" rel="noopener">View live site</a>
      </div>
    </header>

    <?php if ($notice !== ''): ?><div class="alert alert-ok"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($problem !== ''): ?><div class="alert alert-bad"><?= e($problem) ?></div><?php endif; ?>

    <div class="workspace-grid">

      <!-- Collections ------------------------------------------------- -->
      <div class="workspace-main">
        <div class="panel">
          <div class="panel-head">
            <h2>Your data</h2>
          </div>
          <p class="muted">A collection is a kind of thing you want to list — Games, Recipes, Reviews.
             Say what details each one has, and the add-form and listing page are made for you.</p>

          <?php if ($collections): ?>
            <div class="grid grid-3" style="margin: 1.5rem 0">
              <?php foreach ($collections as $collection):
                  $count = entry_count((int) $collection['id']);
                  $fieldCount = count(fields_for((int) $collection['id'])); ?>
                <a class="project-card" href="collection.php?c=<?= (int) $collection['id'] ?>">
                  <h3><?= e((string) $collection['name']) ?></h3>
                  <p class="muted small">
                    <?= $count ?> <?= $count === 1
                        ? e(mb_strtolower((string) ($collection['singular'] ?: 'entry')))
                        : e(mb_strtolower((string) $collection['name'])) ?>
                    &middot; <?= $fieldCount ?> field<?= $fieldCount === 1 ? '' : 's' ?>
                  </p>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" class="stack" style="max-width:520px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_collection">
            <label class="field">
              <span>What do you want to keep a list of?</span>
              <input type="text" name="name" placeholder="Games" maxlength="60" required>
              <small class="hint">Use the plural — "Games", "Recipes", "Books".</small>
            </label>
            <button class="btn btn-primary" type="submit">Create collection</button>
          </form>
        </div>

        <!-- Pages ---------------------------------------------------- -->
        <div class="panel">
          <div class="panel-head"><h2>Your pages</h2></div>

          <ul class="file-list" style="margin-bottom:1.5rem">
            <?php foreach ($pages as $item): ?>
              <li style="display:flex;align-items:center;gap:.5rem">
                <a href="build.php?id=<?= $projectId ?>&page=<?= (int) $item['id'] ?>" style="flex:1">
                  <span class="file-name"><?= e((string) $item['title']) ?></span>
                  <span class="file-size">
                    <?= $item['is_home'] ? 'home' : '/' . e((string) $item['slug']) ?>
                    <?php if ($item['collection_id']):
                        $linked = collection_get((int) $item['collection_id'], $projectId); ?>
                      &middot; lists <?= e((string) ($linked['name'] ?? '?')) ?>
                    <?php endif; ?>
                  </span>
                </a>
                <a class="btn btn-ghost btn-sm" href="live.php?p=<?= $projectId ?>&page=<?= e((string) $item['slug']) ?>" target="_blank" rel="noopener">View</a>
              </li>
            <?php endforeach; ?>
          </ul>

          <form method="post" class="stack" style="max-width:520px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_page">
            <label class="field">
              <span>Add a page</span>
              <input type="text" name="title" placeholder="All Games" maxlength="60" required>
            </label>
            <label class="field">
              <span>This page shows</span>
              <select name="collection_id">
                <option value="0">Just words I write</option>
                <?php foreach ($collections as $collection): ?>
                  <option value="<?= (int) $collection['id'] ?>">A list of <?= e((string) $collection['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="btn btn-ghost" type="submit">Add page</button>
          </form>
        </div>
      </div>

      <!-- Edit panel ------------------------------------------------- -->
      <aside class="workspace-side">
        <div class="tabs"><button class="tab is-active" type="button"><?= $editing ? 'Edit page' : 'Site settings' ?></button></div>
        <div class="tab-panel is-active">

          <?php if ($editing): ?>
            <form method="post" class="stack">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_page">
              <input type="hidden" name="page_id" value="<?= (int) $editing['id'] ?>">

              <label class="field">
                <span>Page name</span>
                <input type="text" name="title" value="<?= e((string) $editing['title']) ?>" maxlength="60" required>
              </label>
              <label class="field">
                <span>Short intro</span>
                <textarea name="intro" rows="3" maxlength="400"><?= e((string) $editing['intro']) ?></textarea>
              </label>
              <label class="field">
                <span>Main text</span>
                <textarea name="body" rows="7" maxlength="6000"><?= e((string) $editing['body']) ?></textarea>
                <small class="hint">Leave a blank line between paragraphs.</small>
              </label>
              <label class="field">
                <span>Show a list on this page</span>
                <select name="collection_id">
                  <option value="0">No list</option>
                  <?php foreach ($collections as $collection): ?>
                    <option value="<?= (int) $collection['id'] ?>"
                      <?= (int) $editing['collection_id'] === (int) $collection['id'] ? 'selected' : '' ?>>
                      <?= e((string) $collection['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>

              <button class="btn btn-primary" type="submit">Save page</button>
              <a class="btn btn-ghost" href="build.php?id=<?= $projectId ?>">Done</a>
            </form>

            <?php if (!$editing['is_home']): ?>
              <form method="post" style="margin-top:1rem"
                    onsubmit="return confirm('Delete the &quot;<?= e((string) $editing['title']) ?>&quot; page?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_page">
                <input type="hidden" name="page_id" value="<?= (int) $editing['id'] ?>">
                <button class="btn btn-ghost btn-danger btn-sm" type="submit">Delete this page</button>
              </form>
            <?php endif; ?>

          <?php else: ?>
            <form method="post" class="stack">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="rename_site">
              <label class="field">
                <span>Site name</span>
                <input type="text" name="name" value="<?= e((string) $project['name']) ?>" maxlength="80" required>
              </label>
              <button class="btn btn-ghost" type="submit">Rename</button>
            </form>

            <p class="muted small" style="margin-top:1.5rem">
              Pick a page on the left to edit its words, or create a collection to start listing things.
            </p>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php layout_footer(); ?>
