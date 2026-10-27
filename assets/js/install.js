/**
 * "Add SIPAW to your Home Screen?" — explicit ask inside the app (brief §2 PWA).
 * Android/desktop Chrome: native install prompt. iPhone/iPad Safari: step sheet
 * (Share → Add to Home Screen), which iOS needs before Web Push works.
 * "Not now" hides it for 7 days; installed apps never see it.
 */
(function () {
  "use strict";

  var sheet = document.getElementById("a2hs");
  if (!sheet) return;

  var KEY = "sipaw_a2hs_snooze_until";
  var standalone =
    window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  if (standalone) return;

  function snoozed() {
    try {
      return Number(localStorage.getItem(KEY) || 0) > Date.now();
    } catch (e) {
      return false;
    }
  }
  function snooze(days) {
    try {
      localStorage.setItem(KEY, String(Date.now() + days * 86400000));
    } catch (e) {}
  }
  if (snoozed()) return;

  var ua = navigator.userAgent;
  var isIos = /iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && navigator.maxTouchPoints > 1);
  var isSafari = /safari/i.test(ua) && !/crios|fxios|edgios|chrome|android/i.test(ua);
  var addBtn = sheet.querySelector("[data-a2hs-add]");
  var laterBtn = sheet.querySelector("[data-a2hs-later]");
  var steps = sheet.querySelector(".a2hs-steps");
  var deferred = null;

  function show() {
    sheet.hidden = false;
  }
  function hide() {
    sheet.hidden = true;
  }

  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferred = e;
    setTimeout(show, 4000);
  });
  window.addEventListener("appinstalled", function () {
    hide();
    snooze(3650);
  });

  if (isIos) {
    if (!isSafari) {
      steps.textContent = "Open SIPAW in Safari, then tap Share → Add to Home Screen.";
    }
    steps.hidden = false;
    addBtn.textContent = "Got it";
    setTimeout(show, 4000);
  }

  addBtn.addEventListener("click", function () {
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.finally(function () {
        deferred = null;
        hide();
      });
      return;
    }
    // iOS: the steps are the action; remember for a while.
    snooze(7);
    hide();
  });
  laterBtn.addEventListener("click", function () {
    snooze(7);
    hide();
  });
})();
