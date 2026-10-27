/**
 * Instagram-style pet switcher (lib/layout.php rrp_pet_switcher).
 * Picking a pet saves it server-side, then reloads so every screen follows it.
 */
(function () {
  "use strict";

  var btn = document.querySelector("[data-petswitch]");
  var sheet = document.getElementById("petsheet");
  if (!btn || !sheet) return;

  var csrf = (window.SIPO_API && window.SIPO_API.csrf) || btn.getAttribute("data-csrf") || "";
  var url = (window.SIPO_API && window.SIPO_API.switchPet) || "api/pets/switch.php";

  function open() {
    if (typeof sheet.showModal === "function") sheet.showModal();
    else sheet.setAttribute("open", "");
  }
  function close() {
    if (typeof sheet.close === "function") sheet.close();
    else sheet.removeAttribute("open");
  }

  btn.addEventListener("click", open);
  sheet.addEventListener("click", function (e) {
    if (e.target === sheet || e.target.closest("[data-close]")) close();
  });

  sheet.querySelectorAll("[data-pet-id]").forEach(function (row) {
    row.addEventListener("click", function () {
      if (row.getAttribute("aria-current") === "true" && !/[?&]new=1/.test(location.search)) {
        close();
        return;
      }
      row.disabled = true;
      fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ csrf: csrf, pet_id: Number(row.getAttribute("data-pet-id")) })
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || "switch failed");
          var path = location.pathname.replace(/^.*\//, "") || "si-chat.php";
          location.href = path === "pet-register.php" ? "si-chat.php" : path.replace(/\?.*$/, "");
        })
        .catch(function () {
          row.disabled = false;
          close();
        });
    });
  });

  // A pet created inside the onboarding chat shows up without a reload.
  document.addEventListener("sipo:pet-created", function (e) {
    var p = e.detail || {};
    var name = btn.querySelector(".petswitch-name");
    var av = btn.querySelector(".pet-avatar");
    if (name) name.textContent = p.name || "";
    if (av && !p.avatar) av.textContent = p.initial || "";
  });
})();
