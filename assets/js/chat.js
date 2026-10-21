(function () {
  "use strict";

  var cfg = window.RRP_CHAT;
  if (!cfg || !cfg.threadId) return;

  var msgsEl = document.getElementById("chatMsgs");
  var form = document.getElementById("chatComposer");
  var input = document.getElementById("chatInput");
  var sendBtn = document.getElementById("chatSend");
  var quick = document.getElementById("chatQuick");
  var lastId = Number(cfg.lastId || 0);
  var sending = false;
  var apiUrl = cfg.apiUrl || "api/chat.php";

  function esc(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function formatTime(iso) {
    if (!iso) return "";
    // "YYYY-MM-DD HH:MM:SS" UTC → show HH:MM
    var m = String(iso).match(/(\d{2}):(\d{2})/);
    return m ? m[1] + ":" + m[2] : "";
  }

  function appendMessage(msg) {
    if (!msgsEl || !msg || !msg.id) return;
    if (document.getElementById("msg-" + msg.id)) return;
    lastId = Math.max(lastId, Number(msg.id));

    var mine =
      (cfg.role === "owner" && msg.sender_role === "owner") ||
      (cfg.role === "finder" && msg.sender_role === "finder");

    var empty = msgsEl.querySelector(".chat-empty");
    if (empty) empty.remove();

    var div = document.createElement("div");
    div.className = "msg " + (mine ? "me" : "them");
    div.id = "msg-" + msg.id;
    div.innerHTML = esc(msg.body) + "<time>" + esc(formatTime(msg.created_at)) + "</time>";
    msgsEl.appendChild(div);
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  function setBusy(busy) {
    sending = busy;
    if (sendBtn) sendBtn.disabled = busy;
    if (input) input.disabled = busy;
  }

  function sendBody(text) {
    text = String(text || "").trim();
    if (!text || sending) return;
    setBusy(true);
    fetch(apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({
        action: "send",
        thread_id: cfg.threadId,
        body: text,
        as: cfg.role,
        csrf: cfg.csrf,
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        setBusy(false);
        if (!data || !data.ok) {
          window.alert((data && data.error) || "Could not send.");
          return;
        }
        if (input) input.value = "";
        appendMessage(data.message);
      })
      .catch(function () {
        setBusy(false);
        window.alert("Network error. Try again.");
      });
  }

  function poll() {
    var url =
      apiUrl +
      "?action=messages&thread_id=" +
      encodeURIComponent(cfg.threadId) +
      "&after_id=" +
      encodeURIComponent(lastId);
    fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok || !data.messages) return;
        data.messages.forEach(appendMessage);
      })
      .catch(function () {});
  }

  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      sendBody(input && input.value);
    });
  }

  if (quick) {
    quick.addEventListener("click", function (e) {
      var btn = e.target.closest("button[data-quick]");
      if (!btn) return;
      sendBody(btn.getAttribute("data-quick") || btn.textContent);
    });
  }

  if (msgsEl) {
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  setInterval(poll, 4000);
})();
