// The Design tab: plain-language controls for whatever is selected, showing only the
// options that fit it (text, link, button, image, section...). Changes go through the
// Style Manager, so they land on the selected element for the current screen size and
// can be undone like any other edit.
(function () {
  const editor = window.sfEditor;
  const SF = window.SF;
  const { esc, ensureCss, friendlyName, fontValue, toHex } = window.SFE;
  const sm = editor.StyleManager;
  const um = editor.UndoManager;
  const $ = (s, el = document) => el.querySelector(s);
  const box = $('#design');
  const closed = new Set(['Size', 'Effects', 'Show on']); // groups that start folded

  const SWATCHES = [['var(--primary)', 'Main colour'], ['var(--accent)', 'Accent colour'], ['var(--text)', 'Text colour'], ['var(--muted)', 'Soft text'], ['var(--bg)', 'Page background'], ['var(--surface)', 'Cards colour'], ['#ffffff', 'White'], ['#000000', 'Black']];
  const SHADOWS = { None: 'none', Soft: '0 4px 14px rgba(15,23,42,.08)', Medium: '0 12px 30px -8px rgba(15,23,42,.25)', Strong: '0 30px 60px -15px rgba(15,23,42,.45)' };
  const GRADIENTS = { 'Theme colours': 'linear-gradient(135deg,var(--primary),var(--accent))', 'Soft glow': 'radial-gradient(60% 80% at 20% 0%,color-mix(in srgb,var(--primary) 18%,transparent),transparent 70%)', Night: 'linear-gradient(180deg,#0f172a,#1e293b)' };
  const COLUMNS = { Auto: 'repeat(auto-fit,minmax(250px,1fr))', 1: '1fr', 2: 'repeat(auto-fit,minmax(min(100%,420px),1fr))', 3: 'repeat(auto-fit,minmax(min(100%,300px),1fr))', 4: 'repeat(auto-fit,minmax(min(100%,230px),1fr))' };
  const DARKEN = 'linear-gradient(rgba(0,0,0,.5),rgba(0,0,0,.5))';
  const TEXT_TAGS = /^(h[1-6]|p|span|a|li|blockquote|label|button|strong|em|small|figcaption|summary|b|i|td|th|dt|dd)$/;
  const BOX_TAGS = /^(section|div|header|footer|main|nav|article|aside|form|ul|ol|figure)$/;
  const ICON = (d) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${d}"/></svg>`;
  const ALIGN = { left: 'M4 6h16M4 10h10M4 14h16M4 18h10', center: 'M4 6h16M7 10h10M4 14h16M7 18h10', right: 'M4 6h16M10 10h10M4 14h16M10 18h10', justify: 'M4 6h16M4 10h16M4 14h16M4 18h16' };

  // --- Small HTML builders -------------------------------------------------------
  const data = (o) => esc(JSON.stringify(o));
  const opt = (label, style, on, title = '') => `<button type="button" data-style="${data(style)}" class="${on ? 'on' : ''}" title="${esc(title)}">${label}</button>`;
  const copt = (label, op, on, title = '') => `<button type="button" data-cls="${data(op)}" class="${on ? 'on' : ''}" title="${esc(title)}">${label}</button>`;
  const seg = (buttons) => `<div class="seg">${buttons.join('')}</div>`;
  const row = (label, inner) => `<div class="d-row"><span class="d-label">${label}</span>${inner}</div>`;
  const slider = (label, props, value, min, max, step = 1, unit = 'px', scale = 1) =>
    `<label class="d-row"><span class="d-label">${label} <output>${+(value * (scale === 1 ? 1 : 100)).toFixed(2)}${scale === 1 ? unit : '%'}</output></span><input type="range" min="${min}" max="${max}" step="${step}" value="${value / scale}" data-props="${props}" data-unit="${unit}" data-scale="${scale}"></label>`;
  const group = (title, body) => `<details class="d-group" data-g="${esc(title)}"${closed.has(title) ? '' : ' open'}><summary>${title}</summary>${body}</details>`;
  const px = (v) => Math.round(parseFloat(v) || 0);

  function swatches(prop, current, computed) {
    const vars = editor.Css.getRule(':root')?.getStyle() || {};
    const shown = (v) => (v.startsWith('var(') ? vars[v.slice(4, -1)] || '#cbd5e1' : v);
    return `<div class="swatches">${SWATCHES.map(([v, name]) => `<button type="button" title="${name}" data-style="${data({ [prop]: v })}" class="sw${current === v ? ' on' : ''}" style="background:${esc(shown(v))}"></button>`).join('')}
      <label class="sw sw-custom" title="Pick any colour"><input type="color" data-props="${prop}" data-unit="" value="${toHex(computed)}"></label>
      <button type="button" class="sw sw-none${current ? '' : ' on'}" title="No colour of its own" data-style="${data({ [prop]: '' })}"></button></div>`;
  }

  // --- Groups ---------------------------------------------------------------------
  function textGroup(own, cs) {
    const fonts = [['', 'Same as page'], ['var(--font-heading)', 'Theme heading font'], ['var(--font-body)', 'Theme body font'], ...SF.fonts.map((f) => [fontValue(f), f])];
    const weight = [400, 500, 700, 800].reduce((a, b) => (Math.abs(b - (parseInt(cs.fontWeight, 10) || 400)) < Math.abs(a - (parseInt(cs.fontWeight, 10) || 400)) ? b : a));
    const lh = (parseFloat(cs.lineHeight) / parseFloat(cs.fontSize)) || 1.5;
    return group('Text', [
      row('Font', `<select data-props="font-family">${fonts.map(([v, l]) => `<option value="${esc(v)}"${(own['font-family'] || '') === v ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>`),
      slider('Size', 'font-size', px(cs.fontSize), 10, 96),
      row('Weight', seg([['Regular', 400], ['Medium', 500], ['Bold', 700], ['Heavy', 800]].map(([l, w]) => opt(`<span style="font-weight:${w}">Aa</span>`, { 'font-weight': String(w) }, weight === w, l)))),
      row('Style', seg([
        opt('<i>I</i>', { 'font-style': cs.fontStyle === 'italic' ? 'normal' : 'italic' }, cs.fontStyle === 'italic', 'Italic'),
        opt('<u>U</u>', { 'text-decoration': cs.textDecorationLine.includes('underline') ? 'none' : 'underline' }, cs.textDecorationLine.includes('underline'), 'Underline'),
        opt('AA', { 'text-transform': cs.textTransform === 'uppercase' ? 'none' : 'uppercase' }, cs.textTransform === 'uppercase', 'Capital letters'),
      ])),
      row('Align', seg(Object.entries(ALIGN).map(([a, d]) => opt(ICON(d), { 'text-align': a }, cs.textAlign === a || (a === 'left' && cs.textAlign === 'start'), 'Align ' + a)))),
      row('Colour', swatches('color', own.color, cs.color)),
      slider('Line spacing', 'line-height', +lh.toFixed(2), 1, 2.4, 0.05, ''),
      slider('Letter spacing', 'letter-spacing', +(parseFloat(cs.letterSpacing) || 0).toFixed(1), -2, 10, 0.5),
    ].join(''));
  }

  function linkGroup(c) {
    const href = c.getAttributes().href || '';
    const pages = editor.Pages.getAll().map((p) => [`${p.getId()}.html`, p.getName() || p.getId()]);
    const anchors = [...editor.Canvas.getDocument().querySelectorAll('section[id],header[id],footer[id],div[id],article[id]')]
      .map((e) => e.id).filter((id) => !/^i[a-z0-9]{2,6}(-\d+)?$/i.test(id));
    const kind = href.startsWith('mailto:') ? 'email' : href.startsWith('tel:') ? 'phone' : href.includes('wa.me/') ? 'whatsapp'
      : pages.some(([p]) => p === href) ? 'page:' + href : /^#.+/.test(href) ? 'section:' + href : /^https?:/.test(href) ? 'url' : '';
    const value = { email: href.slice(7), phone: href.slice(4), whatsapp: href.split('wa.me/')[1] || '', url: href }[kind] ?? '';
    const placeholder = { email: 'name@example.com', phone: '+971 50 123 4567', whatsapp: '971501234567', url: 'https://…' }[kind];
    const o = (v, l) => `<option value="${esc(v)}"${kind === v ? ' selected' : ''}>${esc(l)}</option>`;
    return group('Link', [
      row('Goes to', `<select data-link>${o('', 'Nowhere (no link)')}
        <optgroup label="Pages">${pages.map(([p, n]) => o('page:' + p, n)).join('')}</optgroup>
        ${anchors.length ? `<optgroup label="Sections on this page">${anchors.map((a) => o('section:#' + a, '#' + a)).join('')}</optgroup>` : ''}
        <optgroup label="Other">${o('url', 'A web address…')}${o('email', 'Send an email…')}${o('phone', 'Phone call…')}${o('whatsapp', 'WhatsApp chat…')}</optgroup></select>`),
      placeholder ? `<input class="d-input" data-linkinput="${kind}" value="${esc(value)}" placeholder="${placeholder}">` : '',
      `<label class="d-check"><input type="checkbox" data-newtab${c.getAttributes().target === '_blank' ? ' checked' : ''}> Open in a new tab</label>`,
    ].join(''));
  }

  function buttonGroup(classes) {
    const has = (x) => classes.includes(x);
    const style = has('sf-btn-ghost') ? 'ghost' : has('sf-btn-soft') ? 'soft' : 'filled';
    const look = ['sf-btn-ghost', 'sf-btn-soft'];
    const size = ['sf-btn-sm', 'sf-btn-lg'];
    return group('Button', [
      row('Look', seg([
        copt('Filled', { group: look }, style === 'filled'),
        copt('Outline', { group: look, add: 'sf-btn-ghost' }, style === 'ghost'),
        copt('Soft', { group: look, add: 'sf-btn-soft', ensure: 'btn' }, style === 'soft'),
      ])),
      row('Size', seg([
        copt('Small', { group: size, add: 'sf-btn-sm', ensure: 'btn' }, has('sf-btn-sm')),
        copt('Medium', { group: size }, !has('sf-btn-sm') && !has('sf-btn-lg')),
        copt('Large', { group: size, add: 'sf-btn-lg', ensure: 'btn' }, has('sf-btn-lg')),
      ])),
      `<label class="d-check"><input type="checkbox" data-toggle="sf-btn-block" data-ensure="btn"${has('sf-btn-block') ? ' checked' : ''}> Full width</label>`,
    ].join(''));
  }

  function imageGroup(c, own) {
    const ratio = own['aspect-ratio'] || '';
    return group('Image', [
      `<div class="d-buttons"><button type="button" class="btn btn-sm" data-act="replace-image">Replace image</button></div>`,
      row('Description', `<input class="d-input" data-attr="alt" value="${esc(c.getAttributes().alt || '')}" placeholder="What the picture shows (helps Google and blind visitors)">`),
      row('Shape', seg([['Original', ''], ['Square', '1 / 1'], ['Landscape', '4 / 3'], ['Wide', '16 / 9'], ['Portrait', '3 / 4']].map(([l, r]) =>
        opt(l, r ? { 'aspect-ratio': r, 'object-fit': 'cover', height: 'auto' } : { 'aspect-ratio': '', 'object-fit': '' }, ratio.replace(/\s/g, '') === r.replace(/\s/g, ''))))),
      row('Fit', seg([opt('Fill the shape', { 'object-fit': 'cover' }, own['object-fit'] !== 'contain'), opt('Show whole image', { 'object-fit': 'contain' }, own['object-fit'] === 'contain')])),
    ].join(''));
  }

  function layoutGroup(c, own, cs, k) {
    const parts = [row('Align content', seg(['left', 'center', 'right'].map((a) => opt(ICON(ALIGN[a]), { 'text-align': a }, cs.textAlign === a || (a === 'left' && cs.textAlign === 'start'), 'Align ' + a))))];
    if (cs.display === 'grid') {
      parts.push(row('Items per row', seg(Object.entries(COLUMNS).map(([l, v]) => opt(l, { 'grid-template-columns': v }, own['grid-template-columns'] === v)))));
    }
    if (/grid|flex/.test(cs.display)) parts.push(slider('Space between items', 'gap', px(cs.rowGap), 0, 80));
    if (k.section) {
      const tall = (v) => ({ 'min-height': v, display: 'flex', 'flex-direction': 'column', 'justify-content': 'center' });
      parts.push(row('Height', seg([
        opt('Auto', { 'min-height': '', display: '', 'flex-direction': '', 'justify-content': '' }, !own['min-height']),
        opt('Tall', tall('70vh'), own['min-height'] === '70vh'),
        opt('Full screen', tall('100vh'), own['min-height'] === '100vh'),
      ])));
    }
    return group('Layout', parts.join(''));
  }

  function backgroundGroup(own, cs) {
    const img = own['background-image'] || '';
    const url = (img.match(/url\(["']?([^"')]+)/) || [])[1];
    const dark = img.includes(DARKEN);
    return group('Background', [
      row('Colour', swatches('background-color', own['background-color'], cs.backgroundColor)),
      row('Gradient', seg(Object.entries(GRADIENTS).map(([l, g]) => opt(l, { 'background-image': g }, img === g)))),
      `<div class="d-buttons"><button type="button" class="btn btn-sm btn-ghost" data-act="bg-image">${url ? 'Change' : 'Add'} background photo</button>${img ? `<button type="button" class="btn btn-sm btn-ghost" data-style="${data({ 'background-image': '' })}">Remove</button>` : ''}</div>`,
      url ? `<label class="d-check"><input type="checkbox" data-darken="${esc(url)}"${dark ? ' checked' : ''}> Darken the photo so text is easy to read</label>` : '',
    ].join(''));
  }

  function spacingGroup(cs) {
    return group('Spacing', [
      slider('Inside, top & bottom', 'padding-top,padding-bottom', px(cs.paddingTop), 0, 200),
      slider('Inside, left & right', 'padding-left,padding-right', px(cs.paddingLeft), 0, 120),
      slider('Outside, top & bottom', 'margin-top,margin-bottom', px(cs.marginTop), 0, 160),
    ].join(''));
  }

  function shapeGroup(own, cs) {
    const thin = '1px solid color-mix(in srgb,var(--text) 18%,transparent)';
    const thick = '3px solid var(--primary)';
    return group('Corners, border & shadow', [
      slider('Rounded corners', 'border-radius', px(cs.borderTopLeftRadius), 0, 60),
      row('Border', seg([opt('None', { border: 'none' }, !own.border || own.border === 'none'), opt('Thin', { border: thin }, own.border === thin), opt('Thick', { border: thick }, own.border === thick)])),
      row('Shadow', seg(Object.entries(SHADOWS).map(([l, v]) => opt(l, { 'box-shadow': v }, (own['box-shadow'] || 'none') === v)))),
    ].join(''));
  }

  function sizeGroup(own) {
    return group('Size', row('Width', seg([['Auto', ''], ['¼', '25%'], ['½', '50%'], ['¾', '75%'], ['Full', '100%']].map(([l, w]) => opt(l, { width: w }, (own.width || '') === w, w || 'Natural width')))));
  }

  function effectsGroup(classes, cs) {
    const anim = ['sf-anim-fade', 'sf-anim-up', 'sf-anim-zoom'];
    return group('Effects', [
      row('Appear on scroll', seg([['None', ''], ['Fade', 'sf-anim-fade'], ['Slide up', 'sf-anim-up'], ['Zoom', 'sf-anim-zoom']].map(([l, a]) =>
        copt(l, { group: anim, add: a, ensure: 'anim' }, a ? classes.includes(a) : !anim.some((x) => classes.includes(x)))))),
      '<p class="d-hint">Plays on the live site when visitors scroll to it.</p>',
      slider('See-through', 'opacity', +(parseFloat(cs.opacity) || 1).toFixed(2), 10, 100, 1, '', 0.01),
    ].join(''));
  }

  function showGroup(classes) {
    const t = (cls, label) => `<label class="d-check"><input type="checkbox" data-toggle="${cls}" data-ensure="hide" data-invert${classes.includes(cls) ? '' : ' checked'}> ${label}</label>`;
    return group('Show on', t('sf-hide-phone', 'Phones') + t('sf-hide-tablet', 'Tablets') + t('sf-hide-desktop', 'Computers')
      + '<p class="d-hint">Hidden things can still be found and selected in Layers.</p>');
  }

  // --- Render -------------------------------------------------------------------
  let path = [];

  function render() {
    const c = editor.getSelected();
    if (!c || c === editor.getWrapper() || $('[data-sp="design"]').hidden) return;
    const el = c.getEl();
    const cs = el ? el.ownerDocument.defaultView.getComputedStyle(el) : getComputedStyle(document.createElement('div'));
    const own = sm.getSelected()?.getStyle() || {};
    const tag = c.get('tagName');
    const classes = c.getClasses();
    const k = {
      text: TEXT_TAGS.test(tag) || c.is('text'),
      link: tag === 'a',
      button: classes.includes('sf-btn'),
      image: c.is('image') || tag === 'img',
      box: BOX_TAGS.test(tag),
      section: /^(section|header|footer)$/.test(tag),
    };
    const device = editor.getDevice();

    path = [];
    for (let p = c; p && p !== editor.getWrapper(); p = p.parent()) path.unshift(p);
    $('#sel-path').innerHTML = path.slice(0, -1).map((p, i) => `<button type="button" data-pick="${i}">${esc(friendlyName(p))}</button> › `).join('');

    box.innerHTML = [
      `<div class="d-actions">
        <button type="button" data-act="duplicate" title="Duplicate">⧉<span>Copy</span></button>
        <button type="button" data-act="up" title="Move up">↑<span>Up</span></button>
        <button type="button" data-act="down" title="Move down">↓<span>Down</span></button>
        <button type="button" data-act="parent" title="Select what this is inside"${path.length > 1 ? '' : ' disabled'}>⤴<span>Outer</span></button>
        <button type="button" data-act="delete" class="danger" title="Delete">🗑<span>Delete</span></button>
      </div>`,
      device !== 'Desktop' ? `<p class="d-note">You’re editing the ${device === 'Mobile' ? 'phone' : 'tablet'} view: changes here only apply to ${device === 'Mobile' ? 'phones' : 'tablets'}.</p>` : '',
      k.link ? linkGroup(c) : '',
      k.button ? buttonGroup(classes) : '',
      k.image ? imageGroup(c, own) : '',
      k.text ? textGroup(own, cs) : '',
      k.box ? layoutGroup(c, own, cs, k) : '',
      k.box ? backgroundGroup(own, cs) : '',
      k.image ? '' : spacingGroup(cs),
      shapeGroup(own, cs),
      sizeGroup(own),
      effectsGroup(classes, cs),
      showGroup(classes),
    ].join('');
  }
  const later = () => requestAnimationFrame(render);

  // --- Applying changes ------------------------------------------------------------
  const skip = (fn) => (um.skip ? um.skip(fn) : fn());
  const set = (style) => sm.addStyleTargets(style);

  function classOp(c, { group: g = [], add, ensure }) {
    g.forEach((x) => c.removeClass(x));
    if (add) c.addClass(add);
    if (ensure) ensureCss(ensure);
  }

  function pickImage(onPick) {
    const am = editor.AssetManager;
    am.open({ select(asset) { onPick(asset.getSrc()); am.close(); } });
  }

  box.addEventListener('click', (e) => {
    const c = editor.getSelected();
    const b = e.target.closest('button');
    if (!c || !b) return;
    if (b.dataset.style) { set(JSON.parse(b.dataset.style)); later(); }
    if (b.dataset.cls) { classOp(c, JSON.parse(b.dataset.cls)); later(); }
    const parent = c.parent();
    switch (b.dataset.act) {
      case 'duplicate': { const copy = c.clone(); parent.components().add(copy, { at: c.index() + 1 }); editor.select(copy); break; }
      case 'up': if (c.index() > 0) { c.move(parent, { at: c.index() - 1 }); later(); } break;
      case 'down': if (c.index() < parent.components().length - 1) { c.move(parent, { at: c.index() + 2 }); later(); } break;
      case 'parent': editor.select(parent); break;
      case 'delete': c.remove(); editor.select(null); break;
      case 'replace-image': pickImage((src) => { c.addAttributes({ src }); later(); }); break;
      case 'bg-image': pickImage((src) => { set({ 'background-image': `url("${src}")`, 'background-size': 'cover', 'background-position': 'center' }); later(); }); break;
    }
  });

  // Sliders and colour pickers preview live while dragging, then record one undo step.
  let before = null;
  const readOwn = (props) => Object.fromEntries(props.map((p) => [p, (sm.getSelected()?.getStyle() || {})[p] || '']));
  const styleFrom = (el) => {
    const v = el.type === 'range' ? `${+(el.value * (+el.dataset.scale || 1)).toFixed(3)}${el.dataset.unit}` : el.value;
    return Object.fromEntries(el.dataset.props.split(',').map((p) => [p, v]));
  };
  box.addEventListener('input', (e) => {
    const el = e.target;
    if (!el.dataset.props || el.tagName === 'SELECT') return;
    before ??= readOwn(el.dataset.props.split(','));
    skip(() => set(styleFrom(el)));
    const out = el.type === 'range' && el.closest('.d-row')?.querySelector('output');
    if (out) out.textContent = el.dataset.scale !== '1' ? Math.round(el.value) + '%' : el.value + el.dataset.unit;
  });

  box.addEventListener('change', (e) => {
    const c = editor.getSelected();
    const el = e.target;
    if (!c) return;
    if (el.dataset.props) {
      const style = el.tagName === 'SELECT' ? { [el.dataset.props]: el.value } : styleFrom(el);
      if (before) skip(() => set(before));
      before = null;
      set(style);
      if (el.type === 'color' || el.tagName === 'SELECT') later();
    } else if (el.dataset.attr) {
      c.addAttributes({ [el.dataset.attr]: el.value.trim() });
    } else if (el.matches('[data-link]')) {
      const v = el.value;
      const start = { url: 'https://', email: 'mailto:', phone: 'tel:', whatsapp: 'https://wa.me/' }[v];
      c.addAttributes({ href: v.includes(':') && !start ? v.slice(v.indexOf(':') + 1) : start || '#' });
      later();
    } else if (el.dataset.linkinput) {
      const raw = el.value.trim();
      const href = {
        url: /^[a-z]+:/i.test(raw) ? raw : 'https://' + raw,
        email: 'mailto:' + raw,
        phone: 'tel:' + raw.replace(/[^\d+]/g, ''),
        whatsapp: 'https://wa.me/' + raw.replace(/\D/g, ''),
      }[el.dataset.linkinput];
      c.addAttributes({ href });
    } else if (el.matches('[data-newtab]')) {
      el.checked ? c.addAttributes({ target: '_blank', rel: 'noopener' }) : c.removeAttributes(['target', 'rel']);
    } else if (el.dataset.toggle) {
      const on = el.matches('[data-invert]') ? !el.checked : el.checked;
      on ? c.addClass(el.dataset.toggle) : c.removeClass(el.dataset.toggle);
      if (el.dataset.ensure) ensureCss(el.dataset.ensure);
    } else if (el.dataset.darken) {
      set({ 'background-image': `${el.checked ? DARKEN + ',' : ''}url("${el.dataset.darken}")`, color: el.checked ? '#ffffff' : '' });
      later();
    }
  });

  box.addEventListener('toggle', (e) => {
    const g = e.target.dataset?.g;
    if (g) e.target.open ? closed.delete(g) : closed.add(g);
  }, true);

  $('#sel-path').addEventListener('click', (e) => {
    const i = e.target.dataset.pick;
    if (i !== undefined) editor.select(path[+i]);
  });

  editor.on('component:selected', later);
  editor.on('change:device', later);
  editor.on('undo redo', later);
  document.querySelector('[data-st="design"]').addEventListener('click', later);
})();
