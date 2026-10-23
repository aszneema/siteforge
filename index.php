<?php
require __DIR__ . '/lib.php';
$u = user();
$start = $u ? 'dashboard.php' : 'auth.php?mode=register';
head('Build a website in minutes', 'page-home');
?>
<main>
  <section class="hero">
    <div class="wrap hero-in">
      <div>
        <span class="pill">✨ AI builder + visual editor</span>
        <h1>Build the website you imagine, <span>exactly how you want it.</span></h1>
        <p class="lead">Describe your site to the AI and watch it appear, or design it yourself with drag-and-drop sections and hundreds of style options. Switch between the two whenever you like.</p>
        <div class="hero-cta">
          <a class="btn btn-lg" href="<?= $start ?>">Start building, it’s free</a>
          <a class="btn btn-ghost btn-lg" href="#how">See how it works</a>
        </div>
        <p class="muted small">No code. No design skills. Publish with one click.</p>
      </div>
      <div class="mock" aria-hidden="true">
        <div class="mock-bar"><i></i><i></i><i></i><span>siteforge · editor</span></div>
        <div class="mock-body">
          <div class="mock-chat">
            <p class="me">A cosy site for my bakery with our menu and opening hours</p>
            <p class="bot">Done! I built a warm homepage with your menu, hours and a photo gallery. Want online orders too?</p>
            <p class="me">Yes, and make the buttons pink</p>
          </div>
          <div class="mock-site">
            <div class="ms-nav"><b>Crumb &amp; Co.</b><span></span><span></span><span></span></div>
            <div class="ms-hero"><h3>Fresh bread, baked at sunrise</h3><p></p><em>Order now</em></div>
            <div class="ms-cards"><i></i><i></i><i></i></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wrap two-ways" id="how">
    <h2>Two ways to build. Use one, or both.</h2>
    <div class="ways">
      <article class="card way">
        <span class="way-ic">✨</span>
        <h3>Chat with the AI builder</h3>
        <p>Tell it what you want in plain words, in any language. It builds full pages with real content, then keeps editing: “add a pricing section”, “make it darker”, “put my logo here”.</p>
        <ul><li>Builds exactly what you describe</li><li>Understands your photos and screenshots</li><li>Every change can be undone</li></ul>
      </article>
      <article class="card way">
        <span class="way-ic">🎨</span>
        <h3>Design it yourself</h3>
        <p>Start from a template, drag in ready-made sections and click anything to change it. Fonts, colours, spacing, borders, shadows, layout: it’s all there.</p>
        <ul><li>25+ ready sections and elements</li><li>One-click styles and a theme editor</li><li>Desktop, tablet and phone views</li></ul>
      </article>
    </div>
  </section>

  <section class="band">
    <div class="wrap steps">
      <div><b>1</b><h3>Describe or pick</h3><p>Tell the AI your idea, or choose a template and style.</p></div>
      <div><b>2</b><h3>Make it yours</h3><p>Edit by hand, ask the AI for changes, or both.</p></div>
      <div><b>3</b><h3>Publish</h3><p>Go live with one click, or download the files.</p></div>
    </div>
  </section>

  <section class="wrap features">
    <h2>Everything a real website needs</h2>
    <div class="feature-grid">
      <div><span>📄</span><h3>Multiple pages</h3><p>Home, About, Menu, Contact… with shared header and footer.</p></div>
      <div><span>📬</span><h3>Working forms</h3><p>Contact and booking forms land in your SiteForge inbox.</p></div>
      <div><span>📱</span><h3>Looks great on phones</h3><p>Every section is responsive, and you can fine-tune each screen size.</p></div>
      <div><span>🖼️</span><h3>Your own images</h3><p>Upload logos and photos and drop them anywhere.</p></div>
      <div><span>🌍</span><h3>Any language</h3><p>Including Arabic and other right-to-left languages.</p></div>
      <div><span>⬇️</span><h3>Yours to keep</h3><p>Download clean HTML files and host them anywhere.</p></div>
    </div>
  </section>

  <section class="wrap final-cta">
    <h2>Your website is one sentence away.</h2>
    <a class="btn btn-lg" href="<?= $start ?>">Create my website</a>
  </section>
</main>
<?php foot();
