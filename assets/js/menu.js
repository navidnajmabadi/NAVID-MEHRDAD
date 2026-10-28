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
        .then(function (r) { return r.json(); })
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
