<?php
declare(strict_types=1);

namespace SiteForge;

/**
 * Renders a spec as a Bootstrap 5 site — real Bootstrap components and grid,
 * loaded from jsDelivr, with a small stylesheet that recolours it to the
 * project's palette via Bootstrap's own CSS variables.
 */
final class BootstrapRenderer
{
    private const BS = '5.3.3';

    private array $spec;
    private array $t;

    private ?Renderer $widgets = null;

    public function __construct(array $spec)
    {
        $this->spec = $spec;
        $this->t = Blueprint::theme($spec);
    }

    /**
     * The optional components (slider, marquee, timeline …) are framework
     * agnostic — they carry their own classes and CSS — so both renderers
     * share one implementation instead of duplicating the markup.
     * 'html' matches this build's page names, so internal links line up.
     */
    private function widgets(): Renderer
    {
        return $this->widgets ??= new Renderer($this->spec, 'html');
    }

    private function hasComponent(string $key): bool
    {
        return in_array($key, $this->spec['components'] ?? [], true);
    }

    /** @return array<string,string> */
    public function render(): array
    {
        return [
            'index.html'    => $this->page('home', $this->home()),
            'about.html'    => $this->page('about', $this->about()),
            'services.html' => $this->page('services', $this->services()),
            'contact.html'  => $this->page('contact', $this->contact()),
            'css/styles.css' => $this->css(),
            'js/main.js'     => $this->js(),
            'README.txt'     => $this->readme(),
        ];
    }

    // --- Pages -------------------------------------------------------------

    private function home(): string
    {
        $w = $this->widgets();

        $out = $this->hero();
        if ($this->hasComponent('marquee')) $out .= $w->marquee();
        if ($this->hasComponent('logos'))   $out .= $w->logos();
        if ($this->has('stats')) $out .= $this->stats();
        $out .= $this->aboutSplit(true);
        if ($this->hasComponent('slider'))   $out .= $w->slider();
        if ($this->hasComponent('timeline')) $out .= $w->timeline();
        $out .= $this->serviceCards(3, true);
        if ($this->hasComponent('process'))  $out .= $w->process();
        if ($this->has('gallery'))      $out .= $this->gallery();
        if ($this->has('testimonials')) $out .= $this->testimonials();
        if ($this->has('cta'))          $out .= $this->ctaBand();
        if ($this->hasComponent('newsletter')) $out .= $w->newsletter();
        return $out;
    }

    private function about(): string
    {
        $out = $this->pageHeader($this->spec['about']['title'], $this->spec['about']['lead']);
        $out .= $this->aboutSplit(false);
        if ($this->hasComponent('timeline')) $out .= $this->widgets()->timeline();
        if ($this->has('stats')) $out .= $this->stats();
        if ($this->has('team'))  $out .= $this->team();
        $out .= $this->ctaBand();
        return $out;
    }

    private function services(): string
    {
        $out = $this->pageHeader($this->spec['services']['title'], $this->spec['services']['sub']);
        $out .= $this->serviceCards(count($this->spec['services']['items']), false);
        if ($this->hasComponent('process')) $out .= $this->widgets()->process();
        if ($this->has('pricing')) $out .= $this->pricing();
        if ($this->has('faq'))     $out .= $this->faq();
        $out .= $this->ctaBand();
        return $out;
    }

    private function contact(): string
    {
        $c = $this->spec['contact'];
        $tel = $this->h(preg_replace('/[^+\d]/', '', $c['phone']));

        $w = $this->widgets();
        $extras = '';
        if ($this->hasComponent('hours'))  $extras .= '<hr>' . $w->hoursPanel();
        if ($this->hasComponent('social')) $extras .= '<hr><h3 class="h6">Follow us</h3>' . $w->socialLinks();
        if ($this->hasComponent('map'))    $extras .= '<hr><h3 class="h6">Find us</h3>' . $w->mapPanel();

        return $this->pageHeader('Get in touch', 'Tell us what you need and we will reply within one working day.')
            . <<<HTML
        <section class="py-5">
          <div class="container">
            <div class="row g-5">
              <div class="col-lg-7">
                <h2 class="h3 mb-3">Send us a message</h2>
                <form id="contactForm" novalidate>
                  <div class="mb-3">
                    <label class="form-label" for="name">Your name</label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name" required maxlength="80">
                    <div class="invalid-feedback">Please enter your name.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required maxlength="120">
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="message">How can we help?</label>
                    <textarea class="form-control form-control-lg" id="message" name="message" rows="5" required minlength="10" maxlength="2000"></textarea>
                    <div class="invalid-feedback">Please tell us a little more (at least 10 characters).</div>
                  </div>
                  <button class="btn btn-primary btn-lg" type="submit">Send message</button>
                  <div class="alert alert-success mt-3 d-none" id="formNote" role="status">
                    Thanks — your message has been captured.
                  </div>
                </form>
              </div>
              <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                  <div class="card-body p-4">
                    <h3 class="h5 mb-4">Contact details</h3>
                    <p class="mb-3"><span class="contact-label">Email</span><br>
                      <a href="mailto:{$this->h($c['email'])}">{$this->h($c['email'])}</a></p>
                    <p class="mb-3"><span class="contact-label">Phone</span><br>
                      <a href="tel:{$tel}">{$this->h($c['phone'])}</a></p>
                    <p class="mb-3"><span class="contact-label">Address</span><br>{$this->h($c['address'])}</p>
                    <p class="mb-0"><span class="contact-label">Hours</span><br>{$this->h($c['hours'])}</p>
                    {$extras}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
        HTML;
    }

    // --- Sections ----------------------------------------------------------

    private function has(string $section): bool
    {
        return in_array($section, $this->spec['sections'] ?? [], true);
    }

    private function hero(): string
    {
        $h = $this->spec['hero'];
        return <<<HTML
        <section class="hero py-5">
          <div class="container py-lg-5">
            <div class="row align-items-center g-5">
              <div class="col-lg-6" data-reveal>
                <p class="eyebrow mb-3">{$this->h($h['eyebrow'])}</p>
                <h1 class="display-4 fw-bold mb-3">{$this->h($h['title'])}</h1>
                <p class="lead mb-4">{$this->h($h['sub'])}</p>
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-primary btn-lg" href="contact.html">{$this->h($h['cta'])}</a>
                  <a class="btn btn-outline-secondary btn-lg" href="services.html">{$this->h($h['cta2'])}</a>
                </div>
              </div>
              <div class="col-lg-6" data-reveal>{$this->visual(0, 'ratio ratio-4x3')}</div>
            </div>
          </div>
        </section>
        HTML;
    }

    private function pageHeader(string $title, string $sub): string
    {
        return <<<HTML
        <section class="page-header py-5 text-center">
          <div class="container py-lg-4" data-reveal>
            <h1 class="display-5 fw-bold mb-3">{$this->h($title)}</h1>
            <p class="lead mb-0 mx-auto" style="max-width:46rem">{$this->h($sub)}</p>
          </div>
        </section>
        HTML;
    }

    private function stats(): string
    {
        $cols = '';
        foreach ($this->spec['stats'] as $stat) {
            $cols .= '<div class="col-md-4" data-reveal><div class="stat-value">' . $this->h($stat['value'])
                   . '</div><div class="text-secondary">' . $this->h($stat['label']) . '</div></div>';
        }
        return '<section class="border-top border-bottom bg-body-tertiary py-5"><div class="container">'
             . '<div class="row g-4 text-center">' . $cols . '</div></div></section>';
    }

    private function aboutSplit(bool $teaser): string
    {
        $a = $this->spec['about'];
        $paras = '';
        foreach ($teaser ? array_slice($a['body'], 0, 1) : $a['body'] as $paragraph) {
            $paras .= '<p>' . $this->h($paragraph) . '</p>';
        }
        $points = '';
        foreach ($a['points'] as $point) {
            $points .= '<li class="list-group-item bg-transparent border-0 ps-0">'
                     . '<span class="tick" aria-hidden="true"></span>' . $this->h($point) . '</li>';
        }
        $more = $teaser ? '<a class="fw-semibold" href="about.html">Read more about us &rarr;</a>' : '';

        return <<<HTML
        <section class="py-5" id="about">
          <div class="container py-lg-4">
            <div class="row align-items-center g-5">
              <div class="col-lg-5" data-reveal>{$this->visual(1, 'ratio ratio-4x3')}</div>
              <div class="col-lg-7" data-reveal>
                <p class="eyebrow mb-2">About {$this->h($this->spec['name'])}</p>
                <h2 class="h1 mb-3">{$this->h($a['title'])}</h2>
                <p class="lead">{$this->h($a['lead'])}</p>
                {$paras}
                <ul class="list-group list-group-flush mb-3">{$points}</ul>
                {$more}
              </div>
            </div>
          </div>
        </section>
        HTML;
    }

    private function serviceCards(int $limit, bool $teaser): string
    {
        $s = $this->spec['services'];
        $cards = '';
        foreach (array_slice($s['items'], 0, $limit) as $index => $item) {
            $num = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $cards .= <<<HTML
            <div class="col-md-6 col-lg-4" data-reveal>
              <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                  <span class="badge rounded-pill badge-num mb-3">{$num}</span>
                  <h3 class="h5 card-title">{$this->h($item['title'])}</h3>
                  <p class="card-text text-secondary mb-0">{$this->h($item['text'])}</p>
                </div>
              </div>
            </div>
            HTML;
        }
        $more = $teaser
            ? '<div class="text-center mt-4"><a class="btn btn-outline-secondary" href="services.html">See everything we do</a></div>'
            : '';

        return <<<HTML
        <section class="py-5 bg-body-tertiary" id="services">
          <div class="container py-lg-4">
            <div class="text-center mx-auto mb-5" style="max-width:660px" data-reveal>
              <h2 class="h1 mb-2">{$this->h($s['title'])}</h2>
              <p class="lead mb-0">{$this->h($s['sub'])}</p>
            </div>
            <div class="row g-4">{$cards}</div>
            {$more}
          </div>
        </section>
        HTML;
    }

    private function gallery(): string
    {
        $cols = '';
        for ($i = 0; $i < 6; $i++) {
            $cols .= '<div class="col-6 col-md-4" data-reveal>' . $this->visual($i, 'ratio ratio-1x1') . '</div>';
        }
        return '<section class="py-5"><div class="container py-lg-4">'
             . '<div class="text-center mb-5" data-reveal><h2 class="h1 mb-2">A look around</h2>'
             . '<p class="lead mb-0">A few recent pictures from ' . $this->h($this->spec['name']) . '.</p></div>'
             . '<div class="row g-3">' . $cols . '</div></div></section>';
    }

    private function testimonials(): string
    {
        $cols = '';
        foreach ($this->spec['testimonials'] as $item) {
            $cols .= <<<HTML
            <div class="col-lg-6" data-reveal>
              <figure class="card h-100 border-0 shadow-sm">
                <blockquote class="card-body p-4 mb-0">
                  <p class="fs-5 mb-0">&ldquo;{$this->h($item['quote'])}&rdquo;</p>
                </blockquote>
                <figcaption class="card-footer bg-transparent border-0 px-4 pb-4 d-flex align-items-center gap-3">
                  <span class="avatar" aria-hidden="true">{$this->h($this->initials($item['name']))}</span>
                  <span><strong class="d-block">{$this->h($item['name'])}</strong>
                        <small class="text-secondary">{$this->h($item['role'])}</small></span>
                </figcaption>
              </figure>
            </div>
            HTML;
        }
        return '<section class="py-5 bg-body-tertiary"><div class="container py-lg-4">'
             . '<div class="text-center mb-5" data-reveal><h2 class="h1 mb-2">What people say</h2>'
             . '<p class="lead mb-0">Unedited, and from people happy to be named.</p></div>'
             . '<div class="row g-4">' . $cols . '</div></div></section>';
    }

    private function pricing(): string
    {
        $cols = '';
        foreach ($this->spec['pricing'] as $plan) {
            $features = '';
            foreach ($plan['features'] as $feature) {
                $features .= '<li class="list-group-item bg-transparent border-0 ps-0">'
                           . '<span class="tick" aria-hidden="true"></span>' . $this->h($feature) . '</li>';
            }
            $featured = !empty($plan['featured']);
            $cardClass = $featured ? 'card h-100 border-primary shadow' : 'card h-100 border-0 shadow-sm';
            $badge = $featured ? '<span class="badge text-bg-primary position-absolute top-0 start-50 translate-middle">Most popular</span>' : '';
            $btn = $featured ? 'btn btn-primary w-100' : 'btn btn-outline-secondary w-100';

            $cols .= <<<HTML
            <div class="col-lg-4" data-reveal>
              <div class="{$cardClass} position-relative">
                {$badge}
                <div class="card-body p-4 d-flex flex-column">
                  <h3 class="h5">{$this->h($plan['name'])}</h3>
                  <p class="display-6 fw-bold mb-0">{$this->h($plan['price'])}</p>
                  <p class="text-secondary small mb-3">{$this->h($plan['note'])}</p>
                  <ul class="list-group list-group-flush mb-4">{$features}</ul>
                  <a class="{$btn} mt-auto" href="contact.html">Get started</a>
                </div>
              </div>
            </div>
            HTML;
        }
        return '<section class="py-5"><div class="container py-lg-4">'
             . '<div class="text-center mb-5" data-reveal><h2 class="h1 mb-2">Pricing</h2>'
             . '<p class="lead mb-0">Clear numbers, agreed before anything starts.</p></div>'
             . '<div class="row g-4 align-items-start">' . $cols . '</div></div></section>';
    }

    private function team(): string
    {
        $cols = '';
        foreach ($this->spec['team'] as $person) {
            $cols .= <<<HTML
            <div class="col-md-4" data-reveal>
              <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                  <span class="avatar avatar-lg mb-3" aria-hidden="true">{$this->h($this->initials($person['name']))}</span>
                  <h3 class="h5 mb-1">{$this->h($person['name'])}</h3>
                  <p class="text-primary fw-semibold small mb-2">{$this->h($person['role'])}</p>
                  <p class="text-secondary mb-0">{$this->h($person['bio'])}</p>
                </div>
              </div>
            </div>
            HTML;
        }
        return '<section class="py-5 bg-body-tertiary"><div class="container py-lg-4">'
             . '<div class="text-center mb-5" data-reveal><h2 class="h1 mb-2">The people you\'ll deal with</h2>'
             . '<p class="lead mb-0">A small team, and you\'ll know all of them.</p></div>'
             . '<div class="row g-4">' . $cols . '</div></div></section>';
    }

    private function faq(): string
    {
        $items = '';
        foreach ($this->spec['faq'] as $index => $entry) {
            $n = $index + 1;
            $show = $index === 0 ? ' show' : '';
            $collapsed = $index === 0 ? '' : ' collapsed';
            $expanded = $index === 0 ? 'true' : 'false';
            $items .= <<<HTML
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button{$collapsed}" type="button" data-bs-toggle="collapse"
                        data-bs-target="#faq{$n}" aria-expanded="{$expanded}" aria-controls="faq{$n}">
                  {$this->h($entry['q'])}
                </button>
              </h3>
              <div id="faq{$n}" class="accordion-collapse collapse{$show}" data-bs-parent="#faqAccordion">
                <div class="accordion-body">{$this->h($entry['a'])}</div>
              </div>
            </div>
            HTML;
        }
        return '<section class="py-5 bg-body-tertiary"><div class="container py-lg-4" style="max-width:820px">'
             . '<div class="text-center mb-5" data-reveal><h2 class="h1 mb-2">Common questions</h2>'
             . '<p class="lead mb-0">If yours isn\'t here, just ask.</p></div>'
             . '<div class="accordion" id="faqAccordion">' . $items . '</div></div></section>';
    }

    private function ctaBand(): string
    {
        return <<<HTML
        <section class="cta-band py-5">
          <div class="container py-lg-3">
            <div class="row align-items-center g-4" data-reveal>
              <div class="col-lg-8">
                <h2 class="h1 mb-2 text-white">{$this->h($this->spec['tagline'])}</h2>
                <p class="mb-0 text-white-50">Tell us what you need. We'll reply within one working day.</p>
              </div>
              <div class="col-lg-4 text-lg-end">
                <a class="btn btn-light btn-lg" href="contact.html">{$this->h($this->spec['hero']['cta'])}</a>
              </div>
            </div>
          </div>
        </section>
        HTML;
    }

    // --- Chrome ------------------------------------------------------------

    private function page(string $active, string $body): string
    {
        $name = $this->h($this->spec['name']);
        $title = $active === 'home'
            ? $name . ' — ' . $this->h($this->spec['tagline'])
            : ucfirst($active) . ' — ' . $name;
        $desc = $this->h(mb_substr($this->spec['hero']['sub'], 0, 155));
        $theme = !empty($this->spec['dark']) ? ' data-bs-theme="dark"' : '';
        $bs = self::BS;

        $links = '';
        foreach (['Home' => 'index.html', 'About' => 'about.html', 'Services' => 'services.html', 'Contact' => 'contact.html'] as $label => $href) {
            $isActive = strtolower($label) === $active;
            $links .= '<li class="nav-item"><a class="nav-link' . ($isActive ? ' active" aria-current="page' : '')
                    . '" href="' . $href . '">' . $this->h($label) . '</a></li>';
        }

        $c = $this->spec['contact'];
        $year = date('Y');

        $floating = $this->widgets()->floatingChrome();
        $footerSocial = $this->hasComponent('social') ? $this->widgets()->socialLinks() : '';

        $extras = '';
        if ($this->hasComponent('search')) {
            $extras .= '<li class="nav-item ms-lg-2"><form class="site-search" role="search" onsubmit="return false">'
                     . '<label class="sr-only visually-hidden" for="q">Search</label>'
                     . '<input type="search" id="q" placeholder="Search…"></form></li>';
        }
        if ($this->hasComponent('theme_toggle')) {
            $extras .= '<li class="nav-item ms-lg-2"><button class="theme-toggle" type="button" '
                     . 'aria-label="Switch colour theme">&#9790;</button></li>';
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en"{$theme}>
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title}</title>
        <meta name="description" content="{$desc}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@{$bs}/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="css/styles.css" rel="stylesheet">
        </head>
        <body>
        <a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>

        <nav class="navbar navbar-expand-lg sticky-top" id="siteNav">
          <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.html">
              <span class="brand-mark" aria-hidden="true"></span>{$name}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
              <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                {$links}
                {$extras}
                <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                  <a class="btn btn-primary" href="contact.html">{$this->h($this->spec['hero']['cta'])}</a>
                </li>
              </ul>
            </div>
          </div>
        </nav>

        <main id="main">
        {$body}
        </main>

        <footer class="site-footer pt-5">
          <div class="container">
            <div class="row g-4 pb-4">
              <div class="col-lg-5">
                <span class="navbar-brand d-flex align-items-center gap-2 text-white mb-2">
                  <span class="brand-mark" aria-hidden="true"></span>{$name}
                </span>
                <p class="text-white-50 mb-3">{$this->h($this->spec['tagline'])}</p>
                {$footerSocial}
              </div>
              <div class="col-6 col-lg-3">
                <h4 class="footer-heading">Pages</h4>
                <ul class="list-unstyled">
                  <li><a href="index.html">Home</a></li>
                  <li><a href="about.html">About</a></li>
                  <li><a href="services.html">Services</a></li>
                  <li><a href="contact.html">Contact</a></li>
                </ul>
              </div>
              <div class="col-6 col-lg-4">
                <h4 class="footer-heading">Find us</h4>
                <p class="text-white-50 mb-1">{$this->h($c['address'])}</p>
                <p class="mb-1"><a href="mailto:{$this->h($c['email'])}">{$this->h($c['email'])}</a></p>
                <p class="text-white-50 mb-0">{$this->h($c['hours'])}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between gap-2 py-3 border-top border-secondary-subtle small">
              <span class="text-white-50">&copy; {$year} {$name}. All rights reserved.</span>
              <span class="text-white-50">Built with SiteForge</span>
            </div>
          </div>
        </footer>

        {$floating}
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@{$bs}/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/main.js"></script>
        </body>
        </html>
        HTML;
    }

    private function visual(int $index, string $ratioClass): string
    {
        $images = $this->spec['images'] ?? [];
        if ($images !== []) {
            $path = $this->h($images[$index % count($images)]);
            return '<img src="' . $path . '" class="img-fluid rounded-4 shadow-sm w-100" '
                 . 'alt="' . $this->h($this->spec['name']) . '" loading="lazy" style="object-fit:cover">';
        }

        $angles = [135, 45, 200, 300, 90, 260];
        $angle = $angles[$index % count($angles)];

        return '<div class="' . $ratioClass . '"><div class="visual rounded-4" style="--visual-angle:'
             . $angle . 'deg" role="img" aria-label="' . $this->h($this->spec['name']) . '">'
             . '<span>' . $this->h($this->initials($this->spec['name'])) . '</span></div></div>';
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

    // --- Assets ------------------------------------------------------------

    private function css(): string
    {
        $t = $this->t;

        $components = $this->spec['components'] ?? [];
        $has = static fn (string $c): bool => in_array($c, $components, true);

        // The shared component styles speak in --primary/--surface/etc, so map
        // those onto this build's Bootstrap-flavoured variables once.
        $shared = CssKit::componentCss($has, !empty($this->spec['dark']))
                . CssKit::motionCss((string) ($t["motion"] ?? "subtle"))
                . CssKit::buttonShape((string) ($t["buttonShape"] ?? "default"));

        return <<<CSS
        /* =========================================================
           Generated by SiteForge — Bootstrap 5 theme overrides.
           Bootstrap itself is loaded from the CDN; this file only
           recolours it and adds the few custom pieces.
           ========================================================= */

        :root {
          --brand: {$t['primary']};
          --brand-dark: {$t['primaryDark']};
          --brand-accent: {$t['accent']};

          --bs-primary: var(--brand);
          --bs-primary-rgb: 0, 0, 0; /* overridden below by component vars */
          --bs-link-color: var(--brand);
          --bs-link-hover-color: var(--brand-dark);
          --bs-border-radius: {$t['radius']};
          --bs-border-radius-lg: {$t['radiusLg']};
          --bs-body-font-family: {$t['body']};

          /* Aliases so the shared component styles below resolve correctly. */
          --primary: var(--brand);
          --primary-dark: var(--brand-dark);
          --accent: var(--brand-accent);
          --on-primary: #fff;
          --bg: var(--bs-body-bg);
          --surface: var(--bs-tertiary-bg);
          --surface-alt: var(--bs-secondary-bg);
          --border: var(--bs-border-color);
          --ink: var(--bs-emphasis-color);
          --text: var(--bs-secondary-color);
          --radius: {$t['radius']};
          --radius-lg: {$t['radiusLg']};
          --font-heading: {$t['heading']};
          --section-pad: {$t['pad']};
          --max: {$t['maxWidth']};
          --shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .12);
          --shadow-sm: 0 .125rem .5rem rgba(0, 0, 0, .08);
        }

        .container { max-width: var(--max); }
        .section { padding: var(--section-pad) 0; }
        .section-title { font-size: clamp(1.85rem, 3.2vw, 2.6rem); }
        .section-head { max-width: 660px; margin: 0 auto 3rem; text-align: center; }
        .section-sub { font-size: 1.05rem; color: var(--text); }
        .container.narrow { max-width: 820px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        {$shared}

        h1, h2, h3, h4, h5, .navbar-brand, .display-4, .display-5, .display-6 {
          font-family: {$t['heading']};
          font-weight: {$t['weight']};
          letter-spacing: {$t['track']};
        }

        .btn-primary {
          --bs-btn-bg: var(--brand);
          --bs-btn-border-color: var(--brand);
          --bs-btn-hover-bg: var(--brand-dark);
          --bs-btn-hover-border-color: var(--brand-dark);
          --bs-btn-active-bg: var(--brand-dark);
          --bs-btn-active-border-color: var(--brand-dark);
          --bs-btn-disabled-bg: var(--brand);
          --bs-btn-disabled-border-color: var(--brand);
        }
        .btn { transition: transform .18s ease, box-shadow .18s ease; }
        .btn:hover { transform: translateY(-2px); }

        .text-primary { color: var(--brand) !important; }
        .border-primary { border-color: var(--brand) !important; }
        .text-bg-primary { background-color: var(--brand) !important; color: #fff !important; }

        .form-control:focus {
          border-color: var(--brand);
          box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--brand) 22%, transparent);
        }
        .accordion-button:not(.collapsed) {
          color: var(--brand-dark);
          background-color: color-mix(in srgb, var(--brand) 10%, transparent);
        }
        .accordion-button:focus { box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--brand) 22%, transparent); }

        /* Navbar ---------------------------------------------------------- */
        .navbar {
          background: color-mix(in srgb, var(--bs-body-bg) 82%, transparent);
          backdrop-filter: saturate(180%) blur(14px);
          -webkit-backdrop-filter: saturate(180%) blur(14px);
          transition: box-shadow .25s ease, border-color .25s ease;
          border-bottom: 1px solid transparent;
          padding-block: .85rem;
        }
        .navbar.is-scrolled {
          border-bottom-color: var(--bs-border-color);
          box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
        }
        .navbar .nav-link.active { color: var(--brand); font-weight: 600; }
        .brand-mark {
          width: 26px; height: 26px; border-radius: 8px; display: inline-block;
          background: linear-gradient(135deg, var(--brand), var(--brand-accent));
          box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .22);
        }

        /* Hero ------------------------------------------------------------ */
        .hero {
          background:
            radial-gradient(58rem 38rem at 10% -12%, color-mix(in srgb, var(--brand) 14%, transparent), transparent 60%),
            radial-gradient(46rem 32rem at 92% 6%, color-mix(in srgb, var(--brand-accent) 14%, transparent), transparent 62%);
        }
        .page-header { background: linear-gradient(180deg, var(--bs-tertiary-bg), transparent); }
        .eyebrow {
          font-size: .8rem; font-weight: 700; letter-spacing: .13em;
          text-transform: uppercase; color: var(--brand);
        }

        /* Generated visuals ------------------------------------------------ */
        .visual {
          display: grid; place-items: center; overflow: hidden;
          background: linear-gradient(var(--visual-angle, 135deg), var(--brand), var(--brand-accent));
          position: relative;
        }
        .visual::after {
          content: ""; position: absolute; inset: 0;
          background:
            radial-gradient(circle at 22% 26%, rgba(255,255,255,.30), transparent 45%),
            radial-gradient(circle at 78% 74%, rgba(0,0,0,.22), transparent 48%);
        }
        .visual span {
          position: relative; z-index: 1; color: #fff; font-weight: 700;
          font-family: {$t['heading']}; font-size: clamp(1.8rem, 4.5vw, 3rem);
          text-shadow: 0 2px 18px rgba(0, 0, 0, .28);
        }

        .stat-value {
          font-family: {$t['heading']}; font-weight: 800; color: var(--brand);
          font-size: clamp(2rem, 4.4vw, 3rem); line-height: 1.1;
        }
        .avatar {
          display: grid; place-items: center; flex: none;
          width: 44px; height: 44px; border-radius: 50%; color: #fff; font-weight: 700;
          background: linear-gradient(135deg, var(--brand), var(--brand-accent));
        }
        .avatar-lg { width: 56px; height: 56px; font-size: 1.15rem; }
        .badge-num {
          background: color-mix(in srgb, var(--brand) 12%, transparent);
          color: var(--brand); font-weight: 700; letter-spacing: .08em;
        }
        .tick {
          display: inline-block; width: .95rem; height: .5rem; margin-right: .75rem;
          border-left: 2.5px solid var(--brand); border-bottom: 2.5px solid var(--brand);
          transform: rotate(-45deg) translateY(-2px);
        }
        .contact-label {
          font-size: .74rem; letter-spacing: .1em; text-transform: uppercase;
          color: var(--brand); font-weight: 700;
        }

        .cta-band { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); }
        .site-footer { background: {$t['footerBg']}; color: rgba(255,255,255,.72); }
        .site-footer a { color: rgba(255,255,255,.8); text-decoration: none; }
        .site-footer a:hover { color: #fff; }
        .footer-heading { font-size: .82rem; letter-spacing: .12em; text-transform: uppercase; color: #fff; margin-bottom: 1rem; }

        .card { transition: transform .25s ease, box-shadow .25s ease; }
        .card:hover { transform: translateY(-5px); }

        [data-reveal] { opacity: 0; transform: translateY(22px); transition: opacity .7s ease, transform .7s ease; }
        [data-reveal].is-visible { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
          [data-reveal] { opacity: 1; transform: none; }
          .btn:hover, .card:hover { transform: none; }
        }
        CSS;
    }

    private function js(): string
    {
        $components = $this->spec['components'] ?? [];
        $has = static fn (string $c): bool => in_array($c, $components, true);

        return $this->baseJs() . CssKit::extraScript($has, (string) ($this->spec['motion'] ?? 'subtle'));
    }

    private function baseJs(): string
    {
        return <<<'JS'
        /* Generated by SiteForge — Bootstrap bundle handles the navbar and accordion. */
        (function () {
          'use strict';

          var nav = document.getElementById('siteNav');
          if (nav) {
            var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 12); };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });
          }

          var revealables = document.querySelectorAll('[data-reveal]');
          if (!('IntersectionObserver' in window)) {
            revealables.forEach(function (el) { el.classList.add('is-visible'); });
          } else {
            var observer = new IntersectionObserver(function (entries) {
              entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                  entry.target.classList.add('is-visible');
                  observer.unobserve(entry.target);
                }
              });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
            revealables.forEach(function (el) { observer.observe(el); });
          }

          // Bootstrap-style form validation
          var form = document.getElementById('contactForm');
          if (form) {
            form.addEventListener('submit', function (event) {
              event.preventDefault();
              event.stopPropagation();
              form.classList.add('was-validated');
              if (!form.checkValidity()) return;

              var note = document.getElementById('formNote');
              if (note) note.classList.remove('d-none');
              form.reset();
              form.classList.remove('was-validated');
            });
          }
        })();
        JS;
    }

    private function readme(): string
    {
        $bs = self::BS;
        return <<<TXT
        {$this->spec['name']}
        {$this->spec['tagline']}

        Built with SiteForge — output format: Bootstrap 5

        Open index.html in a browser, or upload the whole folder to any web host.

        Bootstrap {$bs} is loaded from the jsDelivr CDN, so this site needs an
        internet connection to look right. Everything else is local.

        Files
        -----
        Pages     index.html, about.html, services.html, contact.html
        Styles    css/styles.css   (palette variables at the top)
        Scripts   js/main.js
        TXT;
    }
}
