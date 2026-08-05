/* SiteForge — the project workspace: preview, edit chat, versions. */
(function () {
  'use strict';

  var workspace = document.getElementById('workspace');
  if (!workspace) return;

  var projectId = parseInt(workspace.getAttribute('data-project'), 10);
  var version = parseInt(workspace.getAttribute('data-version'), 10);

  var frame = document.getElementById('previewFrame');
  var pageSelect = document.getElementById('pageSelect');
  var reloadBtn = document.getElementById('reloadPreview');

  var chat = document.getElementById('chat');
  var editForm = document.getElementById('editForm');
  var instruction = document.getElementById('instruction');
  var applyBtn = document.getElementById('applyBtn');
  var editError = document.getElementById('editError');

  var fileInput = document.getElementById('fileInput');
  var uploads = document.getElementById('uploads');

  var assetIds = [];
  var busy = false;

  // --- Tabs ----------------------------------------------------------------

  document.querySelectorAll('.tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var name = tab.getAttribute('data-tab');

      document.querySelectorAll('.tab').forEach(function (other) {
        other.classList.toggle('is-active', other === tab);
        other.setAttribute('aria-selected', String(other === tab));
      });
      document.querySelectorAll('.tab-panel').forEach(function (panel) {
        panel.classList.toggle('is-active', panel.getAttribute('data-panel') === name);
      });
    });
  });

  // --- Preview controls ----------------------------------------------------

  if (pageSelect && frame) {
    pageSelect.addEventListener('change', function () {
      frame.src = 'preview.php?p=' + projectId + '&v=' + version + '&f=' + encodeURIComponent(pageSelect.value);
    });
  }

  if (reloadBtn && frame) {
    reloadBtn.addEventListener('click', function () {
      // Re-assigning src re-requests the page rather than reusing the cache.
      frame.src = frame.src.split('&_=')[0] + '&_=' + Date.now();
    });
  }

  // --- Device preview ------------------------------------------------------
  // The iframe renders at a real device width, then gets scaled to fit the
  // panel — otherwise "Desktop" would just be however wide the panel happens
  // to be, and the generated site's mobile breakpoints would kick in.
  var stage = document.getElementById('previewStage');
  var viewport = document.getElementById('previewViewport');
  var deviceWidth = 1280;

  function fitPreview() {
    if (!stage || !viewport || !frame) return;

    // Measure the column, not the stage: the stage contains the element we
    // are about to resize, so measuring it risks a feedback loop.
    var column = stage.parentElement || stage;
    var available = column.clientWidth;
    var height = stage.clientHeight;

    // A hidden or not-yet-laid-out panel measures as 0 (or absurdly narrow).
    // Skip rather than baking a wrong scale in; the observer fires again once
    // the panel has a real size.
    if (available < 200 || height < 100) return;

    var scale = Math.min(1, available / deviceWidth);

    frame.style.width = deviceWidth + 'px';
    frame.style.height = Math.round(height / scale) + 'px';
    frame.style.transform = 'scale(' + scale + ')';

    viewport.style.width = Math.round(deviceWidth * scale) + 'px';
    viewport.style.height = height + 'px';
  }

  document.querySelectorAll('.vp').forEach(function (button) {
    button.addEventListener('click', function () {
      document.querySelectorAll('.vp').forEach(function (other) {
        other.classList.toggle('is-active', other === button);
      });
      deviceWidth = parseInt(button.getAttribute('data-width'), 10) || 1280;
      fitPreview();
    });
  });

  if (stage) {
    // A ResizeObserver keeps the scale correct through the initial layout,
    // sidebar tab switches and window resizes alike.
    if ('ResizeObserver' in window) {
      new ResizeObserver(fitPreview).observe(stage.parentElement || stage);
    }
    window.addEventListener('resize', fitPreview);
    window.addEventListener('load', fitPreview);
    if (frame) frame.addEventListener('load', fitPreview);
    requestAnimationFrame(fitPreview);
  }

  // --- Image uploads -------------------------------------------------------

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      Array.prototype.forEach.call(fileInput.files, addUpload);
      fileInput.value = '';
    });
  }

  function addUpload(file) {
    var tile = document.createElement('div');
    tile.className = 'upload is-loading';
    tile.innerHTML = '<span class="spinner" aria-hidden="true"></span>';
    uploads.insertBefore(tile, uploads.querySelector('.upload-add'));

    window.sf.upload(file, projectId).then(function (asset) {
      assetIds.push(asset.id);
      tile.classList.remove('is-loading');
      tile.innerHTML =
        '<img src="' + asset.url + '" alt="' + asset.name + '">' +
        '<button type="button" class="upload-remove" aria-label="Remove ' + asset.name + '">&times;</button>';

      tile.querySelector('.upload-remove').addEventListener('click', function () {
        assetIds = assetIds.filter(function (id) { return id !== asset.id; });
        tile.remove();
      });
    }).catch(function (error) {
      tile.remove();
      window.sf.toast(error.message, 'bad');
    });
  }

  // --- Edit chat -----------------------------------------------------------

  function addMessage(text, kind) {
    var message = document.createElement('div');
    message.className = 'msg msg-' + kind;
    message.innerHTML =
      (kind === 'user' ? '' : '<span class="msg-avatar" aria-hidden="true">' + (kind === 'error' ? '!' : 'W') + '</span>') +
      '<div class="msg-body"><p></p></div>';
    message.querySelector('p').textContent = text;

    chat.appendChild(message);
    chat.scrollTop = chat.scrollHeight;
  }

  if (editForm) {
    instruction.addEventListener('keydown', function (event) {
      if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        editForm.requestSubmit();
      }
    });

    editForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (busy) return;

      var text = instruction.value.trim();
      if (text.length < 3) {
        showError('Describe the change you want to make.');
        return;
      }

      hideError();
      setBusy(true);
      addMessage(text, 'user');
      instruction.value = '';

      var stopWatching = window.sf.watch(projectId, function (project) {
        var status = document.getElementById('projectStatus');
        if (status) {
          status.textContent = project.stage;
          status.className = 'status status-building';
        }
      });

      window.sf.post('api/generate.php', {
        project_id: projectId,
        action: 'edit',
        input: text,
        asset_ids: assetIds
      }).then(function (result) {
        stopWatching();
        addMessage(result.summary, 'bot');
        window.sf.toast('Built v' + result.version + '. Reloading the preview…');
        setTimeout(function () {
          window.location.href = 'project.php?id=' + projectId;
        }, 900);
      }).catch(function (error) {
        stopWatching();
        addMessage(error.message, 'error');
        showError(error.message);
        setBusy(false);

        var status = document.getElementById('projectStatus');
        if (status) {
          status.textContent = 'Failed';
          status.className = 'status status-failed';
        }
      });
    });
  }

  function setBusy(state) {
    busy = state;
    applyBtn.disabled = state;
    applyBtn.classList.toggle('is-busy', state);
    applyBtn.querySelector('.btn-label').textContent = state ? 'Rebuilding…' : 'Apply change';
  }

  function showError(message) {
    editError.textContent = message;
    editError.hidden = false;
  }

  function hideError() {
    editError.hidden = true;
  }

  // --- Restore a version ---------------------------------------------------

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-restore');
    if (!button) return;

    var target = parseInt(button.getAttribute('data-version'), 10);
    if (!window.confirm('Restore version ' + target + '?\n\nThis copies it forward as a new version — nothing is lost.')) {
      return;
    }

    button.disabled = true;
    window.sf.post('api/projects.php', {
      action: 'restore',
      project_id: projectId,
      version: target
    }).then(function (result) {
      window.sf.toast('Restored as v' + result.version + '.');
      window.location.href = 'project.php?id=' + projectId;
    }).catch(function (error) {
      button.disabled = false;
      window.sf.toast(error.message, 'bad');
    });
  });

  // --- Rename --------------------------------------------------------------

  var renameBtn = document.querySelector('.js-rename-project');
  if (renameBtn) {
    renameBtn.addEventListener('click', function () {
      var heading = document.getElementById('projectName');
      var current = heading.textContent.trim();
      var next = window.prompt('Rename this project:', current);

      if (next === null) return;
      next = next.trim();
      if (next === '' || next === current) return;

      window.sf.post('api/projects.php', {
        action: 'rename',
        project_id: projectId,
        name: next
      }).then(function () {
        heading.textContent = next;
        document.title = next + ' · SiteForge';
        window.sf.toast('Renamed to "' + next + '".');
      }).catch(function (error) {
        window.sf.toast(error.message, 'bad');
      });
    });
  }

  // --- Keep the page live while a build is running -------------------------

  if (workspace.getAttribute('data-building') === '1') {
    window.sf.watch(projectId, function (project) {
      var stage = document.getElementById('progressStage');
      var percent = document.getElementById('progressPercent');
      var bar = document.getElementById('progressBar');
      if (stage) stage.textContent = project.stage;
      if (percent) percent.textContent = project.progress + '%';
      if (bar) bar.style.width = project.progress + '%';
    }, function () {
      window.location.reload();
    });
  }
})();
