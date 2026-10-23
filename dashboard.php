<?php
require __DIR__ . '/lib.php';
$u = require_user();

// --- Inbox: answers sent through a site's forms ---------------------------------
if (isset($_GET['inbox'])) {
    $site = own_site((int) $_GET['inbox']);
    $subs = q('SELECT * FROM submissions WHERE site_id = ? ORDER BY id DESC LIMIT 500', [$site['id']])->fetchAll();
    head('Inbox: ' . $site['name']);
    ?>
<main class="wrap dash">
  <p><a href="dashboard.php">← My websites</a></p>
  <div class="dash-head"><div><h1>Inbox</h1><p class="muted">Messages sent through the forms on <b><?= e($site['name']) ?></b>.</p></div></div>
  <?php if (!$subs): ?>
    <div class="card empty-card"><p>No messages yet. When visitors fill in a form on your published site, it shows up here.</p></div>
  <?php endif ?>
  <?php foreach ($subs as $s): ?>
    <article class="card sub">
      <header><b><?= e(date('j M Y, H:i', strtotime($s['created_at'] . ' UTC'))) ?></b><span class="muted small">from <?= e($s['page']) ?>.html</span></header>
      <dl><?php foreach (json_decode($s['data'], true) as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= nl2br(e($v)) ?></dd><?php endforeach ?></dl>
    </article>
  <?php endforeach ?>
</main>
<?php
    foot();
    exit;
}

$sites = q('SELECT s.id, s.name, s.slug, s.published, s.updated_at, s.export IS NOT NULL AS built,
              (SELECT COUNT(*) FROM submissions WHERE site_id = s.id) AS inbox
            FROM sites s WHERE user_id = ? ORDER BY s.updated_at DESC', [$u['id']])->fetchAll();
$ai = (bool) cfg('anthropic_api_key');

head('My websites', 'page-dash');
?>
<main class="wrap dash">
  <div class="dash-head">
    <div><h1>Hi <?= e(strtok($u['name'], ' ')) ?>, your websites</h1><p class="muted">Build with AI, by hand, or mix both whenever you like.</p></div>
    <button class="btn" data-new>+ New website</button>
  </div>

  <?php if (!$sites): ?>
    <div class="start-grid">
      <button class="start-card" data-new="ai"><span>✨</span><b>Describe it to the AI</b><p>Say what you want in your own words. The AI builds the whole site, then keeps changing it whenever you ask.</p></button>
      <button class="start-card" data-new="manual"><span>🎨</span><b>Design it yourself</b><p>Pick a template and a style, then click, drag and restyle anything you like.</p></button>
    </div>
  <?php else: ?>
    <div class="sites">
      <?php foreach ($sites as $s): $url = 'site.php/' . $s['slug'] . '/'; ?>
        <article class="site-card" data-site="<?= (int) $s['id'] ?>">
          <a class="thumb" href="editor.php?id=<?= (int) $s['id'] ?>" aria-label="Edit <?= e($s['name']) ?>">
            <?php if ($s['built']): ?><iframe src="<?= e($url) ?>" loading="lazy" tabindex="-1" title=""></iframe>
            <?php else: ?><span class="thumb-empty">Not built yet</span><?php endif ?>
          </a>
          <div class="site-info">
            <div class="site-title"><h3><?= e($s['name']) ?></h3><span class="badge <?= $s['published'] ? 'live' : '' ?>"><?= $s['published'] ? 'Live' : 'Draft' ?></span></div>
            <p class="muted small">Edited <?= e(date('j M Y', strtotime($s['updated_at'] . ' UTC'))) ?></p>
            <div class="site-actions">
              <a class="btn btn-sm" href="editor.php?id=<?= (int) $s['id'] ?>">Edit</a>
              <a class="btn btn-ghost btn-sm" href="<?= e($url) ?>" target="_blank">View</a>
              <a class="btn btn-ghost btn-sm" href="dashboard.php?inbox=<?= (int) $s['id'] ?>">Inbox<?= $s['inbox'] ? ' (' . (int) $s['inbox'] . ')' : '' ?></a>
              <button class="icon-btn" data-delete title="Delete website">🗑</button>
            </div>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</main>

<dialog id="new-site" class="modal">
  <form id="new-form">
    <header class="modal-head"><h2>Create a new website</h2><button type="button" class="icon-btn" data-close aria-label="Close">✕</button></header>
    <label>Website name<input name="name" required maxlength="60" placeholder="e.g. Sara’s Bakery"></label>
    <div class="mode-tabs" role="tablist">
      <button type="button" data-mode="ai" class="on">✨ Build it with AI</button>
      <button type="button" data-mode="manual">🎨 Design it myself</button>
    </div>
    <section data-pane="ai">
      <?php if (!$ai): ?><p class="alert">The AI assistant isn’t switched on for this SiteForge yet (it needs an API key in config.php). You can still design your site yourself.</p><?php endif ?>
      <label>What should your website be?<textarea name="prompt" rows="5" placeholder="Describe it in your own words: what it’s for, what pages and sections you want, the style and colours you like…"></textarea></label>
      <div class="chips" id="examples">
        <button type="button">A website for my bakery with our menu, opening hours and a photo gallery. Warm and cosy.</button>
        <button type="button">A site where I can list the newest video games and give each one a star rating, dark and modern.</button>
        <button type="button">A portfolio for a wedding photographer, elegant black and white.</button>
        <button type="button">موقع لمطعم شاورما باللغة العربية مع قائمة الطعام وأوقات العمل وزر للطلب عبر واتساب</button>
      </div>
      <p class="muted small">Tip: you can attach your logo and photos in the chat once the editor opens.</p>
    </section>
    <section data-pane="manual" hidden>
      <h3>1. Pick a starting point</h3>
      <div class="tpl-grid" id="templates"></div>
      <h3>2. Pick a style <span class="muted small">(you can change it any time)</span></h3>
      <div class="preset-row" id="presets"></div>
    </section>
    <footer class="modal-foot"><button class="btn" id="create-btn">Create website</button></footer>
  </form>
</dialog>

<script>window.SF = <?= json_encode(['csrf' => csrf(), 'ai' => $ai, 'fontsUrl' => fonts_url()], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="assets/blocks.js"></script>
<script>
(function () {
  const L = window.SF_LIB, $ = (s) => document.querySelector(s), $$ = (s) => [...document.querySelectorAll(s)];
  const dlg = $('#new-site'), form = $('#new-form');
  let mode = SF.ai ? 'ai' : 'manual', tpl = 'business', preset = '';

  async function api(action, data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch('api.php?action=' + action, { method: 'POST', headers: { 'X-CSRF': SF.csrf }, body: fd });
    const json = await res.json().catch(() => ({ error: 'Unexpected server answer.' }));
    if (!res.ok || json.error) throw new Error(json.error || 'Something went wrong.');
    return json;
  }

  function setMode(m) {
    mode = m;
    $$('[data-mode]').forEach((b) => b.classList.toggle('on', b.dataset.mode === m));
    $$('[data-pane]').forEach((p) => { p.hidden = p.dataset.pane !== m; });
    form.prompt.required = m === 'ai' && SF.ai;
    if (m === 'manual' && !$('#templates').children.length) renderTemplates();
  }

  function renderTemplates() {
    $('#templates').innerHTML = Object.entries(L.TEMPLATES).map(([k, t]) => `
      <button type="button" class="tpl${k === tpl ? ' on' : ''}" data-tpl="${k}">
        <span class="tpl-thumb"><iframe tabindex="-1" loading="lazy" title=""></iframe></span>
        <b>${t.name}</b><small>${t.desc}</small>
      </button>`).join('');
    $$('[data-tpl]').forEach((b) => { b.querySelector('iframe').srcdoc = L.templateDoc(L.TEMPLATES[b.dataset.tpl], SF.fontsUrl); });
    $('#presets').innerHTML = `<button type="button" class="preset on" data-preset="">Template’s own</button>` + Object.entries(L.PRESETS).map(([k, p]) => `
      <button type="button" class="preset" data-preset="${k}"><i style="background:${p.vars['--primary']}"></i><i style="background:${p.vars['--accent']}"></i><i style="background:${p.vars['--bg']}"></i>${p.label}</button>`).join('');
  }

  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-new]');
    if (t) { setMode(t.dataset.new || mode); dlg.showModal(); form.name.focus(); }
    if (e.target.closest('[data-close]')) dlg.close();
    const m = e.target.closest('[data-mode]');
    if (m) setMode(m.dataset.mode);
    const tp = e.target.closest('[data-tpl]');
    if (tp) { tpl = tp.dataset.tpl; $$('[data-tpl]').forEach((b) => b.classList.toggle('on', b === tp)); }
    const pr = e.target.closest('[data-preset]');
    if (pr) { preset = pr.dataset.preset; $$('[data-preset]').forEach((b) => b.classList.toggle('on', b === pr)); }
    if (e.target.closest('#examples button')) { form.prompt.value = e.target.textContent; form.prompt.focus(); }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('#create-btn');
    btn.disabled = true;
    btn.textContent = 'Creating…';
    try {
      const { id } = await api('create', { name: form.name.value });
      if (mode === 'ai' && form.prompt.value.trim()) sessionStorage.setItem('sf-prompt-' + id, form.prompt.value.trim());
      location.href = 'editor.php?id=' + id + (mode === 'manual' ? `&template=${tpl}&preset=${preset}` : '');
    } catch (err) {
      alert(err.message);
      btn.disabled = false;
      btn.textContent = 'Create website';
    }
  });

  $$('[data-delete]').forEach((b) => b.addEventListener('click', async () => {
    const card = b.closest('[data-site]');
    if (!confirm(`Delete "${card.querySelector('h3').textContent}"? This removes the site, its images and its inbox for good.`)) return;
    try { await api('delete', { id: card.dataset.site }); card.remove(); } catch (err) { alert(err.message); }
  }));

  setMode(mode);
})();
</script>
<?php foot();
