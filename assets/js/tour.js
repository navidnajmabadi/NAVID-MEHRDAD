/* Guided tour: one step at a time, demos load only for the step on screen. Changes nothing unless the owner picks a lab file. */
(function () {
  var root = document.getElementById('tour');
  if (!root) return;
  var steps = [].slice.call(root.querySelectorAll('.tour-step'));
  var bars = [].slice.call(document.querySelectorAll('#tourProgress i'));
  var count = document.getElementById('tourCount');
  var next = document.getElementById('tourNext');
  var back = document.getElementById('tourBack');
  var barBox = document.getElementById('tourBar');
  var total = steps.length;
  var idx = 0;

  function keyIndex(k) {
    for (var i = 0; i < steps.length; i++) if (steps[i].getAttribute('data-key') === k) return i;
    return -1;
  }

  function loadFrame(step, on) {
    var f = step.querySelector('iframe');
    if (!f) return;
    var cover = step.querySelector('.tour-cover');
    if (on) {
      if (f.getAttribute('src') !== f.getAttribute('data-src')) {
        if (cover) cover.style.display = '';
        f.onload = function () { if (cover) cover.style.display = 'none'; };
        f.setAttribute('src', f.getAttribute('data-src'));
      }
    } else if (f.getAttribute('src')) {
      f.removeAttribute('src');
    }
  }

  function fit() {
    [].slice.call(document.querySelectorAll('.tour-phone')).forEach(function (p) {
      var w = p.clientWidth;
      if (w) p.style.setProperty('--s', (w / 390).toFixed(4));
    });
  }
  window.addEventListener('resize', fit);

  function show(i, push) {
    idx = Math.max(0, Math.min(total - 1, i));
    steps.forEach(function (s, n) {
      var on = n === idx;
      s.classList.toggle('on', on);
      loadFrame(s, on);
    });
    bars.forEach(function (b, n) { b.className = n < idx ? 'done' : (n === idx ? 'on' : ''); });
    if (count) count.textContent = 'Step ' + (idx + 1) + ' of ' + total;
    back.hidden = idx === 0;
    var last = idx === total - 1;
    next.hidden = last;
    barBox.style.display = last ? 'none' : '';
    next.textContent = idx === 0 ? 'Start the tour' : (idx === total - 2 ? 'Finish' : 'Next');
    var key = steps[idx].getAttribute('data-key');
    if (push !== false) {
      try { history.replaceState(null, '', '#' + key); } catch (e) { }
    }
    fit();
    window.scrollTo(0, 0);
  }

  next.addEventListener('click', function () { show(idx + 1); });
  back.addEventListener('click', function () { show(idx - 1); });
  var again = document.getElementById('tourAgain');
  if (again) again.addEventListener('click', function (e) { e.preventDefault(); show(0); });
  document.addEventListener('keydown', function (e) {
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
    if (e.key === 'ArrowRight') show(idx + 1);
    if (e.key === 'ArrowLeft') show(idx - 1);
  });

  /* Live lab upload on the Health step (the only action in the tour that saves anything, and only when a file is chosen). */
  var file = document.getElementById('tourFile');
  var status = document.getElementById('tourStatus');
  if (file && window.sipoUploadMany && window.TOUR) {
    file.addEventListener('change', function () {
      var files = [].slice.call(file.files || []).slice(0, 10);
      file.value = '';
      if (!files.length) return;
      if (files.some(function (f) { return f.size > 10 * 1024 * 1024; })) { status.textContent = 'A file is over 10 MB.'; return; }
      status.textContent = files.length > 1 ? 'SIPO is reading ' + files.length + ' files…' : 'SIPO is reading it…';
      window.sipoUploadMany(files, {
        url: window.TOUR.upload, csrf: window.TOUR.csrf, threadId: window.TOUR.threadId,
        onStep: function (i, n) { status.textContent = 'Reading file ' + i + ' of ' + n + '…'; }
      }).then(function (j) {
        status.textContent = j && j.ok ? 'Saved. Open Health to see the results, and carry on with the tour.' : ((j && j.error) || 'Could not read that file.');
      }).catch(function () { status.textContent = 'Upload failed. Check your connection.'; });
    });
  }

  var start = keyIndex((location.hash || '').replace('#', ''));
  show(start > 0 ? start : 0, false);
})();
