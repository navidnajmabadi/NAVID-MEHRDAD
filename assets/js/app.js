(function () {
  "use strict";

  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("sw.js").catch(function () {});
    });
  }

  var deferredPrompt = null;
  var banner = document.getElementById("installBanner");
  var installBtn = document.getElementById("installBtn");
  var dismissBtn = document.getElementById("installDismiss");
  var installText = document.getElementById("installText");

  function dismissed() {
    try {
      return localStorage.getItem("rrp_install_dismissed") === "1";
    } catch (e) {
      return false;
    }
  }

  function showBanner() {
    if (!banner || dismissed()) return;
    banner.classList.add("show");
  }

  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showBanner();
  });

  // iOS Safari has no beforeinstallprompt — show gentle hint.
  var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;

  if (isIos && !isStandalone && banner && installText && !dismissed()) {
    installText.textContent =
      "On iPhone: Share → Add to Home Screen to install Ring Ring Paw.";
    if (installBtn) installBtn.style.display = "none";
    showBanner();
  }

  if (installBtn) {
    installBtn.addEventListener("click", function () {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(function () {
        deferredPrompt = null;
        if (banner) banner.classList.remove("show");
      });
    });
  }

  if (dismissBtn) {
    dismissBtn.addEventListener("click", function () {
      try {
        localStorage.setItem("rrp_install_dismissed", "1");
      } catch (e) {}
      if (banner) banner.classList.remove("show");
    });
  }
})();
