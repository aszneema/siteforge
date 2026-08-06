/* SiteForge — the Create page: describe a site, watch it build. */
(function () {
  'use strict';

  var root = document.getElementById('build');
  if (!root) return;

  var chat = document.getElementById('chat');
  var form = document.getElementById('composer');
  var brief = document.getElementById('brief');
  var buildBtn = document.getElementById('buildBtn');
  var charCount = document.getElementById('charCount');
  var errorBox = document.getElementById('composerError');

  var progressPanel = document.getElementById('progressPanel');
  var progressBar = document.getElementById('progressBar');
  var progressStage = document.getElementById('progressStage');
  var progressPercent = document.getElementById('progressPercent');
  var progressHint = document.getElementById('progressHint');

  var resultPanel = document.getElementById('resultPanel');
  var resultFrame = document.getElementById('resultFrame');
  var resultSummary = document.getElementById('resultSummary');
  var resultTitle = document.getElementById('resultTitle');
  var resultOpen = document.getElementById('resultOpen');
  var resultProject = document.getElementById('resultProject');

  var fileInput = document.getElementById('fileInput');
  var uploads = document.getElementById('uploads');

  var siteType = 'html';
  var assetIds = [];
  var busy = false;

  // --- Composer ------------------------------------------------------------

  var updateCount = function () {
    charCount.textContent = brief.value.length + ' / 4000';
  };
  brief.addEventListener('input', updateCount);
  updateCount();

  // Ctrl/Cmd + Enter submits.
  brief.addEventListener('keydown', function (event) {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  document.querySelectorAll('.js-example').forEach(function (chip) {
    chip.addEventListener('click', function () {
      brief.value = chip.getAttribute('data-text');
      brief.focus();
      updateCount();
    });
  });

  document.querySelectorAll('.type-option').forEach(function (option) {
    option.addEventListener('click', function () {
      document.querySelectorAll('.type-option').forEach(function (other) {
        other.classList.remove('is-selected');
        other.setAttribute('aria-checked', 'false');
      });
      option.classList.add('is-selected');
      option.setAttribute('aria-checked', 'true');
      siteType = option.getAttribute('data-type');
    });
  });

  // --- Image uploads -------------------------------------------------------

  fileInput.addEventListener('change', function () {
    Array.prototype.forEach.call(fileInput.files, addUpload);
    fileInput.value = '';
  });

  function addUpload(file) {
    var tile = document.createElement('div');
    tile.className = 'upload is-loading';
    tile.innerHTML = '<span class="spinner" aria-hidden="true"></span>';
    uploads.insertBefore(tile, uploads.querySelector('.upload-add'));

    window.sf.upload(file, null).then(function (asset) {
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

  // --- Chat ----------------------------------------------------------------

  function addMessage(text, kind) {
    var message = document.createElement('div');
    message.className = 'msg msg-' + kind;
    message.innerHTML =
      (kind === 'user' ? '' : '<span class="msg-avatar" aria-hidden="true">' + (kind === 'error' ? '!' : 'W') + '</span>') +
      '<div class="msg-body"><p></p></div>';
    message.querySelector('p').textContent = text;

    chat.appendChild(message);
    message.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    return message;
  }

  // --- Build ---------------------------------------------------------------

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (busy) return;

    var text = brief.value.trim();
    if (text.length < 10) {
      showError('Tell me a bit more — at least a sentence about what the site is for.');
      brief.focus();
      return;
    }

    hideError();
    setBusy(true);
    addMessage(text, 'user');
    brief.value = '';
    updateCount();

    resultPanel.hidden = true;
    progressPanel.hidden = false;
    setProgress(3, 'Creating project');

    var stopWatching = null;

    window.sf.post('api/projects.php', {
      action: 'start',
      brief: text,
      site_type: siteType
    }).then(function (started) {
      var projectId = started.project_id;

      stopWatching = window.sf.watch(projectId, function (project) {
        setProgress(project.progress, project.stage);
      });

      progressHint.textContent = 'Building "' + started.name + '". You can watch this in the Library too.';

      return window.sf.post('api/generate.php', {
        project_id: projectId,
        action: 'create',
        input: text,
        asset_ids: assetIds
      }).then(function (result) {
        if (stopWatching) stopWatching();
        return { project: projectId, result: result };
      });
    }).then(function (payload) {
      setProgress(100, 'Ready');
      setTimeout(function () { progressPanel.hidden = true; }, 700);

      addMessage(payload.result.summary, 'bot');
      showResult(payload.project, payload.result);
      assetIds = [];
      uploads.querySelectorAll('.upload').forEach(function (tile) { tile.remove(); });
      setBusy(false);
    }).catch(function (error) {
      if (stopWatching) stopWatching();
      progressPanel.hidden = true;
      addMessage(error.message, 'error');
      showError(error.message);
      setBusy(false);
    });
  });

  function showResult(projectId, result) {
    var previewUrl = 'preview.php?p=' + projectId + '&v=' + result.version;

    resultTitle.textContent = 'Your site is ready';
    resultSummary.textContent = result.summary;
    resultFrame.src = previewUrl;
    resultOpen.href = previewUrl;
    resultProject.href = 'project.php?id=' + projectId;
    resultPanel.hidden = false;
    resultPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function setProgress(percent, stage) {
    progressBar.style.width = Math.max(3, percent) + '%';
    progressPercent.textContent = percent + '%';
    if (stage) progressStage.textContent = stage;
  }

  function setBusy(state) {
    busy = state;
    buildBtn.disabled = state;
    buildBtn.classList.toggle('is-busy', state);
    buildBtn.querySelector('.btn-label').textContent = state ? 'Building…' : 'Build my website';
  }

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
  }

  function hideError() {
    errorBox.hidden = true;
  }
})();

/* "An app with data" — create it and go straight to the builder. */
(function () {
  'use strict';

  var form = document.getElementById('appForm');
  if (!form) return;

  var input = document.getElementById('appName');
  var error = document.getElementById('appError');

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var name = input.value.trim();
    if (name.length < 2) {
      error.textContent = 'Give your app a name first.';
      error.hidden = false;
      return;
    }
    error.hidden = true;

    var button = form.querySelector('button');
    button.disabled = true;
    button.textContent = 'Creating…';

    window.sf.post('api/projects.php', { action: 'start_app', name: name })
      .then(function (result) { window.location.href = result.url; })
      .catch(function (err) {
        error.textContent = err.message;
        error.hidden = false;
        button.disabled = false;
        button.textContent = 'Create it';
      });
  });
})();
