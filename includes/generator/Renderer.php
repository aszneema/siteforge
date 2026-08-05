<?php
declare(strict_types=1);

namespace SiteForge;

/**
 * Renders a spec into a finished static / PHP site using a hand-written
 * design system (no frameworks, no external assets — the output works offline).
 */
final class Renderer
{
    private array $spec;
    private array $t;          // resolved theme tokens
    private string $siteType;
    private string $ext;       // page extension
    private string $assetBase; // where css/js/img live

    public function __construct(array $spec, string $siteType)
    {
        $this->spec = $spec;
        $this->siteType = $siteType;
        $this->t = Blueprint::theme($spec);
        $this->ext = $siteType === 'php' ? 'php' : 'html';
        $this->assetBase = $siteType === 'php' ? 'assets/' : '';
    }

    /** @return array<string,string> */
    public function render(): array
    {
        return match ($this->siteType) {
            'landing' => $this->renderLanding(),
            'php'     => $this->renderPhp(),
            default   => $this->renderStatic(),
        };
    }

    // --- Site variants -----------------------------------------------------

    private function renderStatic(): array
    {
        return [
            'index.html'    => $this->page('home', $this->homeBody()),
            'about.html'    => $this->page('about', $this->aboutBody()),
            'services.html' => $this->page('services', $this->servicesBody()),
            'contact.html'  => $this->page('contact', $this->contactBody(false)),
            'css/styles.css' => $this->css(),
            'js/main.js'     => $this->js(),
            'README.txt'     => $this->readme(),
        ];
    }

    private function renderLanding(): array
    {
        $body = '';
        foreach ($this->orderedSections() as $key) {
            $body .= $this->sectionHtml($key, false, true);
            $body .= $this->componentsAfter($key);
        }
        $body .= $this->contactBody(false, true);

        return [
            'index.html'     => $this->page('home', $body),
            'css/styles.css' => $this->css(),
            'js/main.js'     => $this->js(),
            'README.txt'     => $this->readme(),
        ];
    }

    private function renderPhp(): array
    {
        return [
            'index.php'    => $this->phpPage('home', $this->homeBody()),
            'about.php'    => $this->phpPage('about', $this->aboutBody()),
            'services.php' => $this->phpPage('services', $this->servicesBody()),
            'contact.php'  => $this->phpContactPage(),
            'includes/header.php' => $this->phpHeader(),
            'includes/footer.php' => $this->phpFooter(),
            'assets/css/styles.css' => $this->css(),
            'assets/js/main.js'     => $this->js(),
            'README.txt'            => $this->readme(),
        ];
    }

    // --- Page bodies -------------------------------------------------------

    private function homeBody(): string
    {
        // The home page shows a teaser of about/services; the deep content
        // lives on its own page. Everything follows the spec's section order,
        // so "move testimonials above services" actually moves it.
        $onHome = ['hero', 'stats', 'about', 'services', 'gallery', 'testimonials', 'cta'];

        $body = '';
        foreach ($this->orderedSections() as $key) {
            if (!in_array($key, $onHome, true)) {
                continue;
            }
            $body .= $this->sectionHtml($key, true, false);
            $body .= $this->componentsAfter($key);
        }

        return $body;
    }

    /** Section keys in the order the spec lists them, hero always first. */
    private function orderedSections(): array
    {
        $known = ['hero', 'stats', 'about', 'services', 'gallery', 'pricing',
                  'testimonials', 'team', 'faq', 'cta'];

        $ordered = array_values(array_intersect($this->spec['sections'] ?? [], $known));
        if (!in_array('hero', $ordered, true)) {
            array_unshift($ordered, 'hero');
        } else {
            $ordered = array_merge(['hero'], array_diff($ordered, ['hero']));
        }

        return $ordered;
    }

    private function sectionHtml(string $key, bool $teaser, bool $anchors): string
    {
        return match ($key) {
            'hero'         => $this->hero($anchors),
            'stats'        => $this->stats(),
            'about'        => $this->about($anchors, $teaser),
            'services'     => $this->services($anchors, $teaser),
            'gallery'      => $this->gallery(),
            'pricing'      => $this->pricing(),
            'testimonials' => $this->testimonials(),
            'team'         => $this->team(),
            'faq'          => $this->faq(),
            'cta'          => $this->ctaBand(),
            default        => '',
        };
    }

    /** Components that sit in the page flow, anchored to a section. */
    private function componentsAfter(string $key): string
    {
        $out = '';
        if ($key === 'hero') {
            if ($this->hasComponent('marquee')) $out .= $this->marquee();
            if ($this->hasComponent('logos'))   $out .= $this->logos();
        }
        if ($key === 'about') {
            if ($this->hasComponent('slider'))   $out .= $this->slider();
            if ($this->hasComponent('timeline')) $out .= $this->timeline();
        }
        if ($key === 'services' && $this->hasComponent('process')) {
            $out .= $this->process();
        }
        if ($key === 'cta' && $this->hasComponent('newsletter')) {
            $out .= $this->newsletter();
        }

        return $out;
    }

    public function hasComponent(string $key): bool
    {
        return in_array($key, $this->spec['components'] ?? [], true);
    }

    private function aboutBody(): string
    {
        $body = $this->pageHeader($this->spec['about']['title'], $this->spec['about']['lead']);
        $body .= $this->about(false);
        if ($this->hasComponent('timeline')) $body .= $this->timeline();
        if ($this->has('stats')) $body .= $this->stats();
        if ($this->has('team'))  $body .= $this->team();
        if ($this->has('testimonials')) $body .= $this->testimonials();
        $body .= $this->ctaBand();
        return $body;
    }

    private function servicesBody(): string
    {
        $body = $this->pageHeader($this->spec['services']['title'], $this->spec['services']['sub']);
        $body .= $this->services(false, false);
        if ($this->hasComponent('process')) $body .= $this->process();
        if ($this->has('pricing')) $body .= $this->pricing();
        if ($this->has('faq'))     $body .= $this->faq();
        $body .= $this->ctaBand();
        return $body;
    }

    private function contactBody(bool $php, bool $inline = false): string
    {
        $c = $this->spec['contact'];
        $header = $inline ? '' : $this->pageHeader('Get in touch', 'Tell us what you need and we will come back to you within one working day.');

        $notice = $php
            ? "<?php if (\$sent): ?>\n        <div class=\"notice notice-ok\" role=\"status\">Thanks — your message is on its way. We'll reply within one working day.</div>\n      <?php elseif (\$errors): ?>\n        <div class=\"notice notice-bad\" role=\"alert\">\n          <ul><?php foreach (\$errors as \$error): ?><li><?= htmlspecialchars(\$error, ENT_QUOTES) ?></li><?php endforeach; ?></ul>\n        </div>\n      <?php endif; ?>"
            : '';

        $extras = '';
        if ($this->hasComponent('hours')) {
            $extras .= $this->hoursPanel();
        }
        if ($this->hasComponent('social')) {
            $extras .= '<h3>Follow us</h3>' . $this->socialLinks();
        }
        if ($this->hasComponent('map')) {
            $extras .= '<h3>Find us</h3>' . $this->mapPanel();
        }

        $action = $php ? '' : ' action="#" novalidate';
        $method = $php ? ' method="post"' : '';
        $valueName = $php ? '<?= htmlspecialchars($old[\'name\'] ?? \'\', ENT_QUOTES) ?>' : '';
        $valueEmail = $php ? '<?= htmlspecialchars($old[\'email\'] ?? \'\', ENT_QUOTES) ?>' : '';
        $valueMsg = $php ? '<?= htmlspecialchars($old[\'message\'] ?? \'\', ENT_QUOTES) ?>' : '';

        return $header . <<<HTML
        <section class="section" id="contact">
          <div class="container contact-grid">
            <div data-reveal>
              <h2 class="section-title">Send us a message</h2>
              <p class="section-sub">Fill this in and we'll get back to you. No forms that vanish into a black hole.</p>
              {$notice}
              <form class="form" id="contactForm"{$method}{$action}>
                <div class="field">
                  <label for="name">Your name</label>
                  <input type="text" id="name" name="name" required maxlength="80" autocomplete="name" value="{$valueName}">
                </div>
                <div class="field">
                  <label for="email">Email address</label>
                  <input type="email" id="email" name="email" required maxlength="120" autocomplete="email" value="{$valueEmail}">
                </div>
                <div class="field">
                  <label for="message">How can we help?</label>
                  <textarea id="message" name="message" rows="5" required maxlength="2000">{$valueMsg}</textarea>
                </div>
                <p class="form-error" id="formError" role="alert" hidden></p>
                <button class="btn btn-primary" type="submit">Send message</button>
                <p class="form-note" id="formNote" hidden>Thanks — your message has been captured.</p>
              </form>
            </div>

            <aside class="contact-card" data-reveal>
              <h3>Contact details</h3>
              <dl class="contact-list">
                <div><dt>Email</dt><dd><a href="mailto:{$this->h($c['email'])}">{$this->h($c['email'])}</a></dd></div>
                <div><dt>Phone</dt><dd><a href="tel:{$this->h(preg_replace('/[^+\d]/', '', $c['phone']))}">{$this->h($c['phone'])}</a></dd></div>
                <div><dt>Address</dt><dd>{$this->h($c['address'])}</dd></div>
                <div><dt>Hours</dt><dd>{$this->h($c['hours'])}</dd></div>
              </dl>
              {$extras}
            </aside>
          </div>
        </section>
        HTML;
    }

    // --- Sections ----------------------------------------------------------

    private function has(string $section): bool
    {
        return in_array($section, $this->spec['sections'] ?? [], true);
    }

    private function hero(bool $anchors): string
    {
        $h = $this->spec['hero'];
        $primaryHref = $anchors ? '#contact' : $this->link('contact');
        $secondHref  = $anchors ? '#services' : $this->link('services');

        $layout = $this->spec['hero_layout'] ?? 'split';
        $dynamic = ($this->spec['motion'] ?? 'subtle') === 'dynamic';

        $classes = 'hero';
        if ($layout === 'centred') $classes .= ' hero-centred';
        if ($layout === 'full')    $classes .= ' hero-full';
        if (!empty($this->spec['hero_flip'])) $classes .= ' hero-flip';

        $copyReveal = $dynamic ? ' data-reveal="left"' : ' data-reveal';
        $mediaReveal = $dynamic ? ' data-reveal="right"' : ' data-reveal';

        $copy = <<<HTML
            <div class="hero-copy"{$copyReveal}>
              <p class="eyebrow">{$this->h($h['eyebrow'])}</p>
              <h1 class="hero-title">{$this->h($h['title'])}</h1>
              <p class="hero-sub">{$this->h($h['sub'])}</p>
              <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="{$primaryHref}">{$this->h($h['cta'])}</a>
                <a class="btn btn-outline btn-lg" href="{$secondHref}">{$this->h($h['cta2'])}</a>
              </div>
            </div>
        HTML;

        // A full-bleed hero puts the image behind the copy instead of beside it.
        if ($layout === 'full') {
            $bg = $this->visual(0, 'hero-visual', $this->spec['name'] . ' — feature image');
            $parallax = $this->hasComponent('parallax') ? ' data-parallax="0.18"' : '';

            return <<<HTML
            <section class="{$classes}">
              <div class="hero-bg"{$parallax}>{$bg}</div>
              <div class="container hero-inner">
            {$copy}  </div>
            </section>
            HTML;
        }

        $parallax = $this->hasComponent('parallax') ? ' data-parallax="0.08"' : '';
        $visual = $this->visual(0, 'hero-visual', $this->spec['name'] . ' — feature image');

        return <<<HTML
        <section class="{$classes}">
          <div class="container hero-inner">
        {$copy}    <div class="hero-media"{$mediaReveal}{$parallax}>{$visual}</div>
          </div>
        </section>
        HTML;
    }

    // --- Optional components ------------------------------------------------

    public function marquee(): string
    {
        $items = [
            $this->spec['tagline'],
            $this->spec['about']['points'][0] ?? 'Trusted locally',
            $this->spec['hero']['eyebrow'],
            $this->spec['about']['points'][1] ?? 'Built to last',
        ];

        $row = '';
        foreach (array_merge($items, $items) as $item) {
            $row .= '<span>' . $this->h($item) . '</span><span aria-hidden="true">&bull;</span>';
        }

        return '<div class="marquee" aria-label="Highlights"><div class="marquee-track">' . $row . '</div></div>';
    }

    public function logos(): string
    {
        $names = ['Northgate', 'Verdant', 'Halcyon Co.', 'Brightpath', 'Kestrel', 'Ardent'];
        $items = '';
        foreach ($names as $name) {
            $items .= '<span class="logo-item">' . $this->h($name) . '</span>';
        }

        return <<<HTML
        <section class="logos">
          <div class="container">
            <span class="logos-label">Trusted by teams across the region</span>
            <div class="logos-inner" data-reveal>{$items}</div>
          </div>
        </section>
        HTML;
    }

    public function slider(): string
    {
        $slides = '';
        $dots = '';
        $captions = [
            $this->spec['services']['items'][0]['title'] ?? 'Our work',
            $this->spec['services']['items'][1]['title'] ?? 'In detail',
            $this->spec['about']['title'],
        ];

        foreach ($captions as $i => $caption) {
            $visual = $this->visual($i, 'slider-visual', $this->h($caption));
            $slides .= '<div class="slider-slide">' . $visual
                     . '<div class="slider-caption"><h3>' . $this->h($caption) . '</h3></div></div>';
            $dots .= '<button type="button" role="tab" aria-selected="' . ($i === 0 ? 'true' : 'false')
                   . '" aria-label="Slide ' . ($i + 1) . '"></button>';
        }

        return <<<HTML
        <section class="section" id="showcase">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">A closer look</h2>
              <p class="section-sub">A few highlights from {$this->h($this->spec['name'])}.</p>
            </header>
            <div class="slider" tabindex="0" aria-roledescription="carousel" data-reveal>
              <div class="slider-track">{$slides}</div>
              <button class="slider-btn slider-prev" type="button" aria-label="Previous slide">&#8249;</button>
              <button class="slider-btn slider-next" type="button" aria-label="Next slide">&#8250;</button>
              <div class="slider-dots" role="tablist">{$dots}</div>
            </div>
          </div>
        </section>
        HTML;
    }

    public function process(): string
    {
        $steps = [
            ['Get in touch', 'Tell us what you need. We reply within one working day with honest first thoughts.'],
            ['Agree the plan', 'You get a written scope and a fixed price before any work starts.'],
            ['We do the work', 'One point of contact throughout, with updates you do not have to chase.'],
            ['Hand over', 'Everything delivered, explained and supported after the job is finished.'],
        ];

        $cards = '';
        foreach ($steps as [$title, $text]) {
            $cards .= '<article class="step" data-reveal><h3>' . $this->h($title) . '</h3><p>'
                    . $this->h($text) . '</p></article>';
        }

        return <<<HTML
        <section class="section" id="process">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">How it works</h2>
              <p class="section-sub">Four steps, no surprises.</p>
            </header>
            <div class="steps">{$cards}</div>
          </div>
        </section>
        HTML;
    }

    public function timeline(): string
    {
        $year = (int) date('Y');
        $entries = [
            [$year - 12, 'Opened our doors', 'Started with two people, one room and a short list of clients.'],
            [$year - 7,  'Grew the team',    'Brought specialists in-house so nothing had to be subcontracted.'],
            [$year - 3,  'Moved premises',   'A bigger space, built around how we actually work day to day.'],
            [$year,      'Where we are now', 'Still small enough that you deal with the people doing the work.'],
        ];

        $items = '';
        foreach ($entries as [$when, $title, $text]) {
            $items .= '<div class="tl-item" data-reveal><span class="tl-year">' . $when . '</span><h3>'
                    . $this->h($title) . '</h3><p>' . $this->h($text) . '</p></div>';
        }

        return <<<HTML
        <section class="section section-alt" id="timeline">
          <div class="container narrow">
            <header class="section-head" data-reveal>
              <h2 class="section-title">How we got here</h2>
              <p class="section-sub">The short version.</p>
            </header>
            <div class="tl">{$items}</div>
          </div>
        </section>
        HTML;
    }

    public function newsletter(): string
    {
        return <<<HTML
        <section class="section" id="newsletter">
          <div class="container narrow">
            <div class="newsletter" data-reveal>
              <h2 class="section-title">Stay in the loop</h2>
              <p class="section-sub">Occasional updates from {$this->h($this->spec['name'])}. No spam, unsubscribe any time.</p>
              <form class="newsletter-form" id="newsletterForm" novalidate>
                <label class="sr-only" for="newsletterEmail">Email address</label>
                <input type="email" id="newsletterEmail" name="email" placeholder="you@example.com" required>
                <button class="btn btn-primary" type="submit">Subscribe</button>
              </form>
            </div>
          </div>
        </section>
        HTML;
    }

    public function hoursPanel(): string
    {
        $rows = [
            'Monday'    => '9:00 – 17:30', 'Tuesday'  => '9:00 – 17:30',
            'Wednesday' => '9:00 – 17:30', 'Thursday' => '9:00 – 19:00',
            'Friday'    => '9:00 – 17:30', 'Saturday' => '10:00 – 16:00',
            'Sunday'    => 'Closed',
        ];
        $today = date('l');

        $items = '';
        foreach ($rows as $day => $time) {
            $class = $day === $today ? ' class="is-today"' : '';
            $items .= '<div><dt' . $class . '>' . $day . '</dt><dd' . $class . '>' . $time . '</dd></div>';
        }

        return '<h3>Opening hours</h3><dl class="hours">' . $items . '</dl>';
    }

    public function mapPanel(): string
    {
        return '<div class="map" role="img" aria-label="Map showing '
             . $this->h($this->spec['contact']['address']) . '">'
             . '<span class="map-pin"></span>'
             . '<span class="map-label">' . $this->h($this->spec['name']) . '</span></div>';
    }

    public function socialLinks(): string
    {
        $handles = ['IG' => 'Instagram', 'FB' => 'Facebook', 'IN' => 'LinkedIn', 'X' => 'X'];
        $links = '';
        foreach ($handles as $short => $label) {
            $links .= '<a href="#" aria-label="' . $label . '" rel="noopener">' . $short . '</a>';
        }

        return '<div class="social">' . $links . '</div>';
    }

    /** Fixed-position chrome, appended just before </body>. */
    public function floatingChrome(): string
    {
        $out = '';

        if ($this->hasComponent('sticky_cta')) {
            $out .= '<a class="btn btn-primary sticky-cta" href="' . $this->link('contact') . '">'
                  . $this->h($this->spec['hero']['cta']) . '</a>' . "\n";
        }
        if ($this->hasComponent('chat')) {
            $out .= '<a class="chat-btn" href="https://wa.me/" target="_blank" rel="noopener">'
                  . '<span aria-hidden="true">&#9993;</span> Chat with us</a>' . "\n";
        }
        if ($this->hasComponent('back_to_top')) {
            $out .= '<button class="to-top" type="button" aria-label="Back to top">&uarr;</button>' . "\n";
        }
        if ($this->hasComponent('cookie')) {
            $out .= '<div class="cookie" role="region" aria-label="Cookie notice" hidden>'
                  . '<p>We use a couple of cookies to see which pages people find useful. Nothing is sold on.</p>'
                  . '<button class="btn btn-primary btn-sm" type="button">Fine by me</button>'
                  . '</div>' . "\n";
        }

        return $out;
    }

    private function pageHeader(string $title, string $sub): string
    {
        return <<<HTML
        <section class="page-header">
          <div class="container" data-reveal>
            <h1>{$this->h($title)}</h1>
            <p>{$this->h($sub)}</p>
          </div>
        </section>
        HTML;
    }

    private function stats(): string
    {
        $items = '';
        foreach ($this->spec['stats'] as $stat) {
            $items .= '<div class="stat" data-reveal><span class="stat-value">' . $this->h($stat['value'])
                    . '</span><span class="stat-label">' . $this->h($stat['label']) . '</span></div>';
        }

        return '<section class="section section-tight stats-band" id="stats"><div class="container stats-grid">'
             . $items . '</div></section>';
    }

    private function about(bool $anchor, bool $teaser = false): string
    {
        $a = $this->spec['about'];
        $paras = '';
        $bodies = $teaser ? array_slice($a['body'], 0, 1) : $a['body'];
        foreach ($bodies as $paragraph) {
            $paras .= '<p>' . $this->h($paragraph) . '</p>';
        }

        $points = '';
        foreach ($a['points'] as $point) {
            $points .= '<li>' . $this->h($point) . '</li>';
        }

        $more = $teaser
            ? '<p><a class="text-link" href="' . $this->link('about') . '">Read more about us &rarr;</a></p>'
            : '';

        $visual = $this->visual(1, 'about-visual', 'Inside ' . $this->spec['name']);
        $id = $anchor ? ' id="about"' : ' id="about"';

        return <<<HTML
        <section class="section"{$id}>
          <div class="container split">
            <div class="split-media" data-reveal>{$visual}</div>
            <div class="split-copy" data-reveal>
              <p class="eyebrow">About {$this->h($this->spec['name'])}</p>
              <h2 class="section-title">{$this->h($a['title'])}</h2>
              <p class="lead">{$this->h($a['lead'])}</p>
              {$paras}
              <ul class="ticks">{$points}</ul>
              {$more}
            </div>
          </div>
        </section>
        HTML;
    }

    private function services(bool $anchor, bool $teaser): string
    {
        $s = $this->spec['services'];
        $items = $teaser ? array_slice($s['items'], 0, 3) : $s['items'];

        $cards = '';
        $n = 1;
        foreach ($items as $item) {
            $num = str_pad((string) $n++, 2, '0', STR_PAD_LEFT);
            $cards .= <<<HTML
            <article class="card" data-reveal>
              <span class="card-num">{$num}</span>
              <h3>{$this->h($item['title'])}</h3>
              <p>{$this->h($item['text'])}</p>
            </article>
            HTML;
        }

        $more = $teaser
            ? '<div class="section-foot"><a class="btn btn-outline" href="' . $this->link('services') . '">See everything we do</a></div>'
            : '';

        return <<<HTML
        <section class="section section-alt" id="services">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">{$this->h($s['title'])}</h2>
              <p class="section-sub">{$this->h($s['sub'])}</p>
            </header>
            <div class="card-grid">{$cards}</div>
            {$more}
          </div>
        </section>
        HTML;
    }

    private function gallery(): string
    {
        $tiles = '';
        for ($i = 0; $i < 6; $i++) {
            $tiles .= '<figure class="tile" data-reveal>'
                    . $this->visual($i, 'tile-visual', 'Gallery image ' . ($i + 1))
                    . '</figure>';
        }

        return <<<HTML
        <section class="section" id="gallery">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">A look around</h2>
              <p class="section-sub">A few recent pictures from {$this->h($this->spec['name'])}.</p>
            </header>
            <div class="tile-grid">{$tiles}</div>
          </div>
        </section>
        HTML;
    }

    private function testimonials(): string
    {
        $cards = '';
        foreach ($this->spec['testimonials'] as $item) {
            $initials = $this->initials($item['name']);
            $cards .= <<<HTML
            <blockquote class="quote" data-reveal>
              <p>&ldquo;{$this->h($item['quote'])}&rdquo;</p>
              <footer>
                <span class="quote-avatar" aria-hidden="true">{$this->h($initials)}</span>
                <span><strong>{$this->h($item['name'])}</strong><em>{$this->h($item['role'])}</em></span>
              </footer>
            </blockquote>
            HTML;
        }

        return <<<HTML
        <section class="section section-alt" id="testimonials">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">What people say</h2>
              <p class="section-sub">Unedited, and from people happy to be named.</p>
            </header>
            <div class="quote-grid">{$cards}</div>
          </div>
        </section>
        HTML;
    }

    private function pricing(): string
    {
        $cards = '';
        foreach ($this->spec['pricing'] as $plan) {
            $features = '';
            foreach ($plan['features'] as $feature) {
                $features .= '<li>' . $this->h($feature) . '</li>';
            }
            $featured = !empty($plan['featured']);
            $class = $featured ? 'plan plan-featured' : 'plan';
            $badge = $featured ? '<span class="plan-badge">Most popular</span>' : '';
            $btn = $featured ? 'btn btn-primary' : 'btn btn-outline';

            $cards .= <<<HTML
            <article class="{$class}" data-reveal>
              {$badge}
              <h3>{$this->h($plan['name'])}</h3>
              <p class="plan-price">{$this->h($plan['price'])}<span>{$this->h($plan['note'])}</span></p>
              <ul class="ticks">{$features}</ul>
              <a class="{$btn}" href="{$this->link('contact')}">Get started</a>
            </article>
            HTML;
        }

        return <<<HTML
        <section class="section" id="pricing">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">Pricing</h2>
              <p class="section-sub">Clear numbers, agreed before anything starts.</p>
            </header>
            <div class="plan-grid">{$cards}</div>
          </div>
        </section>
        HTML;
    }

    private function team(): string
    {
        $cards = '';
        foreach ($this->spec['team'] as $person) {
            $cards .= <<<HTML
            <article class="person" data-reveal>
              <span class="person-avatar" aria-hidden="true">{$this->h($this->initials($person['name']))}</span>
              <h3>{$this->h($person['name'])}</h3>
              <p class="person-role">{$this->h($person['role'])}</p>
              <p>{$this->h($person['bio'])}</p>
            </article>
            HTML;
        }

        return <<<HTML
        <section class="section section-alt" id="team">
          <div class="container">
            <header class="section-head" data-reveal>
              <h2 class="section-title">The people you'll deal with</h2>
              <p class="section-sub">A small team, and you'll know all of them.</p>
            </header>
            <div class="person-grid">{$cards}</div>
          </div>
        </section>
        HTML;
    }

    private function faq(): string
    {
        $items = '';
        foreach ($this->spec['faq'] as $entry) {
            $items .= <<<HTML
            <details class="faq-item" data-reveal>
              <summary>{$this->h($entry['q'])}</summary>
              <p>{$this->h($entry['a'])}</p>
            </details>
            HTML;
        }

        return <<<HTML
        <section class="section" id="faq">
          <div class="container narrow">
            <header class="section-head" data-reveal>
              <h2 class="section-title">Common questions</h2>
              <p class="section-sub">If yours isn't here, just ask.</p>
            </header>
            <div class="faq">{$items}</div>
          </div>
        </section>
        HTML;
    }

    private function ctaBand(): string
    {
        return <<<HTML
        <section class="cta-band">
          <div class="container cta-inner" data-reveal>
            <div>
              <h2>{$this->h($this->spec['tagline'])}</h2>
              <p>Tell us what you need. We'll reply within one working day.</p>
            </div>
            <a class="btn btn-invert btn-lg" href="{$this->link('contact')}">{$this->h($this->spec['hero']['cta'])}</a>
          </div>
        </section>
        HTML;
    }

    // --- Chrome ------------------------------------------------------------

    /** @return array<string,string> label => href */
    private function navItems(): array
    {
        if ($this->siteType === 'landing') {
            return ['About' => '#about', 'Services' => '#services', 'Contact' => '#contact'];
        }
        return [
            'Home' => $this->link('index'),
            'About' => $this->link('about'),
            'Services' => $this->link('services'),
            'Contact' => $this->link('contact'),
        ];
    }

    private function link(string $page): string
    {
        if ($this->siteType === 'landing') {
            return $page === 'index' ? 'index.html' : '#' . $page;
        }
        return ($page === 'index' ? 'index' : $page) . '.' . $this->ext;
    }

    private function header(string $active): string
    {
        $links = '';
        foreach ($this->navItems() as $label => $href) {
            $isActive = strtolower($label) === $active || ($active === 'home' && $label === 'Home');
            $links .= '<a href="' . $href . '"' . ($isActive ? ' class="is-active" aria-current="page"' : '') . '>'
                    . $this->h($label) . '</a>';
        }

        $ctaHref = $this->siteType === 'landing' ? '#contact' : $this->link('contact');

        $search = $this->hasComponent('search')
            ? '<form class="site-search" role="search" onsubmit="return false"><label class="sr-only" for="q">Search</label>'
              . '<input type="search" id="q" name="q" placeholder="Search…"></form>'
            : '';

        $toggle = $this->hasComponent('theme_toggle')
            ? '<button class="theme-toggle" type="button" aria-label="Switch colour theme">&#9790;</button>'
            : '';

        return <<<HTML
        <header class="site-header" id="siteHeader">
          <div class="container header-inner">
            <a class="brand" href="{$this->link('index')}">
              <span class="brand-mark" aria-hidden="true"></span>
              <span>{$this->h($this->spec['name'])}</span>
            </a>
            <nav class="nav" id="nav" aria-label="Main navigation">{$links}</nav>
            <div class="header-side">
              {$search}{$toggle}
              <a class="btn btn-primary btn-sm header-cta" href="{$ctaHref}">{$this->h($this->spec['hero']['cta'])}</a>
              <button class="burger" id="burger" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav">
                <span></span><span></span><span></span>
              </button>
            </div>
          </div>
        </header>
        HTML;
    }

    private function footer(): string
    {
        $c = $this->spec['contact'];
        $links = '';
        foreach ($this->navItems() as $label => $href) {
            $links .= '<a href="' . $href . '">' . $this->h($label) . '</a>';
        }
        $year = date('Y');
        $social = $this->hasComponent('social') ? $this->socialLinks() : '';

        return <<<HTML
        <footer class="site-footer">
          <div class="container footer-grid">
            <div>
              <a class="brand brand-light" href="{$this->link('index')}">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>{$this->h($this->spec['name'])}</span>
              </a>
              <p>{$this->h($this->spec['tagline'])}</p>
              {$social}
            </div>
            <nav aria-label="Footer navigation"><h4>Pages</h4>{$links}</nav>
            <div>
              <h4>Find us</h4>
              <p>{$this->h($c['address'])}</p>
              <p><a href="mailto:{$this->h($c['email'])}">{$this->h($c['email'])}</a><br>
                 <a href="tel:{$this->h(preg_replace('/[^+\d]/', '', $c['phone']))}">{$this->h($c['phone'])}</a></p>
              <p>{$this->h($c['hours'])}</p>
            </div>
          </div>
          <div class="container footer-base">
            <span>&copy; {$year} {$this->h($this->spec['name'])}. All rights reserved.</span>
            <span>Built with SiteForge</span>
          </div>
        </footer>
        HTML;
    }

    private function page(string $active, string $body): string
    {
        $title = $active === 'home'
            ? $this->spec['name'] . ' — ' . $this->spec['tagline']
            : ucfirst($active) . ' — ' . $this->spec['name'];

        return $this->documentOpen($title) . "\n" . $this->header($active) . "\n<main id=\"main\">\n"
             . $body . "\n</main>\n" . $this->footer() . "\n" . $this->documentClose();
    }

    private function documentOpen(string $title): string
    {
        $desc = $this->spec['hero']['sub'];
        $css = $this->assetBase . 'css/styles.css';
        $theme = $this->spec['dark'] ? '#0b1020' : $this->t['primary'];

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$this->h($title)}</title>
        <meta name="description" content="{$this->h(mb_substr($desc, 0, 155))}">
        <meta name="theme-color" content="{$theme}">
        <link rel="stylesheet" href="{$css}">
        </head>
        <body>
        <a class="skip-link" href="#main">Skip to content</a>
        HTML;
    }

    private function documentClose(): string
    {
        return $this->floatingChrome()
             . '<script src="' . $this->assetBase . "js/main.js\"></script>\n</body>\n</html>\n";
    }

    // --- PHP variant -------------------------------------------------------

    private function phpPage(string $active, string $body): string
    {
        $title = $active === 'home'
            ? $this->spec['name'] . ' — ' . $this->spec['tagline']
            : ucfirst($active) . ' — ' . $this->spec['name'];

        return "<?php\n\$pageTitle = " . var_export($title, true) . ";\n"
             . "\$activePage = " . var_export($active, true) . ";\n"
             . "require __DIR__ . '/includes/header.php';\n?>\n"
             . $body . "\n<?php require __DIR__ . '/includes/footer.php'; ?>\n";
    }

    private function phpHeader(): string
    {
        $nav = '';
        foreach ($this->navItems() as $label => $href) {
            $key = strtolower($label) === 'home' ? 'home' : strtolower($label);
            $nav .= "        <a href=\"{$href}\"<?= \$activePage === '{$key}' ? ' class=\"is-active\" aria-current=\"page\"' : '' ?>>"
                  . $this->h($label) . "</a>\n";
        }

        $desc = $this->h(mb_substr($this->spec['hero']['sub'], 0, 155));
        $theme = $this->spec['dark'] ? '#0b1020' : $this->t['primary'];
        $cta = $this->h($this->spec['hero']['cta']);
        $name = $this->h($this->spec['name']);

        return <<<PHP
        <?php
        /** Shared page header. Set \$pageTitle and \$activePage before requiring this file. */
        \$pageTitle  = \$pageTitle  ?? '{$name}';
        \$activePage = \$activePage ?? '';
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars(\$pageTitle, ENT_QUOTES) ?></title>
        <meta name="description" content="{$desc}">
        <meta name="theme-color" content="{$theme}">
        <link rel="stylesheet" href="assets/css/styles.css">
        </head>
        <body>
        <a class="skip-link" href="#main">Skip to content</a>

        <header class="site-header" id="siteHeader">
          <div class="container header-inner">
            <a class="brand" href="index.php">
              <span class="brand-mark" aria-hidden="true"></span>
              <span>{$name}</span>
            </a>
            <nav class="nav" id="nav" aria-label="Main navigation">
        {$nav}    </nav>
            <div class="header-side">
              <a class="btn btn-primary btn-sm header-cta" href="contact.php">{$cta}</a>
              <button class="burger" id="burger" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav">
                <span></span><span></span><span></span>
              </button>
            </div>
          </div>
        </header>

        <main id="main">
        PHP;
    }

    private function phpFooter(): string
    {
        return "</main>\n\n" . $this->footer() . "\n\n" . $this->floatingChrome()
             . "<script src=\"assets/js/main.js\"></script>\n</body>\n</html>\n";
    }

    /** Contact page with a real server-side handler. */
    private function phpContactPage(): string
    {
        $email = $this->spec['contact']['email'];
        $title = 'Contact — ' . $this->spec['name'];
        $body = $this->contactBody(true);

        return <<<PHP
        <?php
        /**
         * Contact page. Validates the form server-side and emails the enquiry.
         * Update \$recipient below to the address that should receive messages.
         */
        \$recipient = '{$email}';

        \$errors = [];
        \$sent   = false;
        \$old    = ['name' => '', 'email' => '', 'message' => ''];

        if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
            \$old['name']    = trim(\$_POST['name'] ?? '');
            \$old['email']   = trim(\$_POST['email'] ?? '');
            \$old['message'] = trim(\$_POST['message'] ?? '');

            if (\$old['name'] === '' || mb_strlen(\$old['name']) > 80) {
                \$errors[] = 'Please enter your name.';
            }
            if (!filter_var(\$old['email'], FILTER_VALIDATE_EMAIL)) {
                \$errors[] = 'Please enter a valid email address.';
            }
            if (mb_strlen(\$old['message']) < 10) {
                \$errors[] = 'Please tell us a little more (at least 10 characters).';
            }
            // Reject header injection attempts in the name field.
            if (preg_match('/[\\r\\n]/', \$old['name'] . \$old['email'])) {
                \$errors[] = 'That submission looked malformed. Please try again.';
            }

            if (!\$errors) {
                \$subject = 'Website enquiry from ' . \$old['name'];
                \$body    = "Name: {\$old['name']}\\nEmail: {\$old['email']}\\n\\n{\$old['message']}\\n";
                \$headers = 'From: no-reply@' . (\$_SERVER['HTTP_HOST'] ?? 'localhost') . "\\r\\n"
                         . 'Reply-To: ' . \$old['email'] . "\\r\\n"
                         . 'Content-Type: text/plain; charset=UTF-8';

                @mail(\$recipient, \$subject, \$body, \$headers);

                \$sent = true;
                \$old  = ['name' => '', 'email' => '', 'message' => ''];
            }
        }

        \$pageTitle  = '{$title}';
        \$activePage = 'contact';
        require __DIR__ . '/includes/header.php';
        ?>
        {$body}
        <?php require __DIR__ . '/includes/footer.php'; ?>
        PHP;
    }

    // --- Visual placeholders -----------------------------------------------

    /**
     * Either a real uploaded image or a generated gradient panel, so a site
     * never ships with a broken image icon or an external placeholder URL.
     */
    private function visual(int $index, string $class, string $alt): string
    {
        $images = $this->spec['images'] ?? [];
        if ($images !== []) {
            $path = $images[$index % count($images)];
            return '<img class="' . $class . '" src="' . $this->h($path) . '" alt="' . $this->h($alt) . '" loading="lazy">';
        }

        $rotations = [0, 45, 120, 200, 275, 330];
        $angle = $rotations[$index % count($rotations)];

        return '<div class="' . $class . ' visual" style="--visual-angle:' . $angle . 'deg" role="img" aria-label="'
             . $this->h($alt) . '"><span class="visual-mark">' . $this->h($this->initials($this->spec['name']))
             . '</span></div>';
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letter = mb_substr(preg_replace('/[^\p{L}]/u', '', $part) ?: '', 0, 1);
            $initials .= mb_strtoupper($letter);
        }
        return $initials !== '' ? $initials : 'W';
    }

    private function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function readme(): string
    {
        $type = site_type_label($this->siteType);
        $run = $this->siteType === 'php'
            ? "This is a PHP site. Serve it with any PHP 8 server, for example:\n\n    php -S localhost:8000\n\nThen open http://localhost:8000 in a browser."
            : "This is a static site. Open index.html in a browser, or upload the whole\nfolder to any web host.";

        return <<<TXT
        {$this->spec['name']}
        {$this->spec['tagline']}

        Built with SiteForge — output format: {$type}

        {$run}

        Files
        -----
        Pages          the .{$this->ext} files in this folder
        Styles         {$this->assetBase}css/styles.css  (colours are CSS variables at the top)
        Scripts        {$this->assetBase}js/main.js

        Everything is self-contained: no build step, no external fonts, no CDNs.
        TXT;
    }
    /** The generated stylesheet. */
    private function css(): string
    {
        return CssKit::build($this->t, $this->spec);
    }

    /** The generated behaviour script. */
    private function js(): string
    {
        return CssKit::script($this->spec);
    }
}
