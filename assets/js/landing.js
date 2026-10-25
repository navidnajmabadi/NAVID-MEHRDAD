/**
 * Landing hero: cycle Sipo dialog bubbles + mascot state.
 * ui-ux-pro-max: one key motion, pause when offscreen, honor reduced-motion.
 */
(function () {
  "use strict";

  var INTERVAL_MS = 3800;

  function reduceMotion() {
    try {
      return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (e) {
      return false;
    }
  }

  function initTalk() {
    var root = document.getElementById("landingTalk");
    if (!root) return;
    var bubbles = Array.prototype.slice.call(root.querySelectorAll(".landing-bubble"));
    var sipo = document.getElementById("landingSipo");
    if (bubbles.length < 2) return;

    var i = 0;
    var timer = null;
    var visible = true;

    function show(next) {
      bubbles.forEach(function (el, idx) {
        var on = idx === next;
        el.hidden = !on;
        el.classList.toggle("is-active", on);
      });
      var state = bubbles[next].getAttribute("data-state") || "idle";
      if (sipo) {
        sipo.setAttribute("state", state);
      }
      i = next;
    }

    function stop() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      if (reduceMotion() || !visible || timer) return;
      timer = window.setInterval(function () {
        show((i + 1) % bubbles.length);
      }, INTERVAL_MS);
    }

    show(0);
    if (reduceMotion()) return;

    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(
        function (entries) {
          var entry = entries[0];
          visible = !!(entry && entry.isIntersecting);
          if (visible) start();
          else stop();
        },
        { threshold: 0.35 }
      );
      io.observe(root);
    } else {
      start();
    }

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) stop();
      else if (visible) start();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTalk);
  } else {
    initTalk();
  }
})();
