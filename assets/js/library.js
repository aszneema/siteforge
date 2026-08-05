/* SiteForge — the Library: live build progress, filtering, rename and delete. */
(function () {
  'use strict';

  var library = document.getElementById('library');
  if (!library) return;

  var grid = document.getElementById('libraryGrid');
  var search = document.getElementById('librarySearch');
  var noMatches = document.getElementById('noMatches');
  var filters = document.querySelectorAll('.filter');
  var activeFilter = 'all';

  // --- Filtering -----------------------------------------------------------

  function applyFilters() {
    if (!grid) return;
    var term = (search && search.value || '').trim().toLowerCase();
    var shown = 0;

    grid.querySelectorAll('.project').forEach(function (card) {
      var matchesFilter = activeFilter === 'all' || card.getAttribute('data-bucket') === activeFilter;
      var matchesTerm = term === '' || (card.getAttribute('data-name') || '').indexOf(term) !== -1;
      var visible = matchesFilter && matchesTerm;

      card.hidden = !visible;
      if (visible) shown++;
    });

    if (noMatches) noMatches.hidden = shown > 0;
  }

  filters.forEach(function (button) {
    button.addEventListener('click', function () {
      filters.forEach(function (other) {
        other.classList.remove('is-active');
        other.setAttribute('aria-selected', 'false');
      });
      button.classList.add('is-active');
      button.setAttribute('aria-selected', 'true');
      activeFilter = button.getAttribute('data-filter');
      applyFilters();
    });
  });

  if (search) search.addEventListener('input', applyFilters);

  // --- Live progress -------------------------------------------------------

  function refresh() {
    window.sf.get('api/status.php').then(function (payload) {
      if (!payload.ok) return;
      var stillBuilding = false;

      payload.projects.forEach(function (project) {
        var card = grid && grid.querySelector('.project[data-id="' + project.id + '"]');
        if (!card) return;

        var building = project.status === 'building' || project.status === 'queued';
        if (building) stillBuilding = true;

        var bucket = building ? 'building' : project.status;
        card.setAttribute('data-bucket', bucket);

        var status = card.querySelector('[data-status]');
        if (status) {
          status.textContent = project.status.charAt(0).toUpperCase() + project.status.slice(1);
          status.className = 'status status-' + bucket;
        }

        var bar = card.querySelector('[data-bar]');
        if (bar) bar.style.width = project.progress + '%';

        var stage = card.querySelector('[data-stage]');
        if (stage) stage.textContent = project.stage;

        var thumbStage = card.querySelector('.js-stage');
        if (thumbStage) thumbStage.textContent = project.stage;

        var wrap = card.querySelector('[data-progress-wrap]');
        if (wrap) wrap.hidden = !building;

        var version = card.querySelector('[data-version]');
        if (version) version.textContent = project.version > 0 ? 'v' + project.version : 'not built';

        var updated = card.querySelector('[data-updated]');
        if (updated) updated.textContent = project.updated;

        var error = card.querySelector('[data-error]');
        if (error) {
          if (project.status === 'failed' && project.error) {
            error.textContent = project.error;
            error.hidden = false;
          } else {
            error.hidden = true;
          }
        }

        // A build that has just produced its first version needs the real
        // card markup (thumbnail, download link), so reload once.
        if (!building && project.version > 0 && !card.querySelector('.project-thumb iframe')) {
          window.location.reload();
        }
      });

      applyFilters();
      if (stillBuilding) setTimeout(refresh, 1200);
    }).catch(function () {
      setTimeout(refresh, 4000);
    });
  }

  if (grid && grid.querySelector('.project[data-bucket="building"]')) {
    setTimeout(refresh, 1000);
  }

  // --- Rename / delete -----------------------------------------------------

  document.addEventListener('click', function (event) {
    var renameBtn = event.target.closest('.js-rename');
    if (renameBtn) {
      var card = renameBtn.closest('.project');
      var heading = card.querySelector('.project-top h2 a');
      var current = heading ? heading.textContent.trim() : '';
      var next = window.prompt('Rename this project:', current);

      if (next === null) return;
      next = next.trim();
      if (next === '' || next === current) return;

      window.sf.post('api/projects.php', {
        action: 'rename',
        project_id: parseInt(renameBtn.getAttribute('data-id'), 10),
        name: next
      }).then(function () {
        if (heading) heading.textContent = next;
        card.setAttribute('data-name', next.toLowerCase());
        window.sf.toast('Renamed to "' + next + '".');
      }).catch(function (error) {
        window.sf.toast(error.message, 'bad');
      });
      return;
    }

    var deleteBtn = event.target.closest('.js-delete');
    if (deleteBtn) {
      var target = deleteBtn.closest('.project');
      var name = target.querySelector('.project-top h2 a');
      var label = name ? name.textContent.trim() : 'this project';

      if (!window.confirm('Delete "' + label + '"?\n\nThis removes every version and all its generated files. It cannot be undone.')) {
        return;
      }

      window.sf.post('api/projects.php', {
        action: 'delete',
        project_id: parseInt(deleteBtn.getAttribute('data-id'), 10)
      }).then(function () {
        target.style.opacity = '0';
        target.style.transform = 'scale(.96)';
        setTimeout(function () {
          target.remove();
          applyFilters();
          if (grid && !grid.querySelector('.project')) window.location.reload();
        }, 250);
        window.sf.toast('Deleted "' + label + '".');
      }).catch(function (error) {
        window.sf.toast(error.message, 'bad');
      });
    }
  });
})();
