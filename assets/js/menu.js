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
  back.addEventListener("click", function () { set(false); });
  menu.querySelectorAll("[data-menu-close]").forEach(function (b) { b.addEventListener("click", function () { set(false); }); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !menu.hidden) set(false); });
  menu.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) { if (!window.confirm(f.getAttribute("data-confirm"))) e.preventDefault(); });
  });
})();
