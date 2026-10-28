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
