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
  var SERIOUS = { urgent: 1, concerned: 1, alert: 1, sad: 1, confused: 1 };
  var THANKS = /\b(thanks|thank you|thank u|thx|merci|appreciate it|love you)\b/i;

  // 22:00-06:00 on the owner's clock SIPO is sleepy; a warning face always wins over it.
  function isNight() {
    var h;
    try {
      h = parseInt(new Date().toLocaleString("en-GB", { hour: "numeric", hour12: false, timeZone: vm.tz || undefined }), 10);
    } catch (e) {
      h = new Date().getHours();
    }
    return h >= 22 || h < 6;
  }
  function calm() {
    return isNight() ? "sleepy" : "idle";
  }
  var idleStatus = status.textContent;
  var seen = {};
  var booted = false; // history renders without the entrance animation

  function setFace(state) {
    if (face) face.setAttribute("state", state);
  }

  function setStatus(text) {
    status.textContent = text || idleStatus;
  }

  // Times and days in the owner's SIPAW time zone (QA B37), falling back to the device's.
  function tzOpts(o) {
    if (vm.tz) o.timeZone = vm.tz;
    return o;
  }
  function fmtTime(iso) {
    try {
      return new Date(iso).toLocaleTimeString([], tzOpts({ hour: "2-digit", minute: "2-digit" }));
    } catch (e) {
      return new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    }
  }

  function dayKey(iso) {
    try {
      return new Date(iso).toLocaleDateString("en-CA", tzOpts({}));
    } catch (e) {
      return new Date(iso).toDateString();
    }
  }

  var lastDay = "";
  function maybeDayDivider(iso) {
    var k = dayKey(iso);
    if (!k || k === lastDay) return;
    lastDay = k;
    var d = document.createElement("div");
    d.className = "sipo-day";
    var today = dayKey(new Date().toISOString());
    d.textContent = k === today ? "Today" : new Date(iso).toLocaleDateString([], tzOpts({ weekday: "short", month: "short", day: "numeric" }));
    log.appendChild(d);
  }

  // Phone numbers in SIPO replies (vet search) become tap-to-call links.
  // Our own paw coin in place of the coin emoji (some devices draw that emoji as a grey moon).
  var COIN_SVG = '<svg class="paw-coin chat-coin" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#F5C542" stroke="#C99A1A" stroke-width="1.5"/><circle cx="12" cy="12" r="8" fill="none" stroke="#C99A1A" stroke-width="0.8" opacity=".7"/><ellipse cx="12" cy="14.6" rx="3.2" ry="2.6" fill="#7A5A0A"/><circle cx="8.4" cy="10.8" r="1.25" fill="#7A5A0A"/><circle cx="10.6" cy="8.6" r="1.25" fill="#7A5A0A"/><circle cx="13.4" cy="8.6" r="1.25" fill="#7A5A0A"/><circle cx="15.6" cy="10.8" r="1.25" fill="#7A5A0A"/></svg>';
  function addText(el, str) {
    var parts = str.split("\uD83E\uDE99"); // the coin emoji
    parts.forEach(function (part, i) {
      if (part) el.appendChild(document.createTextNode(part));
      if (i < parts.length - 1) {
        var c = document.createElement("span");
        c.className = "chat-coin-wrap";
        c.innerHTML = COIN_SVG;
        el.appendChild(c);
      }
    });
  }

  function renderBody(el, text) {
    var re = /(\+?\d[\d\s().-]{6,}\d)/g;
    var last = 0;
    var m;
    while ((m = re.exec(text))) {
      var digits = m[1].replace(/[^\d]/g, "");
      // QA B10: dates (2026-10-21, 21.10.2026), times and short numbers are not phone numbers.
      if (/^\d{4}[-.\/]\d{1,2}[-.\/]\d{1,2}$|^\d{1,2}[-.\/]\d{1,2}[-.\/]\d{2,4}$/.test(m[1].trim()) || digits.length < 8 || digits.length > 15) continue;
      if (m.index > last) addText(el, text.slice(last, m.index));
      var a = document.createElement("a");
      a.href = "tel:" + m[1].replace(/[^\d+]/g, "");
      a.textContent = m[1];
      el.appendChild(a);
      last = m.index + m[1].length;
    }
    if (last < text.length) addText(el, text.slice(last));
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
    if (msg.mascot === "alert" || msg.mascot === "urgent") row.className += " is-alert";
    if (booted) row.className += " is-new";
    var p = document.createElement("p");
    renderBody(p, String(msg.body || ""));
    row.appendChild(p);
    if (msg.links && msg.links.length) {
      // SIPO never navigates on its own (QA B21/B24): it offers buttons, the owner taps.
      var links = document.createElement("div");
      links.className = "sipo-links";
      msg.links.forEach(function (l) {
        if (!l || !l.url) return;
        var external = /^https:\/\/www\.google\.com\/maps\//.test(l.url);
        if (!external && !/^[a-z0-9-]+\.php(\?[^\s]*)?$/i.test(l.url)) return; // app pages or Google Maps only
        var a = document.createElement("a");
        a.className = "sipo-link-btn";
        a.href = l.url;
        if (external) { a.target = "_blank"; a.rel = "noopener"; }
        a.textContent = (l.label || "Open") + " →";
        links.appendChild(a);
      });
      if (links.childNodes.length) row.appendChild(links);
    }
    if (msg.admin_note) {
      // Only admins get admin_note from the server; never part of SIPO's words.
      var note = document.createElement("small");
      note.className = "sipo-admin-note";
      note.textContent = "Admin: " + msg.admin_note;
      row.appendChild(note);
    }
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
    try { body.tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
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

  function applyReply(j, thanked) {
    if (j.assistant && j.assistant.body) {
      bubble(j.assistant);
      var mood = j.assistant.mascot || "idle";
      if (thanked && (mood === "idle" || mood === "happy")) mood = "love";
      restState = SERIOUS[mood] ? mood : calm();
      setFace(SERIOUS[mood] ? mood : mood === "idle" ? calm() : mood);
      if (mood === "happy" || mood === "celebrate" || mood === "love") {
        setTimeout(function () { if (!busy && !SERIOUS[restState]) setFace(calm()); }, mood === "happy" ? 4000 : 5500);
      }
    }
    if (j.progress) showProgress(j.progress);
    showChoices(j.choices || (j.assistant && j.assistant.choices));
    if (typeof j.coins === "number") {
      document.querySelectorAll("[data-coin-balance]").forEach(function (el) {
        if (Number(el.textContent) !== j.coins) { el.textContent = j.coins; el.parentNode.classList.add("is-bump"); setTimeout(function () { el.parentNode.classList.remove("is-bump"); }, 900); }
      });
    }
    if (j.reminders && j.reminders.length) j.reminders.forEach(armReminder);
    if (j.pet && !vm.thread.onboarding) syncPetName(j.pet);
    if (j.pet && vm.thread.onboarding) {
      // SIPO just created the pet: this chat now belongs to it.
      vm.thread.onboarding = false;
      var clip = document.getElementById("sipoAttach"); // the paperclip appears once the pet exists
      if (clip) clip.hidden = false;
      vm.thread.pet = j.pet;
      idleStatus = "Looking after " + j.pet.name;
      document.dispatchEvent(new CustomEvent("sipo:pet-created", { detail: j.pet }));
      try { history.replaceState(null, "", "si-chat.php"); } catch (e) {}
      input.placeholder = "Tell SIPO about " + j.pet.name + "…";
    }
  }

  // One source of truth: every reply carries the pet as saved in the database. If the name there differs
  // from what this page shows (SIPO just renamed the pet), every place that shows it is updated at once.
  function syncPetName(pet) {
    if (!pet || !pet.id || !pet.name) return;
    var changed = false;
    document.querySelectorAll('[data-pet-name="' + pet.id + '"]').forEach(function (el) {
      if (el.textContent !== pet.name) { el.textContent = pet.name; changed = true; }
    });
    if (vm.thread.pet && vm.thread.pet.name !== pet.name) { vm.thread.pet.name = pet.name; changed = true; }
    if (changed) {
      idleStatus = "Looking after " + pet.name;
      if (!busy) status.innerHTML = 'Looking after <span data-pet-name="' + pet.id + '"></span>';
      var tag = status.querySelector("[data-pet-name]");
      if (tag) tag.textContent = pet.name;
      input.placeholder = "Tell SIPO about " + pet.name + "…";
    }
  }

  // Short reminders ("in 10 minutes") fire from the open app exactly on time; the cron covers the rest.
  var armed = {};
  function armReminder(r) {
    if (armed[r.id]) return; // the same reminder can arrive from several replies
    armed[r.id] = true;
    var due = Date.parse(String(r.due_utc).replace(" ", "T") + "Z");
    var wait = due - Date.now();
    if (!(wait > -60000 && wait < 3 * 3600000)) return;
    askNotifications();
    setTimeout(function () {
      var text = "⏰ Reminder: " + r.title;
      try {
        if (window.Notification && Notification.permission === "granted" && navigator.serviceWorker) {
          navigator.serviceWorker.ready.then(function (reg) {
            reg.showNotification("SIPO", { body: text, icon: "assets/icons/icon-192.png", tag: "sipo-rem-" + r.id });
          });
        }
      } catch (e) {}
      setFace("happy");
      post(api.reminderFired, { reminder_id: r.id }).then(function (j) {
        if (j.assistant) bubble(j.assistant); // "already": another copy of the app posted it
      }).catch(function () {
        bubble({ role: "assistant", body: text, created_at: new Date().toISOString() });
      });
    }, Math.max(0, wait));
  }

  var notifAsked = false;
  function askNotifications() {
    if (notifAsked || !window.Notification || Notification.permission === "granted") return;
    notifAsked = true;
    var box = document.createElement("div");
    box.className = "sipo-msg from-sipo sipo-notif";
    box.innerHTML = "<p>Turn on notifications so I can reach you when it's time 🔔</p>";
    var b = document.createElement("button");
    b.type = "button";
    b.className = "btn primary";
    b.textContent = "Turn on notifications";
    b.addEventListener("click", function () {
      var done = function () { box.remove(); };
      if (window.RRPPush && window.RRPPush.enable) window.RRPPush.enable().then(done, done);
      else Notification.requestPermission().then(done, done);
    });
    box.appendChild(b);
    log.appendChild(box);
    scrollDown();
  }

  // Profile progress in the header (owner asked to "show the progress").
  var progressEl = document.getElementById("sipoProgress");
  // Tap the setup bar: a checklist of the 4 sections (QA B11).
  var setupList = (vm.thread && vm.thread.progress && vm.thread.progress.list) || null;
  var checklistEl = null;
  function toggleChecklist() {
    if (checklistEl) { checklistEl.remove(); checklistEl = null; return; }
    if (!setupList || !progressEl) return;
    checklistEl = document.createElement("div");
    checklistEl.className = "sipo-checklist";
    setupList.forEach(function (sec, i) {
      var row = document.createElement("div");
      row.className = "sipo-check-row" + (sec.done ? " is-done" : "");
      var b = document.createElement("b");
      b.textContent = (sec.done ? "✓ " : (i + 1) + ". ") + sec.label;
      row.appendChild(b);
      if (!sec.done && sec.missing && sec.missing.length) {
        var sm = document.createElement("small");
        sm.textContent = "Still needed: " + sec.missing.join(", ");
        row.appendChild(sm);
      }
      checklistEl.appendChild(row);
    });
    progressEl.parentNode.appendChild(checklistEl);
  }
  if (progressEl) {
    progressEl.style.cursor = "pointer";
    progressEl.addEventListener("click", toggleChecklist);
    progressEl.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); toggleChecklist(); } });
  }

  function showProgress(p) {
    if (!progressEl || !p) return;
    if (p.list) setupList = p.list;
    if (checklistEl) { checklistEl.remove(); checklistEl = null; }
    progressEl.hidden = p.done >= p.total;
    progressEl.setAttribute("aria-valuenow", String(p.done));
    progressEl.querySelector("span").style.width = p.percent + "%";
    progressEl.querySelector("b").textContent = p.done + "/" + p.total;
    var sec = progressEl.querySelector("[data-setup-section]");
    if (sec) sec.textContent = p.section ? "· " + p.section : "";
    if (p.done >= p.total) setStatus("Setup complete 🎉");
  }

  // Tap-to-answer buttons under SIPO's question; "Other" lets the owner type anything.
  var choicesEl = null;
  function clearChoices() {
    if (choicesEl) { choicesEl.remove(); choicesEl = null; }
  }
  function showChoices(options) {
    clearChoices();
    if (!options || !options.length) return;
    choicesEl = document.createElement("div");
    choicesEl.className = "sipo-choices";
    choicesEl.setAttribute("role", "group");
    choicesEl.setAttribute("aria-label", "Quick answers");
    options.concat(["Other…"]).forEach(function (label, i) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "sipo-choice" + (i === options.length ? " is-other" : "");
      b.textContent = label;
      b.addEventListener("click", function () {
        if (busy) return;
        if (i === options.length) {
          clearChoices();
          input.focus();
          return;
        }
        clearChoices();
        send(label);
      });
      choicesEl.appendChild(b);
    });
    log.appendChild(choicesEl);
    scrollDown();
  }

  function send(text) {
    busy = true;
    sendBtn.disabled = true;
    setFace("thinking");
    setStatus("Thinking…");
    clearChoices();
    var thanked = THANKS.test(text);
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
          setFace(calm());
          if (j.gate) showGate(j);
          else bubble({ role: "assistant", body: j.error || "Something went wrong. Try again.", created_at: new Date().toISOString() });
          return;
        }
        showGate(null);
        applyReply(j, thanked);
      })
      .catch(function () {
        clearTimeout(writingTimer);
        showTyping(false);
        setFace(calm());
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
        else setFace(calm());
      })
      .catch(function () {
        showTyping(false);
        setFace(calm());
      });
  }

  function poll() {
    if (busy || document.hidden) return;
    fetch(api.messages + "?thread_id=" + threadId + "&after_id=" + lastId, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) return;
        (j.messages || []).forEach(function (m) { bubble(m); });
        if (j.pet && !vm.thread.onboarding) syncPetName(j.pet);
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
    if (!busy) setFace(input.value.trim() ? "listening" : SERIOUS[restState] ? restState : calm());
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

  // Attach: photo or PDF → SIPO reads it, saves the data, deletes the file, replies.
  var fileInput = document.getElementById("sipoFile");
  function sendFiles(files) {
      files = files.slice(0, 10);
      if (!files.length || busy) return;
      if (vm.thread.onboarding) {
        bubble({ role: "assistant", body: "Tell me your pet's name first, then you can send photos and files.", created_at: new Date().toISOString() });
        return;
      }
      var bad = files.filter(function (f) { return !/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(f.type); });
      var big = files.filter(function (f) { return f.size > 10 * 1024 * 1024; });
      if (bad.length) {
        bubble({ role: "assistant", body: "I can read photos (JPG, PNG, WebP) and PDFs only.", created_at: new Date().toISOString() });
        return;
      }
      if (big.length) {
        bubble({ role: "assistant", body: "A file is over 10 MB, so try a smaller photo or the PDF.", created_at: new Date().toISOString() });
        return;
      }
      busy = true;
      sendBtn.disabled = true;
      setFace("thinking");
      setStatus(files.length > 1 ? "Reading your " + files.length + " files…" : "Reading your file…");
      var mine = bubble({ role: "user", body: "📎 " + (files.length > 1 ? files.length + " files" : files[0].name), created_at: new Date().toISOString() });
      // Show the photos themselves in the bubble (this device only: files are not kept on the server).
      if (mine) {
        var shots = files.filter(function (f) { return /^image\//.test(f.type); }).slice(0, 4);
        if (shots.length) {
          var strip = document.createElement("div");
          strip.className = "sipo-thumbs";
          shots.forEach(function (f) {
            var im = document.createElement("img");
            im.alt = f.name;
            im.src = URL.createObjectURL(f);
            im.addEventListener("click", function () { window.open(im.src, "_blank", "noopener"); });
            strip.appendChild(im);
          });
          mine.insertBefore(strip, mine.querySelector("p"));
        }
      }
      showTyping(true);
      window.sipoUploadMany(files, {
        url: api.upload, csrf: api.csrf, threadId: threadId,
        onStep: function (i, n) { setStatus("Reading file " + i + " of " + n + "…"); }
      })
        .then(function (j) {
          showTyping(false);
          if (!j.ok) {
            setFace(calm());
            bubble({ role: "assistant", body: j.error || "I couldn't read that file.", created_at: new Date().toISOString() });
            return;
          }
          // Replace the placeholder with what SIPO saved from the file.
          if (mine && j.message) {
            mine.querySelector("p").textContent = j.message.body;
            seen[j.message.id] = true;
            if (j.message.id > lastId) lastId = j.message.id;
          }
          applyReply(j);
        })
        .catch(function () {
          showTyping(false);
          setFace(calm());
          bubble({ role: "assistant", body: "Upload failed. Check your connection and try again.", created_at: new Date().toISOString() });
        })
        .then(function () {
          busy = false;
          sendBtn.disabled = false;
          setStatus();
        });
  }
  if (fileInput) {
    fileInput.addEventListener("change", function () {
      var picked = [].slice.call(fileInput.files || []);
      fileInput.value = "";
      sendFiles(picked);
    });
  }

  // Paste a screenshot or copied image/PDF straight into the message box.
  function filesFrom(dt) {
    if (!dt) return [];
    var out = [].slice.call(dt.files || []);
    if (!out.length && dt.items) {
      [].forEach.call(dt.items, function (it) { if (it.kind === "file") { var f = it.getAsFile(); if (f) out.push(f); } });
    }
    return out.map(function (f, i) {
      // Screenshots arrive as "image.png" with no real name.
      return f.name && f.name !== "image.png" ? f : new File([f], "screenshot-" + (i + 1) + ".png", { type: f.type || "image/png" });
    });
  }
  input.addEventListener("paste", function (e) {
    var files = filesFrom(e.clipboardData);
    if (!files.length) return; // plain text paste: leave it alone
    e.preventDefault();
    sendFiles(files);
  });

  // Drag files onto the chat and drop them to send.
  var dropZone = document.querySelector(".sipo-chat") || form.parentNode;
  var dragDepth = 0;
  function hasFiles(e) { return e.dataTransfer && [].indexOf.call(e.dataTransfer.types || [], "Files") !== -1; }
  dropZone.addEventListener("dragenter", function (e) { if (!hasFiles(e)) return; e.preventDefault(); dragDepth++; dropZone.classList.add("is-drop"); });
  dropZone.addEventListener("dragover", function (e) { if (!hasFiles(e)) return; e.preventDefault(); if (e.dataTransfer) e.dataTransfer.dropEffect = "copy"; });
  dropZone.addEventListener("dragleave", function (e) { if (!hasFiles(e)) return; dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) dropZone.classList.remove("is-drop"); });
  dropZone.addEventListener("drop", function (e) {
    if (!hasFiles(e)) return;
    e.preventDefault();
    dragDepth = 0;
    dropZone.classList.remove("is-drop");
    sendFiles(filesFrom(e.dataTransfer));
  });
  // A file dropped outside the chat must not make the browser leave the page.
  ["dragover", "drop"].forEach(function (t) { window.addEventListener(t, function (e) { if (hasFiles(e)) e.preventDefault(); }); });

  // Initial render
  (vm.messages || []).forEach(bubble);
  var lastMsg = (vm.messages || [])[(vm.messages || []).length - 1];
  if (lastMsg && lastMsg.role === "assistant" && lastMsg.choices) showChoices(lastMsg.choices);
  setFace(calm());
  booted = true;
  showGate(vm.gate);
  if (!vm.gate && vm.auto_ask) {
    // Came from "Ask SIPO" on another page: ask about that page, once (not again on reload).
    try { history.replaceState(history.state, "", location.pathname); } catch (e) {}
    send(vm.auto_ask);
  } else if (!vm.gate) greet();
  setInterval(poll, vm.poll_ms || 20000);
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) poll();
  });
})();
