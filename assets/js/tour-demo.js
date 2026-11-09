/* Animated demo scenes for tour.php and the landing page. Each scene plays on sample data, loops, and never calls the server. */
(function () {
  var stage = document.querySelector('.td-stage');
  var screen = stage && stage.querySelector('.td-screen');
  if (!screen) return;
  var kind = stage.getAttribute('data-demo');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var saved = screen.innerHTML;
  var timers = [], frames = [], cur = 0;

  function T(ms, fn) { timers.push(setTimeout(fn, ms)); }
  function $(s) { return screen.querySelector(s); }
  function $$(s) { return [].slice.call(screen.querySelectorAll(s)); }
  function stopAll() { timers.forEach(clearTimeout); frames.forEach(cancelAnimationFrame); timers = []; frames = []; cur = 0; }

  function fmtTime(s) { s = Math.round(s); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  function countTo(el, from, to, ms, dec, asTime) {
    var t0 = null;
    function put(v) { el.textContent = asTime ? fmtTime(v) : v.toFixed(dec); }
    if (reduce || ms <= 0) { put(to); return; }
    function step(ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min(1, (ts - t0) / ms);
      put(from + (to - from) * (1 - Math.pow(1 - p, 3)));
      if (p < 1) frames.push(requestAnimationFrame(step));
    }
    frames.push(requestAnimationFrame(step));
  }

  /* Anything with --d (seconds) appears at that time; [data-count] / [data-time] count up. */
  function schedule() {
    $$('[style*="--d"]').forEach(function (el) {
      var d = parseFloat(el.style.getPropertyValue('--d')) || 0;
      T(reduce ? 0 : d * 1000, function () { el.classList.add('on'); });
      if (el.classList.contains('td-typing')) T(reduce ? 0 : d * 1000 + 1500, function () { el.classList.add('off'); });
    });
    $$('[data-count],[data-time]').forEach(function (el) {
      var asTime = el.hasAttribute('data-time');
      var to = parseFloat(el.getAttribute(asTime ? 'data-time' : 'data-count')) || 0;
      var dec = parseInt(el.getAttribute('data-dec') || '0', 10);
      var delay = (parseFloat(el.getAttribute('data-delay')) || 0) * 1000;
      var dur = (parseFloat(el.getAttribute('data-dur')) || 2) * 1000;
      T(reduce ? 0 : delay, function () { countTo(el, 0, to, dur, dec, asTime); });
    });
  }

  function scrollTo(sel, ms, pad) {
    var sc = $('.td-scroll'), el = $(sel);
    if (!sc || !el) return;
    var top = el.getBoundingClientRect().top - sc.getBoundingClientRect().top + cur;
    cur = Math.max(0, top - (pad == null ? 8 : pad));
    sc.style.transition = 'transform ' + (reduce ? 0 : ms) + 'ms cubic-bezier(.2,.7,.2,1)';
    sc.style.transform = 'translateY(' + (-cur) + 'px)';
  }
  function caption(text) {
    var c = $('.td-caption');
    if (!c) { c = document.createElement('p'); c.className = 'td-caption'; screen.appendChild(c); }
    c.classList.remove('on');
    void c.offsetWidth;
    c.textContent = text;
    c.classList.add('on');
  }
  function tap(el) {
    var f = $('.td-finger');
    if (!f || !el || reduce) return;
    var a = el.getBoundingClientRect(), b = screen.getBoundingClientRect();
    f.style.left = (a.left - b.left + Math.min(a.width * 0.6, a.width - 8)) + 'px';
    f.style.top = (a.top - b.top + a.height * 0.5) + 'px';
    f.classList.remove('tap'); void f.offsetWidth; f.classList.add('show', 'tap');
    T(900, function () { f.classList.remove('show', 'tap'); });
  }
  function press(el) { if (!el) return; tap(el); T(250, function () { el.classList.add('press'); }); T(650, function () { el.classList.remove('press'); }); }

  var scenes = {
    health: { loop: 15500, run: function () {
      caption('Everything about your pet, in one place.');
      T(1600, function () { scrollTo('#sec-labs', 1500); });
      T(3600, function () { var r = $('.lab-row'); if (r) r.setAttribute('open', ''); caption('Only values outside the normal range blink.'); });
      T(4400, function () { scrollTo('.lab-row', 900, 40); });
      T(7600, function () { var u = $('.td-upload'); if (u) u.classList.add('on'); caption('Send a photo or PDF of the lab results.'); });
      T(10200, function () { var u = $('.td-upload'); if (u) u.classList.add('read'); });
      T(11800, function () { var u = $('.td-upload'); if (u) u.classList.add('done'); caption('Saved. SIPO deletes the file.'); });
      T(14000, function () { var u = $('.td-upload'); if (u) u.classList.remove('on'); });
    } },
    vet: { loop: 15000, run: function () {
      caption('The same dashboard, as a link for your vet.');
      T(1400, function () { scrollTo('#sec-labs', 1400); });
      T(3200, function () { var s = $('.td-sheet'); if (s) s.classList.add('on'); });
      T(4600, function () { press($('.td-create')); });
      T(5500, function () { var l = $('.td-link'); if (l) l.classList.add('on'); caption('Private, read-only, works for 30 days.'); });
      T(7600, function () { var i = $('.td-link i'); press(i); T(300, function () { if (i) i.textContent = 'Copied'; }); });
      T(9300, function () { var s = $('.td-sheet'); if (s) s.classList.remove('on'); var b = $('.td-vetbar'); if (b) b.classList.add('on'); scrollTo('.hd-tabs', 700, 0); });
      T(10200, function () { caption('Your vet sees the numbers and the trends. No account needed.'); scrollTo('#sec-labs', 1500); });
    } },
    chat: { loop: 15000, run: function () { /* scheduled by --d */ } },
    routines: { loop: 10500, run: function () {
      caption('Tap a routine to read what to do.');
      T(1700, function () { var d = $('#tdFleaD'); if (d) d.setAttribute('open', ''); });
      T(4300, function () { press($('.td-done')); });
      T(5200, function () {
        var li = $('#tdFlea'); if (!li) return;
        li.classList.remove('is-overdue'); li.classList.add('is-ok');
        var chip = li.querySelector('[data-chip]'); if (chip) chip.textContent = 'On track';
        var sm = li.querySelector('summary small'); if (sm) sm.textContent = 'Last today · Next in 1 month';
        caption('Done. The next date is set and SIPO will remind you.');
      });
    } },
    lost: { loop: 12500, run: function () { /* scheduled by --d */ } },
    money: { loop: 10500, run: function () {
      T(5000, function () { var b = $('[data-id="tot"]'); if (b) countTo(b, 186.4, 216.4, 900, 2, false); });
    } },
    walk: { loop: 16000, run: function () {
      T(700, function () { press($('.td-start-btn')); });
      T(1000, function () { screen.classList.add('go'); var a = $('animateMotion'); if (a && a.beginElement) a.beginElement(); });
      T(10400, function () { press($('.td-finish-btn')); });
      T(11200, function () { screen.classList.add('fin'); });
    } },
    coins: { loop: 12500, run: function () { /* scheduled by --d */ } }
  };

  function play() {
    stopAll();
    screen.innerHTML = saved;
    screen.classList.remove('go', 'fin');
    screen.classList.add('play');
    schedule();
    var s = scenes[kind];
    if (s) s.run();
    if (reduce) { $$('[style*="--d"]').forEach(function (e) { e.classList.add('on'); }); return; }
    if (s) T(s.loop, play);
  }
  play();
})();
