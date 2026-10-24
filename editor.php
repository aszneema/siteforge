<?php
require __DIR__ . '/lib.php';
require_user();
$site = own_site((int) ($_GET['id'] ?? 0));

$boot = [
    'site' => ['id' => (int) $site['id'], 'name' => $site['name'], 'published' => (bool) $site['published']],
    'project' => $site['project'] ? json_decode($site['project']) : null,
    'messages' => q('SELECT role, body FROM messages WHERE site_id = ? ORDER BY id', [$site['id']])->fetchAll(),
    'assets' => array_map(fn ($f) => base() . '/uploads/' . $f, q('SELECT file FROM assets WHERE site_id = ? ORDER BY id DESC', [$site['id']])->fetchAll(PDO::FETCH_COLUMN)),
    'csrf' => csrf(),
    'fonts' => array_keys(FONTS),
    'fontsUrl' => fonts_url(),
    'ai' => ai_ready(),
    'liveUrl' => base() . '/site.php/' . $site['slug'] . '/',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($site['name']) ?> · SiteForge editor</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://unpkg.com/grapesjs@0.23.6/dist/css/grapes.min.css">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="editor">
<div class="ed">
  <header class="ed-top">
    <a class="ed-back" href="dashboard.php" title="Back to my websites"><?= brand_mark() ?></a>
    <input id="site-name" class="ed-name" value="<?= e($site['name']) ?>" aria-label="Website name" maxlength="60">
    <span class="ed-page">Page: <b id="page-label"></b></span>
    <div class="ed-group" role="group" aria-label="Screen size">
      <button data-device="Desktop" class="on" title="Desktop"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></button>
      <button data-device="Tablet" title="Tablet"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/></svg></button>
      <button data-device="Mobile" title="Phone"><svg viewBox="0 0 24 24"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/></svg></button>
    </div>
    <div class="ed-group">
      <button id="undo" title="Undo (Ctrl+Z)"><svg viewBox="0 0 24 24"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg></button>
      <button id="redo" title="Redo (Ctrl+Shift+Z)"><svg viewBox="0 0 24 24"><path d="m15 14 5-5-5-5"/><path d="M20 9H9a5 5 0 0 0 0 10h3"/></svg></button>
      <button id="outline" title="Show element outlines"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="1" stroke-dasharray="3 3"/></svg></button>
      <button id="code" title="View the code"><svg viewBox="0 0 24 24"><path d="m8 7-5 5 5 5M16 7l5 5-5 5"/></svg></button>
      <button id="preview" title="Preview"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
      <button id="help" title="Help and tour"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5V14M12 17.5v.01"/></svg></button>
    </div>
    <span id="save-state" class="save-state">Loading…</span>
    <a id="view-live" class="btn btn-ghost btn-sm" href="<?= e($boot['liveUrl']) ?>">View site</a>
    <a id="download" class="btn btn-ghost btn-sm" href="api.php?action=export&amp;id=<?= (int) $site['id'] ?>" title="Download as HTML files">Download</a>
    <button id="publish" class="btn btn-sm">Publish</button>
  </header>

  <aside class="ed-left">
    <nav class="ed-tabs">
      <button data-tab="chat" class="on"><span>✨</span>AI</button>
      <button data-tab="add"><span>＋</span>Add</button>
      <button data-tab="pages"><span>📄</span>Pages</button>
      <button data-tab="layers"><span>☰</span>Layers</button>
      <button data-tab="theme"><span>🎨</span>Theme</button>
    </nav>
    <div class="ed-panel">
      <section data-panel="chat" class="chat">
        <div id="chat-log" class="chat-log" aria-live="polite"></div>
        <div id="chat-suggest" class="chat-suggest"></div>
        <form id="chat-form" class="chat-box">
          <div id="chat-attach" class="chat-attach"></div>
          <div id="chat-focus" class="chat-focus"></div>
          <textarea id="chat-input" rows="3" placeholder="Describe what you want, e.g. “add a pricing section” or “make the buttons rounder”"></textarea>
          <div class="chat-row">
            <label class="icon-btn" title="Attach images (logo, photos, a screenshot to copy)"><input id="chat-file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden>📎</label>
            <span class="muted small">Enter to send</span>
            <button id="chat-send" class="btn btn-sm">Send</button>
          </div>
        </form>
      </section>
      <section data-panel="add" hidden>
        <input id="block-search" class="block-search" type="search" placeholder="Search sections, e.g. pricing, gallery, contact" aria-label="Search sections">
        <p class="hint">Drag a section or element onto the page.</p>
        <div id="blocks"></div>
      </section>
      <section data-panel="pages" hidden>
        <p class="hint">Click a page to edit it.</p>
        <div id="pages"></div>
        <button id="add-page" class="btn btn-sm btn-block">+ Add page</button>
      </section>
      <section data-panel="layers" hidden>
        <p class="hint">Everything on this page. Drag to reorder.</p>
        <div id="layers"></div>
      </section>
      <section data-panel="theme" hidden>
        <p class="hint">Change the look of the whole site at once.</p>
        <div id="theme"></div>
      </section>
    </div>
  </aside>

  <main class="ed-canvas">
    <div id="gjs"></div>
    <div id="empty" class="ed-empty" hidden>
      <h2>This page is empty</h2>
      <p>Ask the AI to build it, or drag in ready-made sections.</p>
      <div><button class="btn btn-sm" data-go="chat">✨ Ask the AI</button> <button class="btn btn-ghost btn-sm" data-go="add">＋ Add sections</button></div>
    </div>
    <button id="exit-preview" class="btn exit-preview">Exit preview</button>
  </main>

  <aside class="ed-right">
    <div id="no-sel" class="no-sel">
      <div class="no-sel-icon">👆</div>
      <b>Click anything on the page to edit it</b>
      <p>Double-click text to type. Drag an element to move it.</p>
      <div class="quick-start">
        <button data-go="add"><span>＋</span>Add a section</button>
        <button data-go="theme"><span>🎨</span>Change colours &amp; fonts</button>
        <button data-go="pages"><span>📄</span>Add or edit pages</button>
        <button id="qs-tour"><span>🧭</span>Take the 1-minute tour</button>
      </div>
    </div>
    <div id="sel-panel" hidden>
      <div class="sel-head"><span id="sel-path" class="sel-path"></span><b id="sel-name"></b></div>
      <div class="sel-tabs"><button data-st="design" class="on">Design</button><button data-st="style">Advanced</button><button data-st="settings">Settings</button></div>
      <div data-sp="design"><div id="design"></div></div>
      <div data-sp="style" hidden><p class="hint">Every CSS option, for fine control.</p><div id="styles"></div></div>
      <div data-sp="settings" hidden><p class="hint">Links, image text, form fields and more.</p><div id="traits"></div></div>
    </div>
  </aside>
</div>
<dialog id="page-dialog" class="modal modal-sm">
  <form method="dialog" id="page-form">
    <header class="modal-head"><h2>Page settings</h2><button type="button" class="icon-btn" data-close aria-label="Close">✕</button></header>
    <label>Page name<input name="name" required maxlength="60"></label>
    <p class="muted small" id="page-url"></p>
    <h3>How it appears on Google</h3>
    <label>Title<input name="title" maxlength="70" placeholder="Shown in the browser tab and in search results"></label>
    <label>Description<textarea name="description" rows="3" maxlength="170" placeholder="One or two sentences about this page"></textarea></label>
    <div class="seo-preview"><b id="seo-title"></b><span id="seo-url"></span><p id="seo-desc"></p></div>
    <footer class="modal-foot"><button class="btn" value="save">Save</button></footer>
  </form>
</dialog>
<dialog id="help-dialog" class="modal modal-sm">
  <div class="help">
    <header class="modal-head"><h2>Help</h2><button type="button" class="icon-btn" data-close aria-label="Close">✕</button></header>
    <ul class="help-list">
      <li><b>Edit anything:</b> click it on the page. Double-click text to type.</li>
      <li><b>Change how it looks:</b> use the Design tab on the right. It only shows options that fit what you clicked.</li>
      <li><b>Add content:</b> open Add and drag a section onto the page.</li>
      <li><b>Restyle everything:</b> Theme changes colours, fonts and spacing for the whole site.</li>
      <li><b>Phones and tablets:</b> switch screen size at the top. Changes made there only apply to that size.</li>
      <li><b>Mistakes:</b> Undo fixes anything. Everything saves automatically.</li>
    </ul>
    <h3>Keyboard shortcuts</h3>
    <dl class="keys"><dt>Ctrl/⌘ + Z</dt><dd>Undo</dd><dt>Ctrl/⌘ + Shift + Z</dt><dd>Redo</dd><dt>Ctrl/⌘ + C, V</dt><dd>Copy and paste the selected element</dd><dt>Delete</dt><dd>Remove the selected element</dd><dt>Esc</dt><dd>Exit preview</dd></dl>
    <footer class="modal-foot"><button class="btn" id="help-tour">Take the tour</button></footer>
  </div>
</dialog>
<div class="small-screen">The editor needs a bigger screen. Open SiteForge on a computer or tablet to edit your site.</div>

<script>window.SF = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://unpkg.com/grapesjs@0.23.6/dist/grapes.min.js"></script>
<script src="https://unpkg.com/grapesjs-plugin-forms@2.0.6/dist/index.js"></script>
<script src="https://unpkg.com/grapesjs-parser-postcss@1.0.3/dist/index.js"></script>
<script src="assets/blocks.js"></script>
<script src="assets/editor.js"></script>
<script src="assets/design.js"></script>
</body>
</html>
