(function () {
  "use strict";

  function swUrl() {
    var el = document.querySelector('meta[name="rrp-sw"]');
    return el ? el.getAttribute("content") : "sw.js";
  }

  if ("serviceWorker" in navigator) {
    navigator.serviceWorker
      .register(swUrl())
      .then(function () {
        if (window.RRPPush && typeof window.RRPPush.ensure === "function") {
          window.RRPPush.ensure();
        }
      })
      .catch(function () {});
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

  function afterInstall() {
    if (window.RRPPush && typeof window.RRPPush.ensure === "function") {
      // iOS/Android may need a tick after A2HS before standalone + push APIs settle.
      setTimeout(function () {
        window.RRPPush.ensure().then(function () {
          var pushBanner = document.getElementById("pushBanner");
          if (pushBanner && window.RRPPush.renderBanner) {
            window.RRPPush.renderBanner(pushBanner);
          }
        });
      }, 800);
    }
  }

  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showBanner();
  });

  window.addEventListener("appinstalled", function () {
    if (banner) banner.classList.remove("show");
    afterInstall();
  });

  // iOS Safari has no beforeinstallprompt — show gentle hint.
  var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;

  if (isIos && !isStandalone && banner && installText && !dismissed()) {
    installText.textContent =
      "On iPhone: Share → Add to Home Screen to install siPaw (needed for notifications).";
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
        afterInstall();
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

  // Dashboard push banner wiring
  var pushBanner = document.getElementById("pushBanner");
  var pushEnable = document.getElementById("pushEnableBtn");
  if (pushBanner && window.RRPPush) {
    window.RRPPush.renderBanner(pushBanner);
    if (pushEnable) {
      pushEnable.addEventListener("click", function () {
        pushEnable.disabled = true;
        window.RRPPush.enable().then(function (res) {
          window.RRPPush.renderBanner(pushBanner);
          var hint = pushBanner.querySelector("[data-push-hint]");
          if (!res || !res.ok) {
            if (hint) {
              if (res && res.error === "ios_not_installed") {
                hint.textContent =
                  (res.status && res.status.hint) ||
                  "Install via Share → Add to Home Screen first.";
              } else if (res && res.error === "permission_denied") {
                hint.textContent = isIos
                  ? "Notifications blocked. Settings → Notifications → siPaw → Allow."
                  : "Notifications blocked. Allow notifications for this site in browser settings, then try again.";
              } else if (res && res.error === "vapid_not_configured") {
                hint.textContent =
                  "Server push keys are empty. Email and inbox still work. Ask admin to add VAPID keys.";
              } else {
                hint.textContent = "Could not enable push. Email and inbox still work.";
              }
            }
          }
          pushEnable.disabled = false;
        });
      });
    }
  }
})();
