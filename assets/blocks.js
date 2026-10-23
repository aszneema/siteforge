// Section library, style presets and starter templates. Shared by the editor and the dashboard.
// Every section is styled only through the theme variables in :root, so presets, the Theme panel
// and AI-built sites all restyle it the same way.
(function () {
  const img = (id, w, h) => `https://images.unsplash.com/photo-${id}?auto=format&fit=crop&w=${w}&h=${h}&q=80`;

  const PRESETS = {
    modern: { label: 'Modern', vars: { '--primary': '#4f46e5', '--accent': '#06b6d4', '--on-primary': '#ffffff', '--bg': '#ffffff', '--surface': '#f4f6fb', '--text': '#0f172a', '--muted': '#64748b', '--font-heading': "'Poppins', sans-serif", '--font-body': "'Inter', sans-serif", '--radius': '14px', '--base-size': '16px', '--space': '96px', '--max-width': '1160px' } },
    elegant: { label: 'Elegant', vars: { '--primary': '#8a6a45', '--accent': '#c9a96e', '--on-primary': '#ffffff', '--bg': '#fbf8f3', '--surface': '#f3ece1', '--text': '#2a221c', '--muted': '#7c6f64', '--font-heading': "'Playfair Display', serif", '--font-body': "'Lato', sans-serif", '--radius': '4px', '--base-size': '17px', '--space': '110px', '--max-width': '1120px' } },
    bold: { label: 'Bold', vars: { '--primary': '#ff3d2e', '--accent': '#ffd23f', '--on-primary': '#ffffff', '--bg': '#ffffff', '--surface': '#f3f3ef', '--text': '#111111', '--muted': '#555555', '--font-heading': "'Oswald', sans-serif", '--font-body': "'DM Sans', sans-serif", '--radius': '0px', '--base-size': '17px', '--space': '100px', '--max-width': '1200px' } },
    playful: { label: 'Playful', vars: { '--primary': '#ff5c8a', '--accent': '#ffb703', '--on-primary': '#ffffff', '--bg': '#fffaf4', '--surface': '#fff0e3', '--text': '#2d1b3d', '--muted': '#7a6785', '--font-heading': "'Nunito', sans-serif", '--font-body': "'Nunito', sans-serif", '--radius': '22px', '--base-size': '17px', '--space': '90px', '--max-width': '1120px' } },
    minimal: { label: 'Minimal', vars: { '--primary': '#111111', '--accent': '#888888', '--on-primary': '#ffffff', '--bg': '#ffffff', '--surface': '#f6f6f6', '--text': '#111111', '--muted': '#6b6b6b', '--font-heading': "'Space Grotesk', sans-serif", '--font-body': "'Inter', sans-serif", '--radius': '6px', '--base-size': '16px', '--space': '100px', '--max-width': '1080px' } },
    dark: { label: 'Dark', vars: { '--primary': '#22d3ee', '--accent': '#a78bfa', '--on-primary': '#06121a', '--bg': '#0b1020', '--surface': '#131a2e', '--text': '#e6e9f2', '--muted': '#9aa4bf', '--font-heading': "'Space Grotesk', sans-serif", '--font-body': "'Inter', sans-serif", '--radius': '14px', '--base-size': '16px', '--space': '100px', '--max-width': '1160px' } },
    nature: { label: 'Nature', vars: { '--primary': '#2f7d4f', '--accent': '#e0a43a', '--on-primary': '#ffffff', '--bg': '#f7f8f2', '--surface': '#ecf0e3', '--text': '#1f2b22', '--muted': '#5f6c62', '--font-heading': "'Lora', serif", '--font-body': "'Nunito', sans-serif", '--radius': '12px', '--base-size': '17px', '--space': '96px', '--max-width': '1120px' } },
    ocean: { label: 'Ocean', vars: { '--primary': '#008b99', '--accent': '#1a2b4c', '--on-primary': '#ffffff', '--bg': '#f8fafc', '--surface': '#eaf3f6', '--text': '#1a2b4c', '--muted': '#5b6b80', '--font-heading': "'Montserrat', sans-serif", '--font-body': "'Inter', sans-serif", '--radius': '12px', '--base-size': '16px', '--space': '96px', '--max-width': '1160px' } },
  };

  const rootCss = (vars) => ':root{' + Object.entries(vars).map(([k, v]) => `${k}:${v}`).join(';') + '}';

  const BASE_CSS = `
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font-family:var(--font-body);font-size:var(--base-size);line-height:1.65;-webkit-font-smoothing:antialiased}
h1,h2,h3,h4{font-family:var(--font-heading);line-height:1.15;margin:0 0 .5em;letter-spacing:-.01em}
h1{font-size:clamp(2.3rem,5.2vw,4rem)}
h2{font-size:clamp(1.8rem,3.6vw,2.7rem)}
h3{font-size:1.25rem}
p{margin:0 0 1em}
a{color:var(--primary)}
img{max-width:100%;display:block}
.sf-container{width:min(100% - 40px, var(--max-width));margin-inline:auto}
.sf-section{padding:var(--space) 0}
.sf-alt{background:var(--surface)}
.sf-center{text-align:center}
.sf-head{max-width:680px;margin:0 auto 56px;text-align:center}
.sf-eyebrow{display:inline-block;font-size:.78rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--primary);margin-bottom:14px}
.sf-lead{font-size:1.15rem;color:var(--muted)}
.sf-muted{color:var(--muted)}
.sf-actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:30px}
.sf-center .sf-actions{justify-content:center}
.sf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 26px;border-radius:var(--radius);border:2px solid var(--primary);background:var(--primary);color:var(--on-primary);font:inherit;font-weight:600;text-decoration:none;cursor:pointer;transition:transform .2s ease,box-shadow .2s ease}
.sf-btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px -12px var(--primary)}
.sf-btn-ghost{background:transparent;color:var(--primary)}
.sf-grid{display:grid;gap:28px;grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}
.sf-card{background:var(--bg);border-radius:var(--radius);padding:30px;border:1px solid color-mix(in srgb,var(--text) 8%,transparent);box-shadow:0 18px 40px -28px rgba(15,23,42,.35)}
.sf-split{display:grid;grid-template-columns:1.05fr 1fr;gap:60px;align-items:center}
.sf-split img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:calc(var(--radius) * 1.6);box-shadow:0 40px 80px -40px rgba(15,23,42,.45)}
.sf-form{display:grid;gap:14px}
.sf-form label{display:grid;gap:6px;font-weight:600;font-size:.9rem}
.sf-form input,.sf-form textarea,.sf-form select{font:inherit;padding:13px 15px;border-radius:calc(var(--radius) * .7);border:1px solid color-mix(in srgb,var(--text) 18%,transparent);background:var(--bg);color:var(--text)}
.sf-form textarea{min-height:130px;resize:vertical}
@media (max-width:860px){.sf-split{grid-template-columns:1fr;gap:36px}}
`;

  // Each section: label, icon, its own CSS (added to the site when the section is first used)
  // and html(c), where c can override the default copy.
  const S = {};

  S.nav = {
    label: 'Navigation', icon: '🧭',
    css: `.sf-nav{position:sticky;top:0;z-index:50;background:color-mix(in srgb,var(--bg) 90%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid color-mix(in srgb,var(--text) 8%,transparent)}
.sf-nav__in{display:flex;align-items:center;gap:28px;min-height:74px}
.sf-logo{font-family:var(--font-heading);font-weight:800;font-size:1.35rem;color:var(--text);text-decoration:none;margin-inline-end:auto}
.sf-nav__links{display:flex;gap:26px}
.sf-nav__links a{color:var(--text);text-decoration:none;font-weight:500;opacity:.75;transition:opacity .2s,color .2s}
.sf-nav__links a:hover{opacity:1;color:var(--primary)}
.sf-nav .sf-btn{padding:10px 20px}
@media (max-width:760px){.sf-nav__in{flex-wrap:wrap;gap:8px 18px;padding:14px 0}.sf-nav__links{order:3;width:100%;overflow-x:auto;gap:18px}.sf-nav .sf-btn{display:none}}`,
    html: ({ brand = 'Your Brand', links = [['Home', 'index.html'], ['About', '#about'], ['Services', '#services'], ['Contact', '#contact']], cta = 'Get in touch', ctaHref = '#contact' }) =>
      `<header class="sf-nav"><div class="sf-container sf-nav__in"><a class="sf-logo" href="index.html">${brand}</a><nav class="sf-nav__links">${links.map(([l, h]) => `<a href="${h}">${l}</a>`).join('')}</nav><a class="sf-btn" href="${ctaHref}">${cta}</a></div></header>`,
  };

  S.heroCenter = {
    label: 'Hero (centered)', icon: '✨',
    css: `.sf-hero{padding:calc(var(--space) * 1.3) 0;background:radial-gradient(60% 80% at 15% 0%,color-mix(in srgb,var(--primary) 16%,transparent),transparent 70%),radial-gradient(50% 70% at 95% 20%,color-mix(in srgb,var(--accent) 16%,transparent),transparent 70%)}
.sf-hero h1{max-width:900px;margin-inline:auto}
.sf-hero .sf-lead{max-width:640px;margin-inline:auto}`,
    html: ({ eyebrow = 'Welcome', title = 'A headline that says exactly what you do', text = 'One or two sentences that explain who you help and why they should choose you. Keep it short and friendly.', cta = 'Get started', cta2 = 'Learn more', ctaHref = '#contact', cta2Href = '#about' }) =>
      `<section class="sf-hero sf-center"><div class="sf-container"><span class="sf-eyebrow">${eyebrow}</span><h1>${title}</h1><p class="sf-lead">${text}</p><div class="sf-actions"><a class="sf-btn" href="${ctaHref}">${cta}</a><a class="sf-btn sf-btn-ghost" href="${cta2Href}">${cta2}</a></div></div></section>`,
  };

  S.heroSplit = {
    label: 'Hero with image', icon: '🖼️',
    css: `.sf-hero-split{padding:calc(var(--space) * 1.1) 0}`,
    html: ({ eyebrow = 'New', title = 'Big, clear promise to your visitors', text = 'Explain the value in a sentence or two. Show a photo that makes people want to know more.', cta = 'Get started', cta2 = 'See how it works', ctaHref = '#contact', cta2Href = '#features', photo = '1497366216548-37526070297c' }) =>
      `<section class="sf-hero-split"><div class="sf-container sf-split"><div><span class="sf-eyebrow">${eyebrow}</span><h1>${title}</h1><p class="sf-lead">${text}</p><div class="sf-actions"><a class="sf-btn" href="${ctaHref}">${cta}</a><a class="sf-btn sf-btn-ghost" href="${cta2Href}">${cta2}</a></div></div><img src="${img(photo, 1000, 750)}" alt="${title}"></div></section>`,
  };

  S.heroCover = {
    label: 'Hero (full photo)', icon: '🌄',
    css: `.sf-cover{position:relative;min-height:86vh;display:grid;align-items:center;color:#fff;isolation:isolate;overflow:hidden}
.sf-cover__bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}
.sf-cover::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.3),rgba(0,0,0,.65));z-index:-1}
.sf-cover h1{color:#fff;max-width:880px;margin-inline:auto}
.sf-cover .sf-lead{color:rgba(255,255,255,.88);max-width:620px;margin-inline:auto}`,
    html: ({ title = 'Make a stunning first impression', text = 'A full-screen photo with a short message on top. Perfect for restaurants, travel, events and portfolios.', cta = 'Book now', ctaHref = '#contact', photo = '1469474968028-56623f02e42e' }) =>
      `<section class="sf-cover sf-center"><img class="sf-cover__bg" src="${img(photo, 1800, 1100)}" alt=""><div class="sf-container"><h1>${title}</h1><p class="sf-lead">${text}</p><div class="sf-actions"><a class="sf-btn" href="${ctaHref}">${cta}</a></div></div></section>`,
  };

  S.logos = {
    label: 'Logo strip', icon: '🏷️',
    css: `.sf-logos{padding:48px 0;border-block:1px solid color-mix(in srgb,var(--text) 8%,transparent)}
.sf-logos p{text-align:center;color:var(--muted);font-size:.8rem;text-transform:uppercase;letter-spacing:.14em;margin-bottom:22px}
.sf-logos__row{display:flex;flex-wrap:wrap;justify-content:center;gap:24px 56px}
.sf-logos__row span{font-family:var(--font-heading);font-size:1.35rem;font-weight:800;opacity:.4}`,
    html: ({ title = 'Trusted by teams at', names = ['Northwind', 'Lumen', 'Acme Co', 'Vertex', 'Globex', 'Pinecrest'] }) =>
      `<section class="sf-logos"><div class="sf-container"><p>${title}</p><div class="sf-logos__row">${names.map((x) => `<span>${x}</span>`).join('')}</div></div></section>`,
  };

  S.features = {
    label: 'Features', icon: '⭐',
    css: `.sf-icon{width:54px;height:54px;display:grid;place-items:center;border-radius:calc(var(--radius) * .8);background:color-mix(in srgb,var(--primary) 12%,transparent);font-size:1.5rem;margin-bottom:18px}
.sf-feature p{margin:0}`,
    html: ({ eyebrow = 'Why us', title = 'Everything you need, nothing you don’t', text = 'Three reasons people love working with us.', items = [['⚡', 'Fast', 'Get results quickly without the usual waiting around.'], ['🛡️', 'Reliable', 'We do what we promise, every single time.'], ['💬', 'Friendly', 'Real people who answer and actually care.']] }) =>
      `<section class="sf-section" id="features"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2><p class="sf-lead">${text}</p></div><div class="sf-grid">${items.map(([i, t, d]) => `<div class="sf-card sf-feature"><div class="sf-icon">${i}</div><h3>${t}</h3><p class="sf-muted">${d}</p></div>`).join('')}</div></div></section>`,
  };

  S.about = {
    label: 'About', icon: '👋',
    css: `.sf-checks{list-style:none;padding:0;margin:22px 0 0;display:grid;gap:12px}
.sf-checks li{position:relative;padding-inline-start:32px}
.sf-checks li::before{content:"✓";position:absolute;inset-inline-start:0;top:3px;width:21px;height:21px;border-radius:50%;background:var(--primary);color:var(--on-primary);font-size:12px;font-weight:700;display:grid;place-items:center}`,
    html: ({ eyebrow = 'About us', title = 'A small team with a big passion', text = 'Tell your story here: how you started, what you believe in and what makes you different. People buy from people, so make it personal.', points = ['Over 10 years of experience', 'Hundreds of happy customers', 'Local, independent and proud of it'], photo = '1522071820081-009f0129c71c' }) =>
      `<section class="sf-section sf-alt" id="about"><div class="sf-container sf-split"><img src="${img(photo, 1000, 750)}" alt="${title}"><div><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2><p class="sf-lead">${text}</p><ul class="sf-checks">${points.map((p) => `<li>${p}</li>`).join('')}</ul></div></div></section>`,
  };

  S.services = {
    label: 'Services', icon: '🧰',
    css: `.sf-service{padding:0;overflow:hidden}
.sf-service img{width:100%;aspect-ratio:16/10;object-fit:cover}
.sf-service__body{padding:24px 26px 28px}
.sf-service__body p{margin:0}`,
    html: ({ eyebrow = 'Services', title = 'What we offer', text = 'Pick the service that fits you best.', items = [['1454165804606-c3d57bc86b40', 'Service one', 'A short description of this service and who it is for.'], ['1561070791-2526d30994b5', 'Service two', 'A short description of this service and who it is for.'], ['1552664730-d307ca884978', 'Service three', 'A short description of this service and who it is for.']] }) =>
      `<section class="sf-section" id="services"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2><p class="sf-lead">${text}</p></div><div class="sf-grid">${items.map(([k, t, d]) => `<div class="sf-card sf-service"><img src="${img(k, 800, 500)}" alt="${t}"><div class="sf-service__body"><h3>${t}</h3><p class="sf-muted">${d}</p></div></div>`).join('')}</div></div></section>`,
  };

  S.stats = {
    label: 'Numbers', icon: '📈',
    css: `.sf-stats{background:var(--primary);color:var(--on-primary);padding:calc(var(--space) * .6) 0}
.sf-stats__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:24px;text-align:center}
.sf-stats b{display:block;font-family:var(--font-heading);font-size:clamp(2rem,4vw,3rem);line-height:1.1}
.sf-stats span{opacity:.85}`,
    html: ({ items = [['10+', 'Years of experience'], ['2,500', 'Happy customers'], ['4.9★', 'Average rating'], ['24/7', 'Support']] }) =>
      `<section class="sf-stats"><div class="sf-container sf-stats__grid">${items.map(([v, l]) => `<div><b>${v}</b><span>${l}</span></div>`).join('')}</div></section>`,
  };

  S.steps = {
    label: 'How it works', icon: '🪜',
    css: `.sf-steps{counter-reset:step}
.sf-step::before{counter-increment:step;content:counter(step);width:46px;height:46px;border-radius:50%;background:var(--primary);color:var(--on-primary);display:grid;place-items:center;font-weight:800;font-size:1.1rem;margin-bottom:18px}
.sf-step p{margin:0}`,
    html: ({ eyebrow = 'How it works', title = 'Up and running in three steps', items = [['Tell us what you need', 'A quick chat so we understand your goals.'], ['We get to work', 'We plan, build and keep you updated.'], ['Enjoy the results', 'You launch, we stay around to help.']] }) =>
      `<section class="sf-section sf-alt"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-grid sf-steps">${items.map(([t, d]) => `<div class="sf-card sf-step"><h3>${t}</h3><p class="sf-muted">${d}</p></div>`).join('')}</div></div></section>`,
  };

  S.gallery = {
    label: 'Photo gallery', icon: '📸',
    css: `.sf-gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.sf-gallery img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);transition:transform .35s ease}
.sf-gallery img:hover{transform:scale(1.02)}
@media (max-width:700px){.sf-gallery{grid-template-columns:repeat(2,1fr);gap:10px}}`,
    html: ({ eyebrow = 'Gallery', title = 'A look at our work', photos = ['1502005229762-cf1b2da7c5d6', '1493663284031-b7e3aefcae8e', '1484154218962-a197022b5858', '1505693416388-ac5ce068fe85', '1554995207-c18c203602cb', '1524758631624-e2822e304c36'] }) =>
      `<section class="sf-section" id="gallery"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-gallery">${photos.map((p, i) => `<img src="${img(p, 700, 700)}" alt="${title} ${i + 1}">`).join('')}</div></div></section>`,
  };

  S.testimonials = {
    label: 'Testimonials', icon: '💬',
    css: `.sf-stars{color:#f5a524;letter-spacing:2px;margin-bottom:12px}
.sf-quote p{font-size:1.05rem}
.sf-person{display:flex;align-items:center;gap:12px;margin-top:20px}
.sf-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:var(--on-primary);display:grid;place-items:center;font-weight:700;flex:none}
.sf-person b{display:block;line-height:1.3}
.sf-person small{color:var(--muted)}`,
    html: ({ eyebrow = 'Reviews', title = 'What our customers say', items = [['Absolutely fantastic. They listened, delivered on time and went beyond what we asked for.', 'Sara Ahmed', 'Small business owner'], ['The easiest decision we made all year. Friendly, quick and great value.', 'James Carter', 'Marketing lead'], ['I recommend them to everyone. You can tell they really care about the details.', 'Mona Khalil', 'Designer']] }) =>
      `<section class="sf-section sf-alt" id="reviews"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-grid">${items.map(([q, nm, r]) => `<div class="sf-card sf-quote"><div class="sf-stars">★★★★★</div><p>“${q}”</p><div class="sf-person"><span class="sf-avatar">${nm[0]}</span><div><b>${nm}</b><small>${r}</small></div></div></div>`).join('')}</div></div></section>`,
  };

  S.pricing = {
    label: 'Pricing', icon: '💳',
    css: `.sf-plan{display:flex;flex-direction:column}
.sf-plan--featured{border:2px solid var(--primary);transform:translateY(-8px)}
.sf-badge{align-self:flex-start;background:var(--primary);color:var(--on-primary);font-size:.72rem;font-weight:700;padding:4px 10px;border-radius:999px;margin-bottom:10px}
.sf-price{font-family:var(--font-heading);font-size:2.7rem;font-weight:800;line-height:1;margin:8px 0 4px}
.sf-price small{font-size:1rem;color:var(--muted);font-weight:500}
.sf-plan ul{list-style:none;padding:0;margin:22px 0 28px;display:grid;gap:10px;flex:1}
.sf-plan li::before{content:"✓";color:var(--primary);font-weight:800;margin-inline-end:10px}`,
    html: ({ eyebrow = 'Pricing', title = 'Simple, honest pricing', text = 'No hidden fees. Change or cancel any time.', plans = [['Starter', '$19', ['1 project', 'Email support', 'All the basics'], false], ['Pro', '$49', ['10 projects', 'Priority support', 'Advanced features', 'Team access'], true], ['Business', '$99', ['Unlimited projects', 'Phone support', 'Custom setup'], false]] }) =>
      `<section class="sf-section" id="pricing"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2><p class="sf-lead">${text}</p></div><div class="sf-grid">${plans.map(([nm, p, f, hot]) => `<div class="sf-card sf-plan${hot ? ' sf-plan--featured' : ''}">${hot ? '<span class="sf-badge">Most popular</span>' : ''}<h3>${nm}</h3><div class="sf-price">${p}<small> / month</small></div><ul>${f.map((x) => `<li>${x}</li>`).join('')}</ul><a class="sf-btn${hot ? '' : ' sf-btn-ghost'}" href="#contact">Choose ${nm}</a></div>`).join('')}</div></div></section>`,
  };

  S.faq = {
    label: 'FAQ', icon: '❓',
    css: `.sf-faq{max-width:780px;margin:0 auto;display:grid;gap:12px}
.sf-faq details{background:var(--bg);border:1px solid color-mix(in srgb,var(--text) 10%,transparent);border-radius:var(--radius);padding:18px 22px}
.sf-faq summary{cursor:pointer;font-weight:600;list-style:none;display:flex;justify-content:space-between;gap:16px}
.sf-faq summary::-webkit-details-marker{display:none}
.sf-faq summary::after{content:"+";color:var(--primary);font-size:1.4rem;line-height:1}
.sf-faq details[open] summary::after{content:"–"}
.sf-faq details p{margin:12px 0 0;color:var(--muted)}`,
    html: ({ eyebrow = 'FAQ', title = 'Questions people ask', items = [['How do I get started?', 'Just send us a message or book a call. We will reply within one working day.'], ['How much does it cost?', 'It depends on what you need. Our pricing is clear and we always quote before we start.'], ['Can I change my plan later?', 'Yes, you can upgrade, downgrade or cancel at any time.'], ['Where are you based?', 'We work from our studio in the city centre and online with clients everywhere.']] }) =>
      `<section class="sf-section sf-alt" id="faq"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-faq">${items.map(([q, a]) => `<details><summary>${q}</summary><p>${a}</p></details>`).join('')}</div></div></section>`,
  };

  S.team = {
    label: 'Team', icon: '🧑‍🤝‍🧑',
    css: `.sf-member{text-align:center}
.sf-member img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);margin-bottom:16px}
.sf-member h3{margin-bottom:2px}`,
    html: ({ eyebrow = 'Our team', title = 'The people behind it', members = [['Layla Hassan', 'Founder', '1494790108377-be9c29b29330'], ['Omar Saeed', 'Lead designer', '1500648767791-00dcc994a43e'], ['Emma Brooks', 'Customer success', '1438761681033-6461ffad8d80'], ['Noah Kim', 'Developer', '1507003211169-0a1dd7228f2d']] }) =>
      `<section class="sf-section" id="team"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-grid">${members.map(([nm, r, p]) => `<div class="sf-member"><img src="${img(p, 600, 600)}" alt="${nm}"><h3>${nm}</h3><p class="sf-muted">${r}</p></div>`).join('')}</div></div></section>`,
  };

  S.menu = {
    label: 'Menu / price list', icon: '🍽️',
    css: `.sf-menu{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px 56px;max-width:1000px;margin:0 auto}
.sf-dish{display:flex;justify-content:space-between;gap:20px;padding-bottom:18px;border-bottom:1px dashed color-mix(in srgb,var(--text) 22%,transparent)}
.sf-dish h3{margin:0 0 4px;font-size:1.1rem}
.sf-dish p{margin:0;color:var(--muted);font-size:.95rem}
.sf-dish b{font-family:var(--font-heading);color:var(--primary);font-size:1.15rem;white-space:nowrap}`,
    html: ({ eyebrow = 'Menu', title = 'Our favourites', items = [['House special', 'Our signature dish with seasonal ingredients', '$18'], ['Garden salad', 'Crisp greens, herbs and lemon dressing', '$11'], ['Grilled fish', 'Catch of the day with roasted vegetables', '$24'], ['Slow-cooked lamb', 'Eight hours, spices and saffron rice', '$26'], ['Fresh pasta', 'Handmade daily with tomato and basil', '$16'], ['Chocolate cake', 'Warm, rich and served with cream', '$9']] }) =>
      `<section class="sf-section" id="menu"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-menu">${items.map(([nm, d, p]) => `<div class="sf-dish"><div><h3>${nm}</h3><p>${d}</p></div><b>${p}</b></div>`).join('')}</div></div></section>`,
  };

  S.products = {
    label: 'Products', icon: '🛍️',
    css: `.sf-product{padding:0;overflow:hidden;text-align:center}
.sf-product img{width:100%;aspect-ratio:1;object-fit:cover}
.sf-product__body{padding:20px 22px 24px}
.sf-product__price{font-weight:800;color:var(--primary);font-size:1.15rem;margin-bottom:14px}`,
    html: ({ eyebrow = 'Shop', title = 'Best sellers', items = [['Minimal watch', '$129', '1523275335684-37898b6baf30'], ['Studio headphones', '$89', '1505740420928-5e560c06d30e'], ['Run sneakers', '$110', '1542291026-7eec264c27ff'], ['Instant camera', '$75', '1526170375885-4d8ecf77b99f']] }) =>
      `<section class="sf-section" id="shop"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-grid">${items.map(([nm, p, ph]) => `<div class="sf-card sf-product"><img src="${img(ph, 600, 600)}" alt="${nm}"><div class="sf-product__body"><h3>${nm}</h3><div class="sf-product__price">${p}</div><a class="sf-btn" href="#contact">Order now</a></div></div>`).join('')}</div></div></section>`,
  };

  S.posts = {
    label: 'Blog posts', icon: '📰',
    css: `.sf-post{padding:0;overflow:hidden}
.sf-post img{width:100%;aspect-ratio:16/10;object-fit:cover}
.sf-post__body{padding:22px 24px 26px}
.sf-tag{font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--primary)}
.sf-post h3{margin:8px 0 10px}
.sf-more{font-weight:600;text-decoration:none}`,
    html: ({ eyebrow = 'Blog', title = 'Latest stories', items = [['Tips', 'Five things I wish I knew when I started', '1455390582262-044cdead277a'], ['Story', 'What a year of doing this taught me', '1499750310107-5fef28a66643'], ['Guide', 'A simple guide for complete beginners', '1486312338219-ce68d2c6f44d']] }) =>
      `<section class="sf-section" id="blog"><div class="sf-container"><div class="sf-head"><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2></div><div class="sf-grid">${items.map(([tag, t, ph]) => `<article class="sf-card sf-post"><img src="${img(ph, 800, 500)}" alt="${t}"><div class="sf-post__body"><span class="sf-tag">${tag}</span><h3>${t}</h3><p class="sf-muted">A short teaser that makes people want to read the whole post.</p><a class="sf-more" href="#">Read more →</a></div></article>`).join('')}</div></div></section>`,
  };

  S.cta = {
    label: 'Call to action', icon: '📣',
    css: `.sf-cta{padding:var(--space) 0}
.sf-cta__box{background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 55%,var(--accent)));color:var(--on-primary);border-radius:calc(var(--radius) * 1.6);padding:clamp(40px,6vw,76px);text-align:center}
.sf-cta__box h2{color:inherit}
.sf-cta__box p{opacity:.9;max-width:560px;margin-inline:auto}
.sf-cta__box .sf-btn{background:var(--on-primary);color:var(--primary);border-color:var(--on-primary);margin-top:18px}`,
    html: ({ title = 'Ready to get started?', text = 'Send us a message today and we will get back to you within 24 hours.', cta = 'Contact us', ctaHref = '#contact' }) =>
      `<section class="sf-cta"><div class="sf-container"><div class="sf-cta__box"><h2>${title}</h2><p>${text}</p><a class="sf-btn" href="${ctaHref}">${cta}</a></div></div></section>`,
  };

  S.newsletter = {
    label: 'Newsletter', icon: '✉️',
    css: `.sf-news form{display:flex;flex-wrap:wrap;gap:10px;max-width:480px;margin:26px auto 0}
.sf-news input{flex:1 1 220px;font:inherit;padding:14px 16px;border-radius:var(--radius);border:1px solid color-mix(in srgb,var(--text) 18%,transparent);background:var(--bg);color:var(--text)}`,
    html: ({ title = 'Get updates in your inbox', text = 'News, tips and offers once a month. No spam, ever.' }) =>
      `<section class="sf-section sf-alt sf-news sf-center"><div class="sf-container"><h2>${title}</h2><p class="sf-lead">${text}</p><form><input type="email" name="email" placeholder="Your email address" required aria-label="Email"><button class="sf-btn" type="submit">Subscribe</button></form></div></section>`,
  };

  S.contact = {
    label: 'Contact form', icon: '📬',
    css: `.sf-info{list-style:none;padding:0;margin:26px 0 0;display:grid;gap:18px}
.sf-info li{display:flex;gap:14px;align-items:flex-start}
.sf-info li>span{font-size:1.3rem}
.sf-info b{display:block}`,
    html: ({ eyebrow = 'Contact', title = 'Let’s talk', text = 'Questions, bookings or just saying hello: fill in the form and we will reply soon.', address = '123 Main Street, Your City', phone = '+1 555 123 4567', email = 'hello@yourbrand.com', hours = 'Mon–Fri, 9am–6pm', button = 'Send message', extra = '' }) =>
      `<section class="sf-section" id="contact"><div class="sf-container sf-split"><div><span class="sf-eyebrow">${eyebrow}</span><h2>${title}</h2><p class="sf-lead">${text}</p><ul class="sf-info"><li><span>📍</span><div><b>Address</b>${address}</div></li><li><span>📞</span><div><b>Phone</b>${phone}</div></li><li><span>✉️</span><div><b>Email</b>${email}</div></li><li><span>🕒</span><div><b>Hours</b>${hours}</div></li></ul></div><form class="sf-card sf-form"><label>Your name<input name="name" required></label><label>Email<input type="email" name="email" required></label>${extra}<label>Message<textarea name="message" required></textarea></label><button class="sf-btn" type="submit">${button}</button></form></div></section>`,
  };

  S.footer = {
    label: 'Footer', icon: '🦶',
    css: `.sf-footer{background:color-mix(in srgb,var(--text) 94%,var(--primary));color:color-mix(in srgb,var(--bg) 72%,transparent);padding:64px 0 30px}
.sf-footer__in{display:flex;flex-wrap:wrap;justify-content:space-between;gap:32px}
.sf-footer .sf-logo{color:var(--bg)}
.sf-footer p{max-width:340px;margin:12px 0 0}
.sf-footer a{color:inherit;text-decoration:none}
.sf-footer a:hover{color:var(--bg)}
.sf-footer__links{display:flex;flex-wrap:wrap;gap:12px 26px;align-items:flex-start}
.sf-footer__bottom{margin-top:44px;padding-top:22px;border-top:1px solid color-mix(in srgb,var(--bg) 16%,transparent);font-size:.88rem}`,
    html: ({ brand = 'Your Brand', text = 'A short line about who you are and what you do.', links = [['Home', 'index.html'], ['About', '#about'], ['Services', '#services'], ['Contact', '#contact']] }) =>
      `<footer class="sf-footer"><div class="sf-container"><div class="sf-footer__in"><div><a class="sf-logo" href="index.html">${brand}</a><p>${text}</p></div><nav class="sf-footer__links">${links.map(([l, h]) => `<a href="${h}">${l}</a>`).join('')}</nav></div><div class="sf-footer__bottom">© ${new Date().getFullYear()} ${brand}. All rights reserved.</div></div></footer>`,
  };

  S.blank = {
    label: 'Empty section', icon: '⬜',
    css: '',
    html: () => `<section class="sf-section"><div class="sf-container"><h2>New section</h2><p class="sf-lead">Double-click to write. Drag elements from the Basics list in here.</p></div></section>`,
  };

  // Small building blocks that go inside sections.
  const BASICS = [
    ['heading', 'Heading', '🔠', '<h2>Your heading</h2>'],
    ['text', 'Text', '📝', '<p>Double-click to write your own text here.</p>'],
    ['button', 'Button', '🔘', '<a class="sf-btn" href="#">Click me</a>'],
    ['button2', 'Outline button', '⭕', '<a class="sf-btn sf-btn-ghost" href="#">Learn more</a>'],
    ['image', 'Image', '🖼️', { type: 'image', activate: true, style: { 'border-radius': 'var(--radius)', width: '100%' } }],
    ['video', 'Video', '🎬', { type: 'video', provider: 'yt', videoId: 'ScMzIvxBSi4', style: { width: '100%', height: '420px', 'border-radius': 'var(--radius)' } }],
    ['map', 'Map', '🗺️', { type: 'map', style: { width: '100%', height: '360px' } }],
    ['cols2', '2 columns', '◫', '<div class="sf-grid"><div><h3>Column one</h3><p>Some text for this column.</p></div><div><h3>Column two</h3><p>Some text for this column.</p></div></div>'],
    ['cols3', '3 columns', '▥', '<div class="sf-grid"><div class="sf-card"><h3>Card one</h3><p>Short text.</p></div><div class="sf-card"><h3>Card two</h3><p>Short text.</p></div><div class="sf-card"><h3>Card three</h3><p>Short text.</p></div></div>'],
    ['list', 'List', '📋', '<ul><li>First point</li><li>Second point</li><li>Third point</li></ul>'],
    ['quote', 'Quote', '❝', '<blockquote style="border-inline-start:4px solid var(--primary);margin:24px 0;padding:6px 0 6px 22px;font-size:1.2rem;font-style:italic">“A great quote that people will remember.”</blockquote>'],
    ['divider', 'Divider', '➖', '<hr style="border:0;border-top:1px solid color-mix(in srgb,var(--text) 15%,transparent);margin:32px 0">'],
    ['spacer', 'Spacer', '↕️', '<div style="height:64px"></div>'],
    ['form', 'Form', '🧾', '<form class="sf-form"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Message<textarea name="message"></textarea></label><button class="sf-btn" type="submit">Send</button></form>'],
  ];

  const sectionHtml = (key, c = {}) => (S[key].css ? `<style>${S[key].css}</style>` : '') + S[key].html(c);

  function blocks() {
    const label = (icon, text) => `<div class="blk-ic">${icon}</div><div class="blk-tx">${text}</div>`;
    return [
      ...Object.entries(S).map(([id, s]) => ({ id: 's-' + id, label: label(s.icon, s.label), category: 'Sections', content: sectionHtml(id) })),
      ...BASICS.map(([id, text, icon, content]) => ({ id: 'b-' + id, label: label(icon, text), category: 'Basics', content, activate: id === 'image', select: true })),
    ];
  }

  // Starter templates: pages made of sections, with copy that fits the kind of site.
  const TEMPLATES = {
    blank: { name: 'Blank', desc: 'An empty page. Drag in sections or ask the AI.', preset: 'modern', pages: [['index', 'Home', []]] },
    business: {
      name: 'Business', desc: 'Services company with About and Contact pages.', preset: 'ocean',
      pages: (() => {
        const brand = 'Brightpath';
        const links = [['Home', 'index.html'], ['About', 'about.html'], ['Contact', 'contact.html']];
        const nav = ['nav', { brand, links, cta: 'Book a call', ctaHref: 'contact.html' }];
        const foot = ['footer', { brand, links, text: 'Practical advice that helps small businesses grow.' }];
        return [
          ['index', 'Home', [nav, ['heroSplit', { eyebrow: 'Business consulting', title: 'Grow your business with a clear plan', text: 'We help small and growing companies find customers, fix their numbers and build teams that last.', cta: 'Book a free call', ctaHref: 'contact.html', cta2: 'What we do', cta2Href: '#features', photo: '1600880292203-757bb62b4baf' }], ['logos', {}], ['features', { eyebrow: 'What we do', title: 'Support at every stage', text: 'From your first plan to your fiftieth hire.', items: [['🧭', 'Strategy', 'A one-page plan everyone on your team understands.'], ['📊', 'Finance', 'Clear numbers, healthy cash flow and smarter pricing.'], ['🤝', 'People', 'Hire well, train well and keep your best people.']] }], ['stats', {}], ['testimonials', {}], ['cta', { title: 'Let’s grow together', text: 'Book a free 30-minute call. No pressure, just good advice.', cta: 'Book a call', ctaHref: 'contact.html' }], foot]],
          ['about', 'About', [nav, ['heroCenter', { eyebrow: 'About us', title: 'Built by people who ran small businesses', text: 'We know the late nights and tough decisions because we lived them.', cta: 'Work with us', ctaHref: 'contact.html', cta2: 'Meet the team', cta2Href: '#team' }], ['about', { photo: '1556761175-5973dc0f32e7' }], ['team', {}], foot]],
          ['contact', 'Contact', [nav, ['contact', { title: 'Book your free call', button: 'Request a call' }], ['faq', {}], foot]],
        ];
      })(),
    },
    restaurant: {
      name: 'Restaurant', desc: 'Menu, gallery and table bookings.', preset: 'elegant',
      pages: [['index', 'Home', [
        ['nav', { brand: 'Olive & Thyme', links: [['Menu', '#menu'], ['Our story', '#about'], ['Gallery', '#gallery'], ['Visit', '#contact']], cta: 'Book a table' }],
        ['heroCover', { title: 'Seasonal food, cooked with love', text: 'A neighbourhood kitchen serving fresh, honest Mediterranean dishes every day.', cta: 'Book a table', photo: '1414235077428-338989a2e8c0' }],
        ['about', { eyebrow: 'Our story', title: 'From our family’s kitchen to yours', text: 'Olive & Thyme started with our grandmother’s recipes. Today we cook them with local produce, slow fires and plenty of olive oil.', points: ['Fresh ingredients from local farms', 'Everything made in house', 'Vegetarian and gluten-free options'], photo: '1577219491135-ce391730fb2c' }],
        ['menu', {}],
        ['gallery', { eyebrow: 'Gallery', title: 'A taste of the evening', photos: ['1565299624946-b28f40a0ae38', '1540189549336-e6e99c3679fe', '1567620905732-2d1ec7ab7445', '1546069901-ba9599a7e63c', '1512621776951-a57141f2eefd', '1476224203421-9ac39bcb3327'] }],
        ['testimonials', { title: 'Our guests say it best' }],
        ['contact', { eyebrow: 'Visit us', title: 'Book a table', text: 'Tell us when you’re coming and how many guests. We’ll confirm by email.', hours: 'Tue–Sun, 12pm–11pm', button: 'Request booking', extra: '<label>Date and time<input type="datetime-local" name="when"></label><label>Guests<input type="number" name="guests" min="1" value="2"></label>' }],
        ['footer', { brand: 'Olive & Thyme', text: 'Mediterranean kitchen and wine bar.', links: [['Menu', '#menu'], ['Story', '#about'], ['Book', '#contact']] }],
      ]]],
    },
    portfolio: {
      name: 'Portfolio', desc: 'Show your work as a designer or photographer.', preset: 'minimal',
      pages: [['index', 'Home', [
        ['nav', { brand: 'Studio Nora', links: [['Work', '#gallery'], ['Services', '#services'], ['Contact', '#contact']], cta: 'Hire me' }],
        ['heroCenter', { eyebrow: 'Designer & photographer', title: 'I make brands look as good as they are', text: 'Hi, I’m Nora. I design identities, websites and photos for people who care about the details.', cta: 'See my work', ctaHref: '#gallery', cta2: 'Get in touch', cta2Href: '#contact' }],
        ['gallery', { eyebrow: 'Selected work', title: 'Recent projects', photos: ['1486406146926-c627a92ad1ab', '1487958449943-2429e8be8625', '1511818966892-d7d671e672a2', '1449157291145-7efd050a4d0e', '1493397212122-2b85dda8106b', '1502005229762-cf1b2da7c5d6', '1524758631624-e2822e304c36', '1618005182384-a83a8bd57fbe', '1561070791-2526d30994b5'] }],
        ['services', { title: 'How I can help', items: [['1558655146-9f40138edfeb', 'Brand identity', 'Logos, colours and type that feel like you.'], ['1547658719-da2b51169166', 'Web design', 'Simple, beautiful websites that work on every screen.'], ['1516035069371-29a1b244cc32', 'Photography', 'Product and portrait photos with natural light.']] }],
        ['testimonials', { title: 'Kind words' }],
        ['contact', { title: 'Let’s make something great', text: 'Tell me about your project and I’ll reply within two days.', button: 'Send enquiry' }],
        ['footer', { brand: 'Studio Nora', text: 'Design and photography studio.', links: [['Work', '#gallery'], ['Services', '#services'], ['Contact', '#contact']] }],
      ]]],
    },
    shop: {
      name: 'Online shop', desc: 'Products, reasons to buy and a newsletter.', preset: 'playful',
      pages: [['index', 'Home', [
        ['nav', { brand: 'Nova Store', links: [['Shop', '#shop'], ['Why us', '#features'], ['FAQ', '#faq']], cta: 'Shop now', ctaHref: '#shop' }],
        ['heroSplit', { eyebrow: 'New season', title: 'Everyday things, designed to last', text: 'A small collection of well-made gear for work, travel and weekends. Chosen with care, shipped fast.', cta: 'Shop the collection', ctaHref: '#shop', cta2: 'Our promise', cta2Href: '#features', photo: '1441986300917-64674bd600d8' }],
        ['products', { title: 'Customer favourites' }],
        ['features', { eyebrow: 'Our promise', title: 'Shopping without the worry', text: 'Quality you can feel and service you can count on.', items: [['🚚', 'Free shipping', 'On every order over $50, delivered in 2 to 4 days.'], ['🛡️', '2-year warranty', 'If something breaks, we fix or replace it.'], ['↩️', 'Easy returns', 'Not right? Send it back within 30 days.']] }],
        ['newsletter', { title: 'Get 10% off your first order', text: 'Join our list for new arrivals and early offers.' }],
        ['faq', { items: [['How fast is delivery?', 'Most orders arrive within 2 to 4 working days.'], ['Do you ship abroad?', 'Yes, we ship to most countries within 5 to 9 days.'], ['How do returns work?', 'Request a return within 30 days and we send you a free label.']] }],
        ['footer', { brand: 'Nova Store', text: 'Well-made everyday gear.', links: [['Shop', '#shop'], ['FAQ', '#faq']] }],
      ]]],
    },
    startup: {
      name: 'App / startup', desc: 'Landing page with pricing for a product.', preset: 'dark',
      pages: [['index', 'Home', [
        ['nav', { brand: 'Flowly', links: [['Features', '#features'], ['Pricing', '#pricing'], ['FAQ', '#faq']], cta: 'Start free', ctaHref: '#pricing' }],
        ['heroCenter', { eyebrow: 'Now in public beta', title: 'Plan your week in five minutes', text: 'Flowly turns your to-dos, meetings and goals into one calm, simple plan.', cta: 'Start for free', ctaHref: '#pricing', cta2: 'See features', cta2Href: '#features' }],
        ['logos', { title: 'Loved by people at' }],
        ['features', { eyebrow: 'Features', title: 'Less juggling, more doing', text: 'Everything in one place, beautifully simple.', items: [['🗓️', 'Smart calendar', 'Tasks drop into free time automatically.'], ['🔔', 'Gentle reminders', 'Nudges at the right moment, never spam.'], ['📱', 'Every device', 'Phone, tablet and desktop, always in sync.']] }],
        ['steps', {}],
        ['pricing', {}],
        ['faq', {}],
        ['cta', { title: 'Try Flowly free for 14 days', text: 'No card needed. Cancel any time.', cta: 'Get started', ctaHref: '#pricing' }],
        ['footer', { brand: 'Flowly', text: 'The calm way to plan your week.', links: [['Features', '#features'], ['Pricing', '#pricing'], ['FAQ', '#faq']] }],
      ]]],
    },
    blog: {
      name: 'Blog', desc: 'Personal blog with posts and a newsletter.', preset: 'nature',
      pages: [['index', 'Home', [
        ['nav', { brand: 'Wander Notes', links: [['Stories', '#blog'], ['About', '#about'], ['Subscribe', '#subscribe']], cta: 'Subscribe', ctaHref: '#subscribe' }],
        ['heroSplit', { eyebrow: 'Travel blog', title: 'Slow travel, small towns and good food', text: 'Stories and practical guides from ten years of exploring the world on a budget.', cta: 'Read the latest', ctaHref: '#blog', cta2: 'About me', cta2Href: '#about', photo: '1501785888041-af3ef285b470' }],
        ['posts', { title: 'Latest stories', items: [['Guide', 'Two weeks by the sea on a small budget', '1507525428034-b723cf961d3e'], ['Story', 'The night train that changed my plans', '1527631746610-bca00a040d60'], ['Tips', 'How I plan a month of travel in one evening', '1488646953014-85cb44e25828']] }],
        ['about', { eyebrow: 'About me', title: 'Hi, I’m Sam', text: 'I left my office job to see the world slowly. I write honest guides so you can travel more for less.', points: ['40 countries and counting', 'Budget-friendly guides', 'New story every week'], photo: '1501555088652-021faa106b9b' }],
        ['newsletter', { title: 'New stories every Sunday', text: 'One email a week with my latest trip and best tips.' }],
        ['footer', { brand: 'Wander Notes', text: 'Slow travel stories and guides.', links: [['Stories', '#blog'], ['About', '#about']] }],
      ]]],
    },
  };

  /** Template pages as {id, name, html}; each section's CSS travels with it in a <style> tag. */
  const templatePages = (t) => t.pages.map(([id, name, sections]) => ({ id, name, html: sections.map(([k, c]) => sectionHtml(k, c)).join('') }));

  /** A whole standalone document, used for the template thumbnails on the dashboard. */
  function templateDoc(t, fontsUrl) {
    return `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="${fontsUrl}"><style>${rootCss(PRESETS[t.preset].vars)}${BASE_CSS}</style></head><body>${templatePages(t)[0].html || '<div style="height:100vh;display:grid;place-items:center;color:#94a3b8;font:600 40px sans-serif">Blank page</div>'}</body></html>`;
  }

  window.SF_LIB = { PRESETS, BASE_CSS, TEMPLATES, rootCss, blocks, templatePages, templateDoc };
})();
