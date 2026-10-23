<?php
require __DIR__ . '/lib.php';
$me = require_admin();

$tabs = ['overview' => 'Overview', 'users' => 'Users', 'sites' => 'Websites', 'settings' => 'Settings'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'overview';
$search = trim($_GET['q'] ?? '');

/** $ per million tokens (input, output), to estimate what the AI costs. */
const PRICES = ['claude-opus-5-5' => [4, 20], 'claude-sonnet-5-5' => [2, 10], 'claude-haiku-5-5' => [0.1, 0.5]];

function cost(int $in, int $out): string
{
    $p = PRICES[cfg('model')] ?? null;
    return $p ? '$' . number_format(($in * $p[0] + $out * $p[1]) / 1e6, 2) : '—';
}

function plural(int $n, string $word): string
{
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

function day(?string $utc): string
{
    return $utc ? date('j M Y', strtotime($utc . ' UTC')) : '';
}

// --- Actions: every change is a POST, then back to the same tab -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';
    if (in_array($do, ['make_admin', 'remove_admin', 'suspend', 'unsuspend', 'delete_user'], true) && $id === (int) $me['id']) {
        $notice = "You can't do that to your own account.";
    } else {
        $notice = match ($do) {
            'make_admin' => q('UPDATE users SET is_admin = 1 WHERE id = ?', [$id]) ? 'User is now an admin.' : '',
            'remove_admin' => q('UPDATE users SET is_admin = 0 WHERE id = ?', [$id]) ? 'Admin rights removed.' : '',
            'suspend' => q('UPDATE users SET suspended = 1 WHERE id = ?', [$id]) ? 'Account suspended: they are logged out and their websites are offline.' : '',
            'unsuspend' => q('UPDATE users SET suspended = 0 WHERE id = ?', [$id]) ? 'Account restored.' : '',
            'delete_user' => (function () use ($id) {
                foreach (q('SELECT id FROM sites WHERE user_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $siteId) {
                    delete_site_files((int) $siteId);
                }
                q('DELETE FROM users WHERE id = ?', [$id]);
                return 'Account and all its websites deleted.';
            })(),
            'publish' => q('UPDATE sites SET published = 1 WHERE id = ?', [$id]) ? 'Website published.' : '',
            'unpublish' => q('UPDATE sites SET published = 0 WHERE id = ?', [$id]) ? 'Website taken offline.' : '',
            'delete_site' => (function () use ($id) {
                delete_site_files($id);
                q('DELETE FROM sites WHERE id = ?', [$id]);
                return 'Website deleted.';
            })(),
            'save_settings' => (function () {
                $values = [
                    'signups' => empty($_POST['signups']) ? '0' : '1',
                    'ai_enabled' => empty($_POST['ai_enabled']) ? '0' : '1',
                    'ai_daily_limit' => (string) max(0, min(10000, (int) ($_POST['ai_daily_limit'] ?? 0))),
                ];
                foreach ($values as $k => $v) {
                    q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$k, $v]);
                }
                return 'Settings saved.';
            })(),
            default => '',
        };
    }
    $_SESSION['notice'] = $notice;
    redirect('admin.php?' . http_build_query(array_filter(['tab' => $tab, 'q' => $search])));
}
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

/** A one-button form for a row action. */
function action(string $do, int $id, string $label, string $class = 'btn-ghost', string $confirm = ''): string
{
    return '<form method="post" class="inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . '<input type="hidden" name="csrf" value="' . e(csrf()) . '"><input type="hidden" name="do" value="' . $do . '">'
        . '<input type="hidden" name="id" value="' . $id . '"><button class="btn btn-sm ' . $class . '">' . $label . '</button></form>';
}

$like = '%' . $search . '%';
head('Admin', 'page-admin');
?>
<main class="wrap dash admin">
  <div class="dash-head">
    <div><h1>Admin</h1><p class="muted">Everything happening on your SiteForge.</p></div>
  </div>
  <nav class="admin-tabs">
    <?php foreach ($tabs as $k => $label): ?><a href="admin.php?tab=<?= $k ?>" class="<?= $k === $tab ? 'on' : '' ?>"><?= $label ?></a><?php endforeach ?>
  </nav>
  <?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif ?>

<?php if ($tab === 'overview'):
    $n = fn (string $sql, array $a = []) => (int) q($sql, $a)->fetchColumn();
    $ai30 = q("SELECT COUNT(*) AS n, COALESCE(SUM(tokens_in), 0) AS tin, COALESCE(SUM(tokens_out), 0) AS tout FROM messages WHERE role = 'assistant' AND created_at >= datetime('now', '-30 days')")->fetch();
    $storage = array_sum(array_map('filesize', glob(__DIR__ . '/uploads/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: []));
    $stats = [
        ['Users', $n('SELECT COUNT(*) FROM users'), '+' . $n("SELECT COUNT(*) FROM users WHERE created_at >= datetime('now', '-7 days')") . ' this week'],
        ['Websites', $n('SELECT COUNT(*) FROM sites'), $n('SELECT COUNT(*) FROM sites WHERE published = 1') . ' published'],
        ['AI requests today', $n("SELECT COUNT(*) FROM messages WHERE role = 'assistant' AND created_at >= date('now')"), plural((int) $ai30['n'], 'request') . ' in 30 days'],
        ['AI cost, 30 days', cost((int) $ai30['tin'], (int) $ai30['tout']), number_format($ai30['tin'] + $ai30['tout']) . ' tokens (estimate)'],
        ['Form messages', $n('SELECT COUNT(*) FROM submissions'), '+' . $n("SELECT COUNT(*) FROM submissions WHERE created_at >= datetime('now', '-7 days')") . ' this week'],
        ['Image storage', number_format($storage / 1048576, 1) . ' MB', plural($n('SELECT COUNT(*) FROM assets'), 'upload')],
    ];
    $aiState = !cfg('anthropic_api_key') ? ['Not set up', 'Add an Anthropic API key in config.php to switch it on.', ''] : (setting('ai_enabled') === '1' ? ['On', 'Everyone can use the AI builder.', 'live'] : ['Paused', 'You paused it in Settings.', '']);
    $top = q("SELECT u.name, u.email, COUNT(*) AS n, SUM(m.tokens_in) AS tin, SUM(m.tokens_out) AS tout
              FROM messages m JOIN sites s ON s.id = m.site_id JOIN users u ON u.id = s.user_id
              WHERE m.role = 'assistant' AND m.created_at >= datetime('now', '-30 days')
              GROUP BY u.id ORDER BY n DESC LIMIT 5")->fetchAll();
    $recent = q('SELECT u.name, u.email, u.created_at, (SELECT COUNT(*) FROM sites WHERE user_id = u.id) AS sites FROM users u ORDER BY u.id DESC LIMIT 6')->fetchAll();
    ?>
  <div class="stat-grid">
    <?php foreach ($stats as [$label, $value, $sub]): ?>
      <div class="card stat"><span class="muted small"><?= e($label) ?></span><b><?= e((string) $value) ?></b><span class="muted small"><?= e($sub) ?></span></div>
    <?php endforeach ?>
  </div>
  <div class="admin-cols">
    <section class="card">
      <h2>AI builder</h2>
      <p><span class="badge <?= $aiState[2] ?>"><?= $aiState[0] ?></span> <?= e($aiState[1]) ?></p>
      <p class="muted small">Model <code><?= e(cfg('model')) ?></code>, effort <code><?= e(cfg('effort')) ?></code>. Daily limit per user: <?= setting('ai_daily_limit') === '0' ? 'none' : e(setting('ai_daily_limit')) . ' requests' ?> (admins have no limit).</p>
      <h3>Top AI users, last 30 days</h3>
      <?php if (!$top): ?><p class="muted small">No AI requests yet.</p><?php endif ?>
      <?php foreach ($top as $t): ?>
        <div class="row-line"><span><b><?= e($t['name']) ?></b> <span class="muted small"><?= e($t['email']) ?></span></span><span class="muted small"><?= plural((int) $t['n'], 'request') ?> · <?= cost((int) $t['tin'], (int) $t['tout']) ?></span></div>
      <?php endforeach ?>
    </section>
    <section class="card">
      <h2>Newest users</h2>
      <?php foreach ($recent as $r): ?>
        <div class="row-line"><span><b><?= e($r['name']) ?></b> <span class="muted small"><?= e($r['email']) ?></span></span><span class="muted small"><?= plural((int) $r['sites'], 'site') ?> · <?= day($r['created_at']) ?></span></div>
      <?php endforeach ?>
      <p><a href="admin.php?tab=users">All users →</a></p>
    </section>
  </div>

<?php elseif ($tab === 'users'):
    $users = q("SELECT u.*, (SELECT COUNT(*) FROM sites WHERE user_id = u.id) AS sites,
                  (SELECT COUNT(*) FROM messages m JOIN sites s ON s.id = m.site_id WHERE s.user_id = u.id AND m.role = 'assistant' AND m.created_at >= datetime('now', '-30 days')) AS ai
                FROM users u WHERE u.name LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT 300", [$like, $like])->fetchAll();
    ?>
  <form class="search"><input type="hidden" name="tab" value="users"><input name="q" value="<?= e($search) ?>" placeholder="Search by name or email"><button class="btn btn-sm">Search</button></form>
  <div class="card table-card">
    <table class="admin-table">
      <thead><tr><th>User</th><th>Joined</th><th>Websites</th><th>AI (30 days)</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
        <tr>
          <td><b><?= e($u['name']) ?></b><br><span class="muted small"><?= e($u['email']) ?></span></td>
          <td><?= day($u['created_at']) ?></td>
          <td><a href="admin.php?tab=sites&amp;q=<?= urlencode($u['email']) ?>"><?= (int) $u['sites'] ?></a></td>
          <td><?= (int) $u['ai'] ?></td>
          <td>
            <?php if ($u['is_admin']): ?><span class="badge live">Admin</span><?php endif ?>
            <?php if ($u['suspended']): ?><span class="badge bad">Suspended</span><?php elseif (!$u['is_admin']): ?><span class="badge">Active</span><?php endif ?>
          </td>
          <td class="actions">
            <?php if ($self): ?><span class="muted small">That’s you</span><?php else: ?>
              <?= $u['is_admin'] ? action('remove_admin', (int) $u['id'], 'Remove admin') : action('make_admin', (int) $u['id'], 'Make admin', 'btn-ghost', 'Give ' . $u['name'] . ' full admin access?') ?>
              <?= $u['suspended'] ? action('unsuspend', (int) $u['id'], 'Restore') : action('suspend', (int) $u['id'], 'Suspend', 'btn-ghost', 'Suspend ' . $u['name'] . '? They will be logged out and their websites go offline.') ?>
              <?= action('delete_user', (int) $u['id'], 'Delete', 'btn-danger', 'Delete ' . $u['name'] . ' and all their websites for good?') ?>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$users): ?><tr><td colspan="6" class="muted">No users found.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

<?php elseif ($tab === 'sites'):
    $sites = q("SELECT s.id, s.name, s.slug, s.published, s.updated_at, u.name AS owner, u.email, u.suspended,
                  COALESCE(json_array_length(s.export, '$.pages'), 0) AS pages,
                  (SELECT COUNT(*) FROM submissions WHERE site_id = s.id) AS inbox
                FROM sites s JOIN users u ON u.id = s.user_id
                WHERE s.name LIKE ? OR s.slug LIKE ? OR u.email LIKE ? ORDER BY s.updated_at DESC LIMIT 300", [$like, $like, $like])->fetchAll();
    ?>
  <form class="search"><input type="hidden" name="tab" value="sites"><input name="q" value="<?= e($search) ?>" placeholder="Search by website name or owner email"><button class="btn btn-sm">Search</button></form>
  <div class="card table-card">
    <table class="admin-table">
      <thead><tr><th>Website</th><th>Owner</th><th>Pages</th><th>Form messages</th><th>Last edited</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sites as $s): ?>
        <tr>
          <td><b><?= e($s['name']) ?></b><br><a class="small" href="site.php/<?= e($s['slug']) ?>/" target="_blank">site.php/<?= e($s['slug']) ?>/</a></td>
          <td><?= e($s['owner']) ?><br><span class="muted small"><?= e($s['email']) ?></span></td>
          <td><?= (int) $s['pages'] ?></td>
          <td><?= (int) $s['inbox'] ?></td>
          <td><?= day($s['updated_at']) ?></td>
          <td><?php if ($s['suspended']): ?><span class="badge bad">Owner suspended</span><?php else: ?><span class="badge <?= $s['published'] ? 'live' : '' ?>"><?= $s['published'] ? 'Live' : 'Draft' ?></span><?php endif ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-ghost" href="site.php/<?= e($s['slug']) ?>/" target="_blank">View</a>
            <?= $s['published'] ? action('unpublish', (int) $s['id'], 'Take offline', 'btn-ghost', 'Take “' . $s['name'] . '” offline?') : action('publish', (int) $s['id'], 'Publish') ?>
            <?= action('delete_site', (int) $s['id'], 'Delete', 'btn-danger', 'Delete “' . $s['name'] . '” and its images and inbox for good?') ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$sites): ?><tr><td colspan="7" class="muted">No websites found.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <form method="post" class="card settings">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="do" value="save_settings">
    <label class="check"><input type="checkbox" name="signups" value="1" <?= setting('signups') === '1' ? 'checked' : '' ?>><span><b>Allow new sign-ups</b><small>Turn off to stop new people creating accounts. Existing users can still log in.</small></span></label>
    <label class="check"><input type="checkbox" name="ai_enabled" value="1" <?= setting('ai_enabled') === '1' ? 'checked' : '' ?>><span><b>AI builder on</b><small>Turn off to pause the AI chat for everyone, for example to stop costs. Manual editing keeps working.</small></span></label>
    <label>AI requests per user per day<input type="number" name="ai_daily_limit" min="0" max="10000" value="<?= e(setting('ai_daily_limit')) ?>"><small class="muted">0 means no limit. Admins are never limited. Resets at midnight UTC.</small></label>
    <button class="btn">Save settings</button>
  </form>
  <div class="card settings">
    <h2>Server settings</h2>
    <p class="muted small">These live in <code>config.php</code> on the server.</p>
    <div class="row-line"><span>Anthropic API key</span><span><?= cfg('anthropic_api_key') ? '<span class="badge live">Set</span>' : '<span class="badge bad">Missing</span>' ?></span></div>
    <div class="row-line"><span>Model</span><code><?= e(cfg('model')) ?></code></div>
    <div class="row-line"><span>Effort</span><code><?= e(cfg('effort')) ?></code></div>
  </div>
<?php endif ?>
</main>
<script>
  document.addEventListener('submit', (e) => { const m = e.target.dataset.confirm; if (m && !confirm(m)) e.preventDefault(); });
</script>
<?php foot();
