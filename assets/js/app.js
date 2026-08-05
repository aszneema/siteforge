/* SiteForge — shared behaviour and helpers. */
(function () {
  'use strict';

  var sf = window.sf = {};

  // --- Helpers -------------------------------------------------------------

  sf.csrf = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };

  /** POST JSON and return the parsed body. Throws on a non-ok payload. */
  sf.post = function (url, data) {
    data = data || {};
    data.csrf = sf.csrf();

    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': sf.csrf(),
        'X-Requested-With': 'fetch'
      },
      body: JSON.stringify(data)
    }).then(function (response) {
      return response.json().catch(function () {
        throw new Error('The server sent back something unexpected (HTTP ' + response.status + ').');
      });
    }).then(function (payload) {
      if (!payload.ok) throw new Error(payload.error || 'Something went wrong.');
      return payload;
    });
  };

  sf.get = function (url) {
    return fetch(url, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    }).then(function (response) {
      return response.json();
    });
  };

  /** Upload one image, returning { id, name, url }. */
  sf.upload = function (file, projectId) {
    var form = new FormData();
    form.append('file', file);
    form.append('csrf', sf.csrf());
    if (projectId) form.append('project_id', projectId);

    return fetch('api/upload.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': sf.csrf(), 'X-Requested-With': 'fetch' },
      body: form
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      if (!payload.ok) throw new Error(payload.error || 'That image could not be uploaded.');
      return payload;
    });
  };

  sf.toast = function (message, kind) {
    var host = document.getElementById('sfToasts');
    if (!host) {
      host = document.createElement('div');
      host.id = 'sfToasts';
      host.style.cssText = 'position:fixed;right:1.25rem;bottom:1.25rem;z-index:200;display:grid;gap:.6rem;max-width:min(92vw,380px)';
      document.body.appendChild(host);
    }

    var toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText =
      'padding:.85rem 1.1rem;border-radius:12px;font-size:.92rem;line-height:1.5;' +
      'background:rgba(11,16,34,.96);border:1px solid ' +
      (kind === 'bad' ? 'rgba(248,113,113,.45);color:#fecaca' : 'rgba(255,255,255,.14);color:#f2f5fb') +
      ';box-shadow:0 18px 40px rgba(0,0,0,.5);opacity:0;transform:translateY(8px);' +
      'transition:opacity .25s ease,transform .25s ease;white-space:pre-wrap';

    host.appendChild(toast);
    requestAnimationFrame(function () {
      toast.style.opacity = '1';
      toast.style.transform = 'none';
    });

    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      setTimeout(function () { toast.remove(); }, 300);
    }, kind === 'bad' ? 7000 : 4000);
  };

  /** Watch a project's build status until it settles. */
  sf.watch = function (projectId, onUpdate, onDone) {
    var stopped = false;

    var tick = function () {
      if (stopped) return;
      sf.get('api/status.php?id=' + projectId).then(function (payload) {
        if (stopped || !payload.ok) return;
        var project = payload.project;
        onUpdate(project);

        if (project.status === 'building' || project.status === 'queued') {
          setTimeout(tick, 900);
        } else if (onDone) {
          onDone(project);
        }
      }).catch(function () {
        if (!stopped) setTimeout(tick, 2500);
      });
    };

    tick();
    return function () { stopped = true; };
  };

  // --- Chrome --------------------------------------------------------------

  var header = document.getElementById('siteHeader');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 10); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  var burger = document.getElementById('burger');
  var mobileNav = document.getElementById('mobileNav');
  if (burger && mobileNav) {
    burger.addEventListener('click', function () {
      var open = mobileNav.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', String(open));
    });
  }

  // --- Scroll reveal -------------------------------------------------------

  sf.reveal = function (scope) {
    var targets = (scope || document).querySelectorAll('[data-reveal]:not(.is-visible)');
    if (!('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -30px' });
    targets.forEach(function (el) { observer.observe(el); });
  };
  sf.reveal();

  // --- Number counters -----------------------------------------------------

  var counters = document.querySelectorAll('[data-count]');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (counters.length && 'IntersectionObserver' in window && !reduceMotion) {
    var counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        counterObserver.unobserve(entry.target);

        var el = entry.target;
        var target = parseFloat(el.getAttribute('data-count'));
        if (!isFinite(target)) return;

        var start = performance.now();
        var step = function (now) {
          var progress = Math.min((now - start) / 1100, 1);
          el.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
          if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      });
    }, { threshold: 0.6 });
    counters.forEach(function (el) { counterObserver.observe(el); });
  } else {
    counters.forEach(function (el) { el.textContent = el.getAttribute('data-count'); });
  }
})();
