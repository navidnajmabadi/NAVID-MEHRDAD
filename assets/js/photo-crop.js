/*
 * Photo position editor, like Telegram or WhatsApp: pick a photo, drag it inside a circle, zoom,
 * Save. The cropped square (JPEG, 640 px) replaces the chosen file and the form is submitted.
 *
 * Markup: <input type="file" data-crop> inside the form that saves the photo. An optional
 * <button type="button" data-crop-adjust="<file input id>" data-src="<current image url>"> reopens the
 * editor on the picture that is already saved.
 */
(function () {
  var VIEW = 280, OUT = 640;
  var modal, stage, img, slider, saveBtn, state = null;

  function build() {
    if (modal) return;
    modal = document.createElement('div');
    modal.className = 'pcrop';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="pcrop-card" role="dialog" aria-modal="true" aria-label="Position your photo">' +
      '<h3>Move and zoom</h3><p class="pcrop-hint">Drag the photo to centre it, use the slider to zoom.</p>' +
      '<div class="pcrop-stage" style="width:' + VIEW + 'px;height:' + VIEW + 'px"><img alt="" draggable="false" /><i class="pcrop-ring"></i></div>' +
      '<input class="pcrop-zoom" type="range" min="1" max="4" step="0.01" value="1" aria-label="Zoom" />' +
      '<div class="pcrop-actions"><button type="button" class="btn outline" data-pc-cancel>Cancel</button><button type="button" class="btn primary" data-pc-save>Save photo</button></div>' +
      '</div>';
    document.body.appendChild(modal);
    stage = modal.querySelector('.pcrop-stage');
    img = stage.querySelector('img');
    slider = modal.querySelector('.pcrop-zoom');
    saveBtn = modal.querySelector('[data-pc-save]');
    modal.querySelector('[data-pc-cancel]').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (!modal.hidden && e.key === 'Escape') close(); });

    var drag = null;
    stage.addEventListener('pointerdown', function (e) {
      if (!state) return;
      drag = { x: e.clientX, y: e.clientY, ox: state.x, oy: state.y };
      try { stage.setPointerCapture(e.pointerId); } catch (x) {}
      e.preventDefault();
    });
    stage.addEventListener('pointermove', function (e) {
      if (!drag || !state) return;
      state.x = drag.ox + e.clientX - drag.x;
      state.y = drag.oy + e.clientY - drag.y;
      apply();
    });
    var stop = function () { drag = null; };
    stage.addEventListener('pointerup', stop);
    stage.addEventListener('pointercancel', stop);
    stage.addEventListener('wheel', function (e) {
      if (!state) return;
      e.preventDefault();
      setZoom(state.zoom * (e.deltaY < 0 ? 1.06 : 0.94));
    }, { passive: false });
    slider.addEventListener('input', function () { setZoom(parseFloat(slider.value)); });
    saveBtn.addEventListener('click', save);
  }

  function clamp() {
    var w = state.nw * state.base * state.zoom, h = state.nh * state.base * state.zoom;
    state.x = Math.min(0, Math.max(VIEW - w, state.x));
    state.y = Math.min(0, Math.max(VIEW - h, state.y));
  }
  function apply() {
    clamp();
    var s = state.base * state.zoom;
    img.style.width = state.nw * s + 'px';
    img.style.height = state.nh * s + 'px';
    img.style.transform = 'translate(' + state.x + 'px,' + state.y + 'px)';
  }
  function setZoom(z) {
    z = Math.min(4, Math.max(1, z));
    // Zoom around the centre of the circle.
    var cx = (VIEW / 2 - state.x) / (state.base * state.zoom), cy = (VIEW / 2 - state.y) / (state.base * state.zoom);
    state.zoom = z;
    state.x = VIEW / 2 - cx * state.base * z;
    state.y = VIEW / 2 - cy * state.base * z;
    slider.value = String(z);
    apply();
  }

  function open(source, onDone) {
    build();
    var url = typeof source === 'string' ? source : URL.createObjectURL(source);
    var done = function (bitmap) {
      var nw = bitmap.width, nh = bitmap.height;
      state = { bitmap: bitmap, nw: nw, nh: nh, base: VIEW / Math.min(nw, nh), zoom: 1, x: 0, y: 0, onDone: onDone, url: url, own: typeof source !== 'string' };
      state.x = (VIEW - nw * state.base) / 2;
      state.y = (VIEW - nh * state.base) / 2;
      img.src = url;
      slider.value = '1';
      apply();
      modal.hidden = false;
      document.body.classList.add('pcrop-open');
      saveBtn.focus();
    };
    if (window.createImageBitmap && typeof source !== 'string') {
      // from-image: honour the phone's rotation flag so portraits are not sideways.
      createImageBitmap(source, { imageOrientation: 'from-image' }).then(done, function () { fallback(url, done); });
    } else {
      fallback(url, done);
    }
  }
  function fallback(url, done) {
    var im = new Image();
    im.onload = function () { done({ width: im.naturalWidth, height: im.naturalHeight, el: im }); };
    im.onerror = function () { alert('I could not open that photo. Try a JPG or PNG.'); };
    im.src = url;
  }

  function close() {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('pcrop-open');
    if (state && state.own) { try { URL.revokeObjectURL(state.url); } catch (e) {} }
    state = null;
  }

  function save() {
    if (!state) return;
    var s = state.base * state.zoom;
    var sx = -state.x / s, sy = -state.y / s, sw = VIEW / s, sh = VIEW / s;
    var c = document.createElement('canvas');
    c.width = c.height = OUT;
    var g = c.getContext('2d');
    g.fillStyle = '#fff';
    g.fillRect(0, 0, OUT, OUT);
    g.imageSmoothingQuality = 'high';
    g.drawImage(state.bitmap.el || state.bitmap, sx, sy, sw, sh, 0, 0, OUT, OUT);
    var cb = state.onDone;
    c.toBlob(function (blob) {
      if (!blob) { alert('Could not save that crop. Please try again.'); return; }
      close();
      cb(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
    }, 'image/jpeg', 0.9);
  }

  function submitWith(input, file) {
    var dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.form.submit();
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.type !== 'file' || !input.hasAttribute('data-crop')) return;
    var f = input.files && input.files[0];
    if (!f) return;
    if (!/^image\//.test(f.type)) { input.value = ''; return; }
    open(f, function (out) { submitWith(input, out); });
    // If the editor is cancelled the chosen file is dropped.
    input.value = '';
    input._pcFile = f;
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-crop-adjust]');
    if (!b) return;
    e.preventDefault();
    var input = document.getElementById(b.getAttribute('data-crop-adjust'));
    if (!input) return;
    open(b.getAttribute('data-src'), function (out) { submitWith(input, out); });
  });
})();
