/**
 * Landing: cycle Sipo dialog lines ↔ mascot state.
 * Honors prefers-reduced-motion; pause control for auto-rotating content.
 */
(function () {
  "use strict";

  var LINES = [
    {
      text: "Hi I’m SIPO — your Pet Assistant. Ask me anything. I’ll roast your schedule gently.",
      state: "happy",
    },
    {
      text: "Forgot the flea meds again? I got you. Nudges > guilt trips.",
      state: "thinking",
    },
    {
      text: "Drafting a recovery plan for post-walk Milo… typing with tiny paws.",
      state: "writing",
    },
    {
      text: "Listening… vent about the 3am zoomies. I’m here. No judgment. Mostly.",
      state: "listening",
    },
    {
      text: "Boards, reminders, SI chat — all in one PWA. Your lint roller will thank you.",
      state: "idle",
    },
  ];

  var bubble = document.getElementById("landingBubble");
  var sipo = document.getElementById("landingSipo");
  var pauseBtn = document.getElementById("landingSipoPause");
  if (!bubble || !sipo) return;

  var idx = 0;
  var timer = null;
  var paused = false;
  var reduce = false;
  try {
    reduce = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
  } catch (e) {
    reduce = false;
  }

  function apply(i) {
    var line = LINES[i % LINES.length];
    bubble.classList.remove("is-swap");
    // force reflow for fade
    void bubble.offsetWidth;
    bubble.textContent = line.text;
    bubble.setAttribute("data-line", String(i % LINES.length));
    bubble.classList.add("is-swap");
    sipo.setAttribute("state", line.state);
    if (window.Sipo && typeof window.Sipo.setState === "function") {
      window.Sipo.setState(line.state, "#landingSipo");
    }
  }

  function next() {
    idx = (idx + 1) % LINES.length;
    apply(idx);
  }

  function stop() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function start() {
    stop();
    if (reduce || paused) return;
    timer = setInterval(next, 4200);
  }

  apply(0);

  if (pauseBtn) {
    pauseBtn.addEventListener("click", function () {
      paused = !paused;
      pauseBtn.setAttribute("aria-pressed", paused ? "true" : "false");
      pauseBtn.textContent = paused ? "Play chat" : "Pause chat";
      if (paused) stop();
      else start();
    });
  }

  bubble.addEventListener("focus", stop);
  bubble.setAttribute("tabindex", "0");

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) stop();
    else if (!paused && !reduce) start();
  });

  if (!reduce) start();
})();
