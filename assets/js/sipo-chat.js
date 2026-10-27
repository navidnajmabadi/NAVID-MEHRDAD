/**
 * SIPO chat view. Renders window.SIPO_VM (lib/viewmodels/chat_vm.php) and talks to api/si/*.
 * SIPO speaks first (greet), polls for proactive messages while visible, and
 * moves the mascot through listening → thinking → writing → reply state.
 */
(function () {
  "use strict";

  var vm = window.SIPO_VM;
  var api = window.SIPO_API;
  if (!vm || !api) return;

  var log = document.getElementById("sipoLog");
  var form = document.getElementById("sipoForm");
  var input = document.getElementById("sipoInput");
  var face = document.getElementById("sipoFace");
  var status = document.getElementById("sipoStatus");
  var gateBox = document.getElementById("sipoGate");
  var sendBtn = form.querySelector(".sipo-send");

  var threadId = vm.thread.id;
  var lastId = 0;
  var busy = false;
  var restState = "idle";
  var idleStatus = status.textContent;
  var seen = {};

  function setFace(state) {
    if (face) face.setAttribute("state", state);
  }

  function setStatus(text) {
    status.textContent = text || idleStatus;
  }

  function fmtTime(iso) {
    try {
      return new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    } catch (e) {
      return "";
    }
  }

  function dayKey(iso) {
    try {
      return new Date(iso).toDateString();
    } catch (e) {
      return "";
    }
  }

  var lastDay = "";
  function maybeDayDivider(iso) {
    var k = dayKey(iso);
    if (!k || k === lastDay) return;
    lastDay = k;
    var d = document.createElement("div");
    d.className = "sipo-day";
    var today = new Date().toDateString();
    d.textContent = k === today ? "Today" : new Date(iso).toLocaleDateString([], { weekday: "short", month: "short", day: "numeric" });
    log.appendChild(d);
  }

  // Phone numbers in SIPO replies (vet search) become tap-to-call links.
  function renderBody(el, text) {
    var re = /(\+?\d[\d\s().-]{6,}\d)/g;
    var last = 0;
    var m;
    while ((m = re.exec(text))) {
      if (m.index > last) el.appendChild(document.createTextNode(text.slice(last, m.index)));
      var a = document.createElement("a");
      a.href = "tel:" + m[1].replace(/[^\d+]/g, "");
      a.textContent = m[1];
      el.appendChild(a);
      last = m.index + m[1].length;
    }
    if (last < text.length) el.appendChild(document.createTextNode(text.slice(last)));
  }

  function bubble(msg) {
    if (msg.id && seen[msg.id]) return null;
    if (msg.id) {
      seen[msg.id] = true;
      if (msg.id > lastId) lastId = msg.id;
    }
    maybeDayDivider(msg.created_at || new Date().toISOString());
    var row = document.createElement("div");
    row.className = "sipo-msg " + (msg.role === "assistant" ? "from-sipo" : "from-me");
    if (msg.mascot === "alert") row.className += " is-alert";
    var p = document.createElement("p");
    renderBody(p, String(msg.body || ""));
    row.appendChild(p);
    if (msg.created_at) {
      var t = document.createElement("time");
      t.dateTime = msg.created_at;
      t.textContent = fmtTime(msg.created_at);
      row.appendChild(t);
    }
    log.appendChild(row);
    scrollDown();
    return row;
  }

  function scrollDown() {
    log.scrollTop = log.scrollHeight;
  }

  var typingEl = null;
  function showTyping(on) {
    if (on && !typingEl) {
      typingEl = document.createElement("div");
      typingEl.className = "sipo-msg from-sipo sipo-typing";
      typingEl.setAttribute("aria-label", "SIPO is typing");
      typingEl.innerHTML = "<span></span><span></span><span></span>";
      log.appendChild(typingEl);
      scrollDown();
    } else if (!on && typingEl) {
      typingEl.remove();
      typingEl = null;
    }
  }

  function showGate(gate) {
    if (!gate) {
      gateBox.hidden = true;
      return;
    }
    gateBox.hidden = false;
    gateBox.textContent = gate.message || gate.error || "SIPO is resting.";
    if (gate.kind === "plus_required" || gate.gate === "plus_required") {
      var a = document.createElement("a");
      a.href = "plus.php";
      a.className = "link";
      a.textContent = " See Plus";
      gateBox.appendChild(a);
    }
  }

  function post(url, body) {
    body.csrf = api.csrf;
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () {
        return { ok: false, error: "Server error (" + r.status + ")." };
      });
    });
  }

  function applyReply(j) {
    if (j.assistant && j.assistant.body) {
      bubble(j.assistant);
      restState = j.assistant.mascot || "idle";
      setFace(restState);
      if (restState === "happy") {
        setTimeout(function () { if (!busy) setFace("idle"); }, 4000);
      }
    }
    if (j.pet && vm.thread.onboarding) {
      // SIPO just created the pet: this chat now belongs to it.
      vm.thread.onboarding = false;
      vm.thread.pet = j.pet;
      idleStatus = "Looking after " + j.pet.name;
      document.dispatchEvent(new CustomEvent("sipo:pet-created", { detail: j.pet }));
      try { history.replaceState(null, "", "si-chat.php"); } catch (e) {}
      input.placeholder = "Tell SIPO about " + j.pet.name + "…";
    }
  }

  function send(text) {
    busy = true;
    sendBtn.disabled = true;
    setFace("thinking");
    setStatus("Thinking…");
    var mine = bubble({ role: "user", body: text, created_at: new Date().toISOString() });
    showTyping(true);
    var writingTimer = setTimeout(function () { setFace("writing"); setStatus("Writing…"); }, 1400);

    post(api.send, { thread_id: threadId, body: text })
      .then(function (j) {
        clearTimeout(writingTimer);
        showTyping(false);
        if (j.message && j.message.id && mine) {
          seen[j.message.id] = true;
          if (j.message.id > lastId) lastId = j.message.id;
        }
        if (!j.ok) {
          setFace("idle");
          if (j.gate) showGate(j);
          else bubble({ role: "assistant", body: j.error || "Something went wrong. Try again.", created_at: new Date().toISOString() });
          return;
        }
        showGate(null);
        applyReply(j);
      })
      .catch(function () {
        clearTimeout(writingTimer);
        showTyping(false);
        setFace("idle");
        bubble({ role: "assistant", body: "I couldn't reach the server. Check your connection and try again.", created_at: new Date().toISOString() });
      })
      .then(function () {
        busy = false;
        sendBtn.disabled = false;
        setStatus();
      });
  }

  function greet() {
    setFace("thinking");
    showTyping(true);
    post(api.greet, { thread_id: threadId })
      .then(function (j) {
        showTyping(false);
        if (j.ok && j.assistant) applyReply(j);
        else setFace("idle");
      })
      .catch(function () {
        showTyping(false);
        setFace("idle");
      });
  }

  function poll() {
    if (busy || document.hidden) return;
    fetch(api.messages + "?thread_id=" + threadId + "&after_id=" + lastId, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) return;
        (j.messages || []).forEach(function (m) { bubble(m); });
      })
      .catch(function () {});
  }

  // Composer: Enter sends, Shift+Enter = new line, grows with text.
  function autosize() {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 160) + "px";
  }
  input.addEventListener("input", function () {
    autosize();
    if (!busy) setFace(input.value.trim() ? "listening" : restState === "alert" ? "alert" : "idle");
  });
  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey && !e.isComposing && window.matchMedia("(hover:hover)").matches) {
      e.preventDefault();
      form.requestSubmit();
    }
  });
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text || busy) return;
    input.value = "";
    autosize();
    send(text);
  });

  // Initial render
  (vm.messages || []).forEach(bubble);
  showGate(vm.gate);
  if (!vm.gate) greet();
  setInterval(poll, vm.poll_ms || 20000);
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) poll();
  });
})();
