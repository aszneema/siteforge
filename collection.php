<?php
declare(strict_types=1);

/**
 * One collection: what details it has, and the things in it.
 * This is where "add a game and give it a rating" actually happens.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/builder.php';

$user = require_login();

$collectionId = isset($_GET['c']) ? (int) $_GET['c'] : 0;
$collection = collection_get($collectionId);
$project = $collection ? project_get((int) $collection['project_id'], (int) $user['id']) : null;

if (!$collection || !$project) {
    http_response_code(404);
    layout_header(['title' => 'Not found']);
    echo '<section class="section"><div class="shell empty"><h1>Collection not found</h1>'
       . '<a class="btn btn-primary" href="library.php">Back to the library</a></div></section>';
    layout_footer();
    exit;
}

$projectId = (int) $project['id'];
$notice = '';
$problem = '';
$formErrors = [];
$old = [];

/**
 * Store an uploaded image and return its asset id.
 * Trusts the file's real contents, never its name or the browser's type.
 */
function entry_store_image(array $file, int $userId, int $projectId): ?int
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('That image did not upload properly. Try again.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Images need to be 5 MB or smaller.');
    }

    $allowed = [
        IMAGETYPE_PNG  => ['png',  'image/png'],
        IMAGETYPE_JPEG => ['jpg',  'image/jpeg'],
        IMAGETYPE_GIF  => ['gif',  'image/gif'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info[2]])) {
        throw new RuntimeException('That file is not a PNG, JPEG, GIF or WebP image.');
    }
    [$ext, $mime] = $allowed[$info[2]];

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = config('storage_path') . '/uploads/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Could not save that image.');
    }
    @chmod($target, 0644);

    $name = slugify(pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_FILENAME), 'image') . '.' . $ext;
    $stmt = db()->prepare(
        'INSERT INTO assets (user_id, project_id, filename, stored, mime, size) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $projectId, $name, $stored, $mime, (int) filesize($target)]);

    return (int) db()->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'add_field':
                $label = trim((string) ($_POST['label'] ?? ''));
                if ($label === '') {
                    throw new RuntimeException('Give the detail a name — "Title", "Rating", "Cover".');
                }
                field_create(
                    $collectionId,
                    $label,
                    (string) ($_POST['type'] ?? 'text'),
                    (string) ($_POST['options'] ?? ''),
                    !empty($_POST['required'])
                );
                $notice = 'Added "' . $label . '".';
                break;

            case 'delete_field':
                field_delete((int) ($_POST['field_id'] ?? 0), $collectionId);
                $notice = 'Removed that detail.';
                break;

            case 'move_field':
                field_move((int) ($_POST['field_id'] ?? 0), $collectionId, (int) ($_POST['dir'] ?? 1));
                break;

            case 'save_settings':
                collection_update(
                    $collectionId,
                    (string) ($_POST['name'] ?? $collection['name']),
                    (string) ($_POST['singular'] ?? ''),
                    (string) ($_POST['sort_field'] ?? ''),
                    (string) ($_POST['sort_dir'] ?? 'desc')
                );
                $collection = collection_get($collectionId);
                $notice = 'Saved.';
                break;

            case 'delete_collection':
                collection_delete($collectionId);
                redirect('build.php?id=' . $projectId);

            case 'save_entry':
                $fields = fields_for($collectionId);
                $uploads = [];
                foreach ($fields as $field) {
                    if ($field['type'] === 'image' && isset($_FILES['f_' . $field['key']])) {
                        $id = entry_store_image($_FILES['f_' . $field['key']], (int) $user['id'], $projectId);
                        if ($id !== null) {
                            $uploads[$field['key']] = $id;
                        }
                    }
                }

                $input = [];
                foreach ($fields as $field) {
                    $input[$field['key']] = $_POST['v_' . $field['key']] ?? '';
                }

                [$data, $formErrors] = entry_clean($fields, $input, $uploads);
                $entryId = (int) ($_POST['entry_id'] ?? 0);

                if ($formErrors !== []) {
                    $old = $input;
                    break;
                }
                if ($entryId > 0) {
                    entry_update($entryId, $collectionId, $data);
                    $notice = 'Saved your changes.';
                } else {
                    entry_create($collectionId, $data);
                    $notice = 'Added.';
                }
                break;

            case 'delete_entry':
                entry_delete((int) ($_POST['entry_id'] ?? 0), $collectionId);
                $notice = 'Deleted.';
                break;
        }
    } catch (Throwable $e) {
        $problem = $e->getMessage();
    }
}

$fields = fields_for($collectionId);
$entries = entries_for($collectionId, $collection);
$isNew = isset($_GET['new']);

$editingEntry = null;
if (isset($_GET['entry'])) {
    $editingEntry = entry_get((int) $_GET['entry'], $collectionId);
    if ($editingEntry && $old === []) {
        $old = $editingEntry['values'];
    }
}

$singular = (string) ($collection['singular'] ?: rtrim((string) $collection['name'], 's'));

layout_header(['title' => (string) $collection['name'], 'active' => 'library']);
?>

<section class="workspace">
  <div class="shell">

    <header class="workspace-head">
      <div class="workspace-title">
        <a class="back-link" href="build.php?id=<?= $projectId ?>">&larr; <?= e((string) $project['name']) ?></a>
        <h1><?= e((string) $collection['name']) ?></h1>
        <div class="badges">
          <span class="badge"><?= count($fields) ?> detail<?= count($fields) === 1 ? '' : 's' ?></span>
          <span class="badge badge-soft"><?= count($entries) ?> saved</span>
        </div>
      </div>
      <div class="workspace-actions">
        <a class="btn btn-ghost btn-sm" href="live.php?p=<?= $projectId ?>" target="_blank" rel="noopener">View live site</a>
      </div>
    </header>

    <?php if ($notice !== ''): ?><div class="alert alert-ok"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($problem !== ''): ?><div class="alert alert-bad"><?= e($problem) ?></div><?php endif; ?>

    <?php if ($isNew && $fields === []): ?>
      <div class="alert alert-ok">
        <strong>Next: say what details each <?= e(mb_strtolower($singular)) ?> has.</strong><br>
        For games that might be Title, Cover, Rating and Released. Add them below and the
        form for adding games builds itself.
      </div>
    <?php endif; ?>

    <div class="workspace-grid">

      <!-- Entries ---------------------------------------------------- -->
      <div class="workspace-main">

        <?php if ($fields === []): ?>
          <div class="panel">
            <div class="empty">
              <h2>Add the details first</h2>
              <p class="muted">Once this collection has details, the add form appears here.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="panel">
            <div class="panel-head">
              <h2><?= $editingEntry ? 'Edit' : 'Add' ?> <?= e(mb_strtolower($singular)) ?></h2>
              <?php if ($editingEntry): ?>
                <a class="btn btn-ghost btn-sm" href="collection.php?c=<?= $collectionId ?>">Cancel</a>
              <?php endif; ?>
            </div>

            <?php if ($formErrors): ?>
              <div class="alert alert-bad">
                <?php foreach ($formErrors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="stack">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_entry">
              <?php if ($editingEntry): ?>
                <input type="hidden" name="entry_id" value="<?= (int) $editingEntry['id'] ?>">
              <?php endif; ?>

              <?php foreach ($fields as $field):
                  $key = (string) $field['key'];
                  $value = $old[$key] ?? '';
                  $req = !empty($field['required']) ? 'required' : ''; ?>
                <label class="field">
                  <span><?= e((string) $field['label']) ?><?= $req ? ' *' : '' ?></span>

                  <?php switch ($field['type']):
                      case 'longtext': ?>
                      <textarea name="v_<?= e($key) ?>" rows="4" maxlength="4000" <?= $req ?>><?= e((string) $value) ?></textarea>
                      <?php break; ?>

                      <?php case 'rating': ?>
                      <select name="v_<?= e($key) ?>" <?= $req ?>>
                        <option value="">Not rated</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                          <option value="<?= $i ?>" <?= (int) $value === $i ? 'selected' : '' ?>>
                            <?= str_repeat('★', $i) ?> &nbsp;<?= $i ?> out of 5
                          </option>
                        <?php endfor; ?>
                      </select>
                      <?php break; ?>

                      <?php case 'choice':
                        $choices = array_filter(array_map('trim', explode(',', (string) $field['options']))); ?>
                      <select name="v_<?= e($key) ?>" <?= $req ?>>
                        <option value="">Choose…</option>
                        <?php foreach ($choices as $choice): ?>
                          <option value="<?= e($choice) ?>" <?= (string) $value === $choice ? 'selected' : '' ?>><?= e($choice) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php break; ?>

                      <?php case 'image': ?>
                      <input type="file" name="f_<?= e($key) ?>" accept="image/png,image/jpeg,image/gif,image/webp">
                      <?php if ($value): ?>
                        <input type="hidden" name="v_<?= e($key) ?>" value="<?= (int) $value ?>">
                        <img src="live.php?p=<?= $projectId ?>&img=<?= (int) $value ?>" alt=""
                             style="height:70px;border-radius:8px;margin-top:.5rem">
                        <small class="hint">Choose a new file to replace it.</small>
                      <?php endif; ?>
                      <?php break; ?>

                      <?php case 'checkbox': ?>
                      <input type="checkbox" name="v_<?= e($key) ?>" value="1" <?= $value ? 'checked' : '' ?>
                             style="width:auto;justify-self:start">
                      <?php break; ?>

                      <?php default:
                        $inputType = match ($field['type']) {
                            'number' => 'number', 'date' => 'date', 'url' => 'url', default => 'text',
                        }; ?>
                      <input type="<?= $inputType ?>" name="v_<?= e($key) ?>"
                             value="<?= e((string) $value) ?>"
                             <?= $field['type'] === 'number' ? 'step="any"' : 'maxlength="300"' ?> <?= $req ?>>
                  <?php endswitch; ?>
                </label>
              <?php endforeach; ?>

              <button class="btn btn-primary" type="submit">
                <?= $editingEntry ? 'Save changes' : 'Add ' . e(mb_strtolower($singular)) ?>
              </button>
            </form>
          </div>
        <?php endif; ?>

        <?php if ($entries): ?>
          <div class="panel">
            <div class="panel-head"><h2>Saved <?= e(mb_strtolower((string) $collection['name'])) ?></h2></div>
            <ul class="file-list">
              <?php foreach ($entries as $row): ?>
                <li style="display:flex;align-items:center;gap:.5rem">
                  <span style="flex:1">
                    <span class="file-name"><?= e(entry_title($fields, $row['values'])) ?></span>
                    <span class="file-size">
                      <?php foreach ($fields as $field):
                          $v = $row['values'][$field['key']] ?? null;
                          if ($v === null || $v === '' || $field['type'] === 'longtext') continue;
                          if ($field['type'] === 'rating') { echo str_repeat('★', (int) $v) . ' '; continue; }
                          if ($field['type'] === 'image') continue;
                          echo e(mb_substr((string) (is_bool($v) ? ($v ? 'yes' : 'no') : $v), 0, 22)) . ' &middot; ';
                      endforeach; ?>
                    </span>
                  </span>
                  <a class="btn btn-ghost btn-sm" href="collection.php?c=<?= $collectionId ?>&entry=<?= (int) $row['id'] ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this?')" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_entry">
                    <input type="hidden" name="entry_id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-ghost btn-sm btn-danger" type="submit">Delete</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>

      <!-- Fields ----------------------------------------------------- -->
      <aside class="workspace-side">
        <div class="tabs"><button class="tab is-active" type="button">Details &amp; settings</button></div>
        <div class="tab-panel is-active">

          <?php if ($fields): ?>
            <ul class="file-list" style="margin-bottom:1.25rem">
              <?php foreach ($fields as $i => $field): ?>
                <li style="display:flex;align-items:center;gap:.35rem">
                  <span style="flex:1">
                    <span class="file-name"><?= e((string) $field['label']) ?><?= $field['required'] ? ' *' : '' ?></span>
                    <span class="file-size"><?= e(field_type_label((string) $field['type'])) ?></span>
                  </span>
                  <?php foreach ([-1 => '↑', 1 => '↓'] as $dir => $arrow):
                      $can = $dir < 0 ? $i > 0 : $i < count($fields) - 1;
                      if (!$can) continue; ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="move_field">
                      <input type="hidden" name="field_id" value="<?= (int) $field['id'] ?>">
                      <input type="hidden" name="dir" value="<?= $dir ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" aria-label="Move <?= $dir < 0 ? 'up' : 'down' ?>"><?= $arrow ?></button>
                    </form>
                  <?php endforeach; ?>
                  <form method="post" onsubmit="return confirm('Remove &quot;<?= e((string) $field['label']) ?>&quot;? Values already saved stay in the database but stop showing.')" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_field">
                    <input type="hidden" name="field_id" value="<?= (int) $field['id'] ?>">
                    <button class="btn btn-ghost btn-sm btn-danger" type="submit" aria-label="Remove">&times;</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_field">
            <label class="field">
              <span>Add a detail</span>
              <input type="text" name="label" placeholder="Rating" maxlength="60" required>
            </label>
            <label class="field">
              <span>What kind?</span>
              <select name="type" id="fieldType">
                <?php foreach (field_types() as $key => $type): ?>
                  <option value="<?= e($key) ?>"><?= e($type['label']) ?> — <?= e($type['hint']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span>Choices <span class="muted">(only for "Choice")</span></span>
              <input type="text" name="options" placeholder="PC, PlayStation, Xbox, Switch" maxlength="300">
            </label>
            <label style="display:flex;gap:.5rem;align-items:center;font-size:.92rem">
              <input type="checkbox" name="required" value="1" style="width:auto"> Must be filled in
            </label>
            <button class="btn btn-ghost" type="submit">Add detail</button>
          </form>

          <hr style="margin:1.5rem 0;border:0;border-top:1px solid var(--border)">

          <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_settings">
            <label class="field">
              <span>Collection name</span>
              <input type="text" name="name" value="<?= e((string) $collection['name']) ?>" maxlength="60" required>
            </label>
            <label class="field">
              <span>One of them is called</span>
              <input type="text" name="singular" value="<?= e($singular) ?>" maxlength="60" placeholder="Game">
            </label>
            <label class="field">
              <span>Order the list by</span>
              <select name="sort_field">
                <option value="">Newest first</option>
                <?php foreach ($fields as $field): ?>
                  <option value="<?= e((string) $field['key']) ?>"
                    <?= (string) $collection['sort_field'] === (string) $field['key'] ? 'selected' : '' ?>>
                    <?= e((string) $field['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span>Direction</span>
              <select name="sort_dir">
                <option value="desc" <?= $collection['sort_dir'] === 'desc' ? 'selected' : '' ?>>Highest / newest first</option>
                <option value="asc"  <?= $collection['sort_dir'] === 'asc'  ? 'selected' : '' ?>>Lowest / oldest first</option>
              </select>
            </label>
            <button class="btn btn-ghost" type="submit">Save settings</button>
          </form>

          <form method="post" style="margin-top:1rem"
                onsubmit="return confirm('Delete &quot;<?= e((string) $collection['name']) ?>&quot; and everything in it? This cannot be undone.')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_collection">
            <button class="btn btn-ghost btn-danger btn-sm" type="submit">Delete this collection</button>
          </form>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php layout_footer(); ?>
