// Left side menu: open/close, Esc, backdrop, and confirm for admin tools.
(function () {
  var btn = document.getElementById("menuBtn");
  var menu = document.getElementById("sideMenu");
  var back = document.getElementById("sideBackdrop");
  if (!btn || !menu) return;
  function set(open) {
    menu.hidden = !open;
    back.hidden = !open;
    btn.setAttribute("aria-expanded", open ? "true" : "false");
    document.documentElement.classList.toggle("menu-open", open);
    if (open) { var f = menu.querySelector("a,button"); if (f) f.focus(); } else btn.focus();
  }
  btn.addEventListener("click", function () { set(menu.hidden); });
  // The pet chip in a top bar opens the menu at "My pets".
  document.querySelectorAll("[data-menu-open]").forEach(function (el) {
    el.addEventListener("click", function () {
      set(true);
      var pets = document.getElementById("sidePets");
      var drop = pets && pets.querySelector(".side-pet-drop");
      if (drop) drop.open = true;
      if (pets) {
        pets.scrollIntoView({ block: "start" });
        var cur = pets.querySelector("[aria-current]") || pets.querySelector("button,a");
        if (cur) cur.focus();
      }
    });
  });
  // Tap a pet: save it as current, then reload this page so every screen follows it.
  var csrf = menu.getAttribute("data-csrf") || "";
  var switchUrl = (window.SIPO_API && window.SIPO_API.switchPet) || "/api/pets/switch.php";
  menu.querySelectorAll("[data-menu-pet]").forEach(function (b) {
    b.addEventListener("click", function () {
      if (b.getAttribute("aria-current") === "true") { set(false); return; }
      b.disabled = true;
      b.classList.add("is-busy");
      fetch(switchUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ csrf: csrf, pet_id: Number(b.getAttribute("data-menu-pet")) })
      })
        .then(function (r) {
          // Trust the HTTP status: if the server saved the pet, follow it even if the body is not clean JSON.
          return r.json().catch(function () { return { ok: r.ok }; });
        })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || "switch failed");
          var path = location.pathname.replace(/^.*\//, "") || "si-chat.php";
          location.href = path === "pet-register.php" || path === "pet-profile.php" ? "si-chat.php" : path;
        })
        .catch(function () { b.disabled = false; b.classList.remove("is-busy"); });
    });
  });
  back.addEventListener("click", function () { set(false); });
  menu.querySelectorAll("[data-menu-close]").forEach(function (b) { b.addEventListener("click", function () { set(false); }); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !menu.hidden) set(false); });
  menu.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) { if (!window.confirm(f.getAttribute("data-confirm"))) e.preventDefault(); });
  });
})();

// Clean address bar: /dashboard instead of /dashboard.php (no reload). Only after the server has
// proved once that /<page> really serves <page>.php (X-SIPAW-Page header), so a refresh never breaks.
(function () {
  var m = location.pathname.match(/^\/([a-z0-9-]+)\.php$/);
  if (!m || m[1] === "index" || !window.history || !history.replaceState) return;
  var clean = "/" + m[1] + location.search + location.hash;
  var ok = null;
  try { ok = localStorage.getItem("sipaw_clean_urls_v2"); } catch (e) {}
  if (ok === "1") { history.replaceState(history.state, "", clean); return; }
  if (ok === "0") return;
  fetch("/" + m[1] + location.search, { method: "HEAD", credentials: "same-origin", headers: { "X-SIPAW-Check": "1" } })
    .then(function (r) {
      var works = r.ok && r.headers.get("X-SIPAW-Page") === m[1] + ".php";
      try { localStorage.setItem("sipaw_clean_urls_v2", works ? "1" : "0"); } catch (e) {}
      if (works) history.replaceState(history.state, "", clean);
    })
    .catch(function () {});
})();

// Instant feedback: a thin progress bar the moment an in-app link is tapped.
(function () {
  var bar = document.createElement("div");
  bar.className = "nav-progress";
  bar.setAttribute("aria-hidden", "true");
  document.body.appendChild(bar);
  document.addEventListener("click", function (e) {
    var a = e.target.closest && e.target.closest("a[href]");
    if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || a.target === "_blank" || a.hasAttribute("download")) return;
    var url;
    try { url = new URL(a.href, location.href); } catch (err) { return; }
    if (url.origin !== location.origin || (url.pathname === location.pathname && url.hash)) return;
    bar.classList.remove("is-on");
    void bar.offsetWidth;
    bar.classList.add("is-on");
  });
  window.addEventListener("pageshow", function () { bar.classList.remove("is-on"); });
})();

// QA v3 B47: a page restored from the back/forward cache could show old numbers (e.g. Finance after a
// cost was logged in chat). Reload it so data is always current.
window.addEventListener("pageshow", function (e) {
  if (e.persisted) location.reload();
});

/* Ask SIPO button: hold it for about half a second, then drag it anywhere. It remembers where you left it. */
(function () {
  var fab = document.querySelector('.sipo-fab');
  if (!fab) return;
  var KEY = 'sipo_fab_pos', HOLD = 450, timer = null, lifted = false, sx = 0, sy = 0, ox = 0, oy = 0, moved = false;
  function clamp(x, y) {
    var w = fab.offsetWidth, h = fab.offsetHeight;
    return [Math.min(Math.max(8, x), window.innerWidth - w - 8), Math.min(Math.max(8, y), window.innerHeight - h - 8)];
  }
  function place(x, y) {
    var c = clamp(x, y);
    fab.style.left = c[0] + 'px'; fab.style.top = c[1] + 'px'; fab.style.right = 'auto'; fab.style.bottom = 'auto';
  }
  try {
    var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
    if (saved && typeof saved.fx === 'number') place(saved.fx * window.innerWidth, saved.fy * window.innerHeight);
  } catch (e) {}
  window.addEventListener('resize', function () {
    if (fab.style.left) { var r = fab.getBoundingClientRect(); place(r.left, r.top); }
  });
  fab.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  fab.addEventListener('pointerdown', function (e) {
    if (e.button > 0) return;
    var r = fab.getBoundingClientRect();
    sx = e.clientX; sy = e.clientY; ox = r.left; oy = r.top; moved = false;
    clearTimeout(timer);
    timer = setTimeout(function () {
      lifted = true; moved = true; fab.classList.add('is-lifted');
      try { fab.setPointerCapture(e.pointerId); } catch (x) {}
      if (navigator.vibrate) navigator.vibrate(12);
    }, HOLD);
  });
  fab.addEventListener('pointermove', function (e) {
    if (!lifted) {
      if (Math.abs(e.clientX - sx) > 8 || Math.abs(e.clientY - sy) > 8) clearTimeout(timer);
      return;
    }
    moved = true;
    place(ox + e.clientX - sx, oy + e.clientY - sy);
  });
  function end() {
    clearTimeout(timer);
    if (!lifted) return;
    lifted = false; fab.classList.remove('is-lifted');
    var r = fab.getBoundingClientRect();
    try { localStorage.setItem(KEY, JSON.stringify({ fx: r.left / window.innerWidth, fy: r.top / window.innerHeight })); } catch (e) {}
    setTimeout(function () { moved = false; }, 0);
  }
  fab.addEventListener('pointerup', end);
  fab.addEventListener('pointercancel', end);
  // A drag must not count as a tap.
  fab.addEventListener('click', function (e) { if (moved || lifted) { e.preventDefault(); e.stopPropagation(); } }, true);
  fab.addEventListener('dragstart', function (e) { e.preventDefault(); });
})();
