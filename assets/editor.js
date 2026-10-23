// The builder: a GrapesJS canvas with our own panels around it, plus the AI chat that edits
// the same pages. Manual edits and AI edits are interchangeable because both read and write
// the same thing: pages of HTML and one shared stylesheet.
(function () {
  const SF = window.SF;
  const L = window.SF_LIB;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const slug = (s) => String(s).toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);

  async function api(action, data = {}, raw = false) {
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    fd.append('id', SF.site.id);
    const res = await fetch('api.php?action=' + action, { method: 'POST', headers: { 'X-CSRF': SF.csrf }, body: fd });
    if (raw && res.ok) return res;
    const json = await res.json().catch(() => ({ error: 'The server sent an unexpected answer.' }));
    if (!res.ok || json.error) throw new Error(json.error || 'Something went wrong.');
    return json;
  }

  function toast(text, isError) {
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' toast-error' : '');
    t.textContent = text;
    document.body.append(t);
    setTimeout(() => t.remove(), 4200);
  }

  // --- Editor -----------------------------------------------------------------

  const fallback = (f) => (['Playfair Display', 'Lora', 'Merriweather', 'Cormorant Garamond'].includes(f) ? 'serif' : ['Pacifico', 'Caveat'].includes(f) ? 'cursive' : 'sans-serif');
  const fontValue = (f) => `'${f}', ${fallback(f)}`;

  const SECTORS = [
    { name: 'Text', open: true, properties: [
      { property: 'font-family', type: 'select', default: '', options: [{ id: '', label: 'Same as page' }, { id: 'var(--font-heading)', label: 'Theme heading font' }, { id: 'var(--font-body)', label: 'Theme body font' }, ...SF.fonts.map((f) => ({ id: fontValue(f), label: f }))] },
      'font-size', 'font-weight', 'color', 'line-height', 'letter-spacing', 'text-align', 'text-decoration', 'font-style', 'text-shadow',
    ] },
    { name: 'Background', properties: ['background-color', 'background'] },
    { name: 'Size', properties: ['width', 'max-width', 'height', 'min-height'] },
    { name: 'Spacing', properties: ['padding', 'margin'] },
    { name: 'Border & corners', properties: ['border-radius', 'border', 'box-shadow'] },
    { name: 'Layout', properties: ['display', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items', { property: 'gap', type: 'number', units: ['px', 'rem', '%'], default: '0', min: 0 }, 'position', 'top', 'right', 'bottom', 'left', { property: 'z-index', type: 'number', default: 'auto' }] },
    { name: 'Effects', properties: ['opacity', 'transform', 'transition', 'cursor', 'overflow'] },
  ];

  // The browser's CSS parser drops shorthands that use variables (background: var(--bg)),
  // so CSS is parsed with PostCSS and kept exactly as written.
  const plugin = (name, opts) => { const p = window[name]; return p ? (ed) => (p.default || p)(ed, opts) : null; };
  const editor = grapesjs.init({
    container: '#gjs',
    height: '100%',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    noticeOnUnload: false,
    projectData: SF.project || { pages: [{ id: 'index', name: 'Home', component: '' }] },
    panels: { defaults: [] },
    canvas: { styles: [SF.fontsUrl] },
    parser: { optionsHtml: { allowScripts: true } },
    deviceManager: { devices: [
      { id: 'Desktop', name: 'Desktop', width: '' },
      { id: 'Tablet', name: 'Tablet', width: '820px', widthMedia: '992px' },
      { id: 'Mobile', name: 'Mobile', width: '390px', widthMedia: '480px' },
    ] },
    blockManager: { appendTo: '#blocks', blocks: L.blocks() },
    layerManager: { appendTo: '#layers' },
    traitManager: { appendTo: '#traits' },
    selectorManager: { componentFirst: true },
    styleManager: { appendTo: '#styles', sectors: SECTORS },
    assetManager: { upload: 'api.php?action=upload&id=' + SF.site.id, uploadName: 'files', headers: { 'X-CSRF': SF.csrf }, assets: SF.assets },
    plugins: [plugin('grapesjs-parser-postcss'), plugin('grapesjs-plugin-forms', { blocks: [] })].filter(Boolean),
  });
  window.sfEditor = editor;

  const Pages = editor.Pages;
  const currentPage = () => Pages.getSelected();

  /** Everything the server needs to publish the site, and the AI needs to edit it. */
  function exportSite() {
    return {
      css: editor.getCss({ keepUnusedStyles: true }),
      pages: Pages.getAll().map((p) => ({
        id: p.getId(),
        name: p.getName() || p.getId(),
        html: editor.getHtml({ component: p.getMainComponent() }).replace(/^\s*<body[^>]*>|<\/body>\s*$/g, ''),
      })),
    };
  }

  /** Sections rely on base styles and theme variables; add them if this site doesn't have them yet. */
  function ensureBase() {
    const has = (sel) => editor.Css.getAll().some((r) => !r.get('mediaText') && r.selectorsToString() === sel);
    if (!has(':root')) editor.Css.addRules(L.rootCss(L.PRESETS.modern.vars));
    if (!has('body')) editor.Css.addRules(L.BASE_CSS);
    else if (!has('.sf-container')) {
      // Keep the site's own typography; only add the section helpers.
      editor.Css.addRules(L.BASE_CSS.split('\n').filter((l) => l.startsWith('.sf-') || l.startsWith('@media')).join('\n'));
    }
  }

  function applyTemplate(key, presetKey) {
    const t = L.TEMPLATES[key];
    if (!t) return;
    const preset = L.PRESETS[presetKey] || L.PRESETS[t.preset];
    editor.Css.addRules(L.rootCss(preset.vars) + L.BASE_CSS);
    for (const p of L.templatePages(t)) {
      const page = Pages.get(p.id) || Pages.add({ id: p.id, name: p.name });
      page.setName(p.name);
      page.getMainComponent().components(p.html);
    }
    Pages.select('index');
  }

  // --- Saving -----------------------------------------------------------------

  let dirty = false;
  let saving = null;
  let timer = 0;
  const saveState = $('#save-state');
  const setState = (text, bad) => { saveState.textContent = text; saveState.classList.toggle('bad', !!bad); };

  function scheduleSave() {
    dirty = true;
    setState('Unsaved changes');
    clearTimeout(timer);
    timer = setTimeout(save, 1200);
  }

  async function save() {
    clearTimeout(timer);
    if (saving) await saving;
    if (!dirty) return;
    dirty = false;
    setState('Saving…');
    saving = api('save', { project: JSON.stringify(editor.getProjectData()), export: JSON.stringify(exportSite()) })
      .then(() => setState(dirty ? 'Unsaved changes' : 'All changes saved'))
      .catch((e) => { dirty = true; setState('Not saved', true); toast(e.message, true); })
      .finally(() => { saving = null; });
    return saving;
  }

  window.addEventListener('beforeunload', (e) => {
    if (dirty || saving) { e.preventDefault(); e.returnValue = ''; }
  });

  // --- Top bar ----------------------------------------------------------------

  $('#site-name').addEventListener('change', async (e) => {
    try { SF.site.name = (await api('rename', { name: e.target.value })).name; } catch (err) { toast(err.message, true); }
  });

  $$('[data-device]').forEach((b) => b.addEventListener('click', () => {
    editor.setDevice(b.dataset.device);
    $$('[data-device]').forEach((x) => x.classList.toggle('on', x === b));
  }));
  $('#undo').onclick = () => editor.UndoManager.undo();
  $('#redo').onclick = () => editor.UndoManager.redo();
  $('#outline').onclick = (e) => {
    const on = e.currentTarget.classList.toggle('on');
    on ? editor.runCommand('core:component-outline') : editor.stopCommand('core:component-outline');
  };
  $('#code').onclick = () => editor.runCommand('core:open-code');
  $('#preview').onclick = () => {
    document.body.classList.add('previewing');
    editor.runCommand('core:preview');
  };
  $('#exit-preview').onclick = () => {
    document.body.classList.remove('previewing');
    editor.stopCommand('core:preview');
  };

  $('#view-live').addEventListener('click', async (e) => {
    e.preventDefault();
    const w = window.open('about:blank', '_blank');
    await save();
    if (w) w.location = SF.liveUrl;
  });
  $('#download').addEventListener('click', async (e) => {
    e.preventDefault();
    await save();
    location.href = e.currentTarget.href;
  });

  const publishBtn = $('#publish');
  const showPublished = () => {
    publishBtn.textContent = SF.site.published ? 'Published ✓' : 'Publish';
    publishBtn.classList.toggle('btn-live', SF.site.published);
    publishBtn.title = SF.site.published ? 'Your site is live. Click to take it offline.' : 'Put your site online';
  };
  publishBtn.onclick = async () => {
    if (SF.site.published && !confirm('Take your website offline? Visitors will no longer see it.')) return;
    await save();
    try {
      SF.site.published = (await api('publish', { published: SF.site.published ? '' : '1' })).published;
      showPublished();
      if (SF.site.published) toast('Your website is live at ' + location.origin + SF.liveUrl);
    } catch (err) { toast(err.message, true); }
  };
  showPublished();

  // --- Left panel tabs --------------------------------------------------------

  function openTab(name) {
    $$('[data-tab]').forEach((b) => b.classList.toggle('on', b.dataset.tab === name));
    $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== name; });
    if (name === 'theme') renderTheme();
  }
  $$('[data-tab]').forEach((b) => b.addEventListener('click', () => openTab(b.dataset.tab)));
  $$('[data-go]').forEach((b) => b.addEventListener('click', () => openTab(b.dataset.go)));

  // --- Pages ------------------------------------------------------------------

  function renderPages() {
    const sel = currentPage();
    $('#pages').innerHTML = Pages.getAll().map((p) => `
      <div class="page-row${p === sel ? ' on' : ''}" data-page="${esc(p.getId())}">
        <button class="page-open"><b>${esc(p.getName() || p.getId())}</b><small>${esc(p.getId())}.html</small></button>
        <button class="icon-btn" data-rename title="Rename">✎</button>
        ${p.getId() === 'index' ? '' : '<button class="icon-btn" data-remove title="Delete page">🗑</button>'}
      </div>`).join('');
    $('#page-label').textContent = (sel && (sel.getName() || sel.getId())) || '';
  }

  $('#pages').addEventListener('click', (e) => {
    const row = e.target.closest('[data-page]');
    if (!row) return;
    const page = Pages.get(row.dataset.page);
    if (e.target.closest('[data-rename]')) {
      const name = prompt('Page name', page.getName());
      if (name) { page.setName(name.trim()); renderPages(); scheduleSave(); }
    } else if (e.target.closest('[data-remove]')) {
      if (!confirm(`Delete the page "${page.getName()}"? You can undo this with the Undo button.`)) return;
      if (page === currentPage()) Pages.select('index');
      Pages.remove(page);
      scheduleSave();
    } else {
      Pages.select(page);
    }
  });

  $('#add-page').onclick = () => {
    const name = (prompt('Name of the new page (for example: About, Menu, Gallery)') || '').trim();
    if (!name) return;
    let id = slug(name) || 'page';
    for (let n = 2; Pages.get(id); n++) id = `${slug(name) || 'page'}-${n}`;
    // New pages start with the home page's header and footer, so the site stays consistent.
    const home = Pages.get('index').getMainComponent().components();
    const first = home.at(0);
    const last = home.at(home.length - 1);
    const parts = [];
    if (first && /^(header|nav)$/.test(first.get('tagName'))) parts.push(first.toHTML());
    parts.push(`<section class="sf-section"><div class="sf-container"><h1>${esc(name)}</h1><p class="sf-lead">Start adding content to this page: drag sections from Add, or ask the AI.</p></div></section>`);
    if (last && last !== first && last.get('tagName') === 'footer') parts.push(last.toHTML());
    ensureBase();
    Pages.select(Pages.add({ id, name, component: parts.join('') }));
    scheduleSave();
    toast(`Page "${name}" added. Tip: ask the AI to add it to your menu.`);
  };


  // --- Theme ------------------------------------------------------------------

  const THEME = [
    ['Colours', [['--primary', 'Main colour'], ['--accent', 'Accent colour'], ['--on-primary', 'Text on buttons'], ['--bg', 'Page background'], ['--surface', 'Cards & alt sections'], ['--text', 'Text'], ['--muted', 'Soft text']], 'color'],
    ['Fonts', [['--font-heading', 'Headings'], ['--font-body', 'Body text']], 'font'],
    ['Shape & spacing', [['--radius', 'Corner roundness', 0, 40], ['--base-size', 'Text size', 13, 22], ['--space', 'Space between sections', 24, 180], ['--max-width', 'Content width', 760, 1600]], 'range'],
  ];
  const themeVars = () => ({ ...(editor.Css.getRule(':root')?.getStyle() || {}) });
  function setVars(vars) {
    editor.Css.setRule(':root', { ...themeVars(), ...vars });
    scheduleSave();
  }

  const ctx = document.createElement('canvas').getContext('2d');
  function toHex(color) {
    ctx.fillStyle = '#000000';
    ctx.fillStyle = (color || '').trim() || '#000000';
    const c = ctx.fillStyle;
    if (c.startsWith('#')) return c;
    const [r, g, b] = c.match(/[\d.]+/g).map(Number);
    return '#' + [r, g, b].map((x) => Math.round(x).toString(16).padStart(2, '0')).join('');
  }

  function renderTheme() {
    const v = themeVars();
    const has = Object.keys(v).some((k) => k.startsWith('--'));
    const firstFont = (val) => ((val || '').match(/['"]?([^'",]+)/) || [])[1]?.trim();
    $('#theme').innerHTML = `
      <h4>One-click styles</h4>
      <div class="presets">${Object.entries(L.PRESETS).map(([k, p]) => `
        <button data-preset="${k}" title="${p.label}">
          <i style="background:${p.vars['--bg']}"><b style="background:${p.vars['--primary']}"></b><b style="background:${p.vars['--accent']}"></b></i>
          <span style="font-family:${esc(p.vars['--font-heading'])}">${p.label}</span>
        </button>`).join('')}</div>
      ${has ? '' : '<p class="hint">This site has no theme settings yet. Pick a style above to add them.</p>'}
      ${THEME.map(([title, rows, kind]) => `<h4>${title}</h4>` + rows.map(([key, label, min, max]) => {
        if (kind === 'color') return `<label class="trow"><span>${label}</span><input type="color" data-var="${key}" value="${toHex(v[key])}"></label>`;
        if (kind === 'font') {
          const cur = firstFont(v[key]);
          return `<label class="trow"><span>${label}</span><select data-var="${key}">${SF.fonts.map((f) => `<option value="${esc(fontValue(f))}"${f === cur ? ' selected' : ''} style="font-family:'${f}'">${f}</option>`).join('')}</select></label>`;
        }
        const n = parseFloat(v[key]) || min;
        return `<label class="trow trow-range"><span>${label} <output>${n}px</output></span><input type="range" min="${min}" max="${max}" value="${n}" data-var="${key}" data-unit="px"></label>`;
      }).join('')).join('')}`;
  }

  $('#theme').addEventListener('input', (e) => {
    const el = e.target;
    if (!el.dataset.var) return;
    const value = el.value + (el.dataset.unit || '');
    if (el.type === 'range') el.previousElementSibling.querySelector('output').textContent = value;
    setVars({ [el.dataset.var]: value });
  });
  $('#theme').addEventListener('click', (e) => {
    const b = e.target.closest('[data-preset]');
    if (!b) return;
    setVars(L.PRESETS[b.dataset.preset].vars);
    ensureBase();
    renderTheme();
  });

  // --- Selection panel --------------------------------------------------------

  const NAMES = { h1: 'Heading', h2: 'Heading', h3: 'Heading', h4: 'Heading', p: 'Paragraph', a: 'Link', img: 'Image', section: 'Section', header: 'Header', footer: 'Footer', nav: 'Menu', button: 'Button', form: 'Form', input: 'Input field', textarea: 'Text box', ul: 'List', li: 'List item', article: 'Card' };
  const friendlyName = (c) => (c.getClasses().includes('sf-btn') ? 'Button' : NAMES[c.get('tagName')] || c.getName());

  function updateSelection() {
    const c = editor.getSelected();
    const isWrapper = !c || c === editor.getWrapper();
    $('#no-sel').hidden = !isWrapper;
    $('#sel-panel').hidden = isWrapper;
    $('#sel-name').textContent = isWrapper ? '' : friendlyName(c);
    $('#chat-focus').innerHTML = isWrapper ? '' : `AI will focus on the selected <b>${esc(friendlyName(c))}</b> <button type="button" title="Clear selection">×</button>`;
  }
  editor.on('component:selected component:deselected', updateSelection);
  $('#chat-focus').addEventListener('click', (e) => { if (e.target.closest('button')) editor.select(null); });
  $$('[data-st]').forEach((b) => b.addEventListener('click', () => {
    $$('[data-st]').forEach((x) => x.classList.toggle('on', x === b));
    $$('[data-sp]').forEach((p) => { p.hidden = p.dataset.sp !== b.dataset.st; });
  }));

  function updateEmpty() {
    const main = currentPage()?.getMainComponent();
    $('#empty').hidden = !main || main.components().length > 0;
  }

  // --- AI chat ----------------------------------------------------------------

  const log = $('#chat-log');
  const input = $('#chat-input');
  let attached = [];
  let busy = false;

  const SUGGEST_NEW = [
    'A website for my bakery with our menu, opening hours, a photo gallery and a contact form',
    'A portfolio for a wedding photographer, elegant and minimal',
    'A landing page for a fitness app with features, pricing and FAQ',
    'A site where I can list the newest video games and give each one a star rating',
  ];
  const SUGGEST_EDIT = ['Make it look more premium', 'Change the colours to green and gold', 'Add a testimonials section', 'Add an About page and link it in the menu', 'Make the site Arabic, right-to-left', 'Make it work great on phones'];

  function bubble(role, text = '') {
    const el = document.createElement('div');
    el.className = 'msg msg-' + role;
    el.innerHTML = `<div class="msg-text"></div>`;
    el.firstChild.textContent = text;
    log.append(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  function renderSuggestions() {
    $('#chat-suggest').innerHTML = !SF.ai ? '' : (Pages.getAll().some((p) => p.getMainComponent().components().length) ? SUGGEST_EDIT : SUGGEST_NEW)
      .map((s) => `<button type="button">${esc(s)}</button>`).join('');
  }
  $('#chat-suggest').addEventListener('click', (e) => {
    if (!e.target.matches('button')) return;
    input.value = e.target.textContent;
    input.focus();
  });

  function renderAttached() {
    $('#chat-attach').innerHTML = attached.map((u, i) => `<span><img src="${esc(u)}" alt=""><button type="button" data-i="${i}" title="Remove">×</button></span>`).join('');
  }
  $('#chat-attach').addEventListener('click', (e) => {
    const i = e.target.dataset.i;
    if (i !== undefined) { attached.splice(+i, 1); renderAttached(); }
  });
  $('#chat-file').addEventListener('change', async (e) => {
    const fd = new FormData();
    [...e.target.files].forEach((f) => fd.append('files[]', f));
    e.target.value = '';
    try {
      const { data } = await api('upload', fd);
      editor.AssetManager.add(data);
      attached.push(...data);
      renderAttached();
    } catch (err) { toast(err.message, true); }
  });

  /** Pull the tagged parts out of the AI's answer. */
  function parseAI(text) {
    const pick = (re) => (text.match(re) || [])[1];
    return {
      reply: (pick(/<reply>([\s\S]*?)<\/reply>/) || '').trim(),
      css: pick(/<css>([\s\S]*?)<\/css>/)?.trim() ?? null,
      pages: [...text.matchAll(/<page\s+id="([^"]+)"(?:\s+name="([^"]*)")?\s*>([\s\S]*?)<\/page>/g)]
        .map(([, id, name, html]) => ({ id: slug(id) || 'index', name: name || id, html: html.trim() })),
      remove: [...text.matchAll(/<remove\s+page="([^"]+)"\s*\/?>/g)].map((m) => m[1]),
    };
  }

  function applyAI(r) {
    if (r.css !== null) editor.setStyle(r.css);
    for (const p of r.pages) {
      const page = Pages.get(p.id) || Pages.add({ id: p.id, name: p.name });
      page.setName(p.name);
      page.getMainComponent().components(p.html);
    }
    for (const id of r.remove) {
      const page = Pages.get(id);
      if (!page || id === 'index') continue;
      if (page === currentPage()) Pages.select('index');
      Pages.remove(page);
    }
    if (r.pages.length && !r.pages.some((p) => p.id === currentPage().getId())) Pages.select(r.pages[0].id);
    renderPages();
    updateEmpty();
    renderSuggestions();
    if (!$('[data-panel="theme"]').hidden) renderTheme();
    scheduleSave();
  }

  function progressText(full) {
    const pages = [...full.matchAll(/<page\s+id="[^"]+"(?:\s+name="([^"]*)")?/g)];
    if (/<css>(?![\s\S]*<\/css>)/.test(full)) return 'Designing the styles…';
    if (pages.length && !/<\/page>\s*$/.test(full)) return `Building the “${pages.at(-1)[1] || 'new'}” page…`;
    if (/<\/reply>/.test(full)) return 'Working on it…';
    return 'Writing…';
  }

  async function send(text) {
    if (busy || !text.trim()) return;
    busy = true;
    $('#chat-send').disabled = true;
    $('#chat-suggest').innerHTML = '';
    const user = bubble('user', text);
    if (attached.length) user.insertAdjacentHTML('beforeend', `<div class="msg-imgs">${attached.map((u) => `<img src="${esc(u)}" alt="">`).join('')}</div>`);
    const ai = bubble('assistant');
    ai.classList.add('working');
    ai.insertAdjacentHTML('beforeend', '<div class="msg-status"><i class="spin"></i><span>Starting…</span></div>');
    const status = $('.msg-status span', ai);
    const replyEl = $('.msg-text', ai);

    const sel = editor.getSelected();
    const fd = new FormData();
    fd.append('message', text);
    fd.append('site', JSON.stringify(exportSite()));
    fd.append('images', JSON.stringify(attached));
    fd.append('focus', JSON.stringify({ page: currentPage().getId(), selected: sel && sel !== editor.getWrapper() ? sel.toHTML() : '' }));
    attached = [];
    renderAttached();

    let full = '';
    let finished = false;
    const fail = (msg) => { finished = true; ai.classList.add('msg-error'); replyEl.textContent = msg; };
    const handle = (ev) => {
      if (ev.t === 'status') status.textContent = ev.text;
      else if (ev.t === 'delta') {
        full += ev.text;
        const reply = (full.match(/<reply>([\s\S]*?)(?:<\/reply>|$)/) || [])[1];
        if (reply) replyEl.textContent = reply.trim();
        status.textContent = progressText(full);
      } else if (ev.t === 'error') fail(ev.message);
      else if (ev.t === 'done') {
        finished = true;
        const r = parseAI(ev.text);
        replyEl.textContent = r.reply || ev.reply || 'Done!';
        if (r.css !== null || r.pages.length || r.remove.length) {
          const before = editor.getProjectData();
          try { applyAI(r); } catch (err) { console.error(err); fail('I wrote the changes but they could not be applied. Please try again.'); return; }
          const undo = document.createElement('button');
          undo.className = 'msg-undo';
          undo.textContent = '↶ Undo this change';
          undo.onclick = () => { editor.loadProjectData(before); renderPages(); updateEmpty(); scheduleSave(); undo.remove(); };
          ai.append(undo);
        }
      }
      log.scrollTop = log.scrollHeight;
    };

    try {
      const res = await api('chat', fd, true);
      const reader = res.body.getReader();
      const dec = new TextDecoder();
      let buf = '';
      for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buf += dec.decode(value, { stream: true });
        let i;
        while ((i = buf.indexOf('\n\n')) >= 0) {
          const chunk = buf.slice(0, i);
          buf = buf.slice(i + 2);
          chunk.split('\n').filter((l) => l.startsWith('data: ')).forEach((l) => handle(JSON.parse(l.slice(6))));
        }
      }
      if (!finished) fail('The connection dropped before the AI finished. Please send your message again.');
    } catch (err) {
      fail(err.message || 'Could not reach the server.');
    } finally {
      ai.classList.remove('working');
      $('.msg-status', ai)?.remove();
      busy = false;
      $('#chat-send').disabled = false;
      log.scrollTop = log.scrollHeight;
    }
  }

  $('#chat-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value;
    input.value = '';
    send(text);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); $('#chat-form').requestSubmit(); }
  });

  // --- Start ------------------------------------------------------------------

  editor.on('load', () => {
    const params = new URLSearchParams(location.search);
    if (!SF.project && params.get('template')) {
      applyTemplate(params.get('template'), params.get('preset'));
      dirty = true;
      save();
    }
    history.replaceState(null, '', 'editor.php?id=' + SF.site.id);

    if (!SF.messages.length) bubble('assistant', SF.ai
      ? 'Hi! Tell me what website you want, or what to change, and I’ll build it right here. You can also click anything on the page and edit it yourself.'
      : 'The AI assistant is not switched on for this SiteForge yet, but you can build everything by hand: drag sections from “Add”, click anything to edit it, and use “Theme” to restyle the whole site.');
    SF.messages.forEach((m) => bubble(m.role, m.body));
    if (!SF.ai) { input.disabled = true; input.placeholder = 'AI is turned off. Add an API key in config.php.'; $('#chat-send').disabled = true; }

    renderPages();
    renderSuggestions();
    updateSelection();
    updateEmpty();
    editor.on('page:add page:remove page:select page:update', () => { renderPages(); updateEmpty(); });
    editor.on('component:add component:remove', () => setTimeout(updateEmpty));
    editor.on('block:drag:stop', (c) => { if (c) { ensureBase(); scheduleSave(); } });
    editor.on('update', scheduleSave);
    setState('All changes saved');

    const first = sessionStorage.getItem('sf-prompt-' + SF.site.id);
    if (first && SF.ai) {
      sessionStorage.removeItem('sf-prompt-' + SF.site.id);
      send(first);
    }
  });
})();
