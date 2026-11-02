/* Notification bell: a popup list with times, "Read all", and the counter cleared. No separate page. */
(function () {
  var bell = document.querySelector("[data-bell]");
  if (!bell) return;
  var csrf = bell.getAttribute("data-csrf") || "";
  var pop = null, open = false, items = [];
  var dot = bell.querySelector(".top-bell-dot");

  function setCount(n) {
    if (dot) { dot.textContent = n > 99 ? "99" : String(n); dot.hidden = n < 1; }
    bell.setAttribute("aria-label", "Notifications" + (n ? ", " + n + " unread" : ""));
  }
  function ago(iso) {
    var d = new Date(iso), s = Math.max(0, (Date.now() - d.getTime()) / 1000);
    if (s < 60) return "just now";
    if (s < 3600) return Math.floor(s / 60) + " min ago";
    var t = d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    var today = new Date(); today.setHours(0, 0, 0, 0);
    if (d >= today) return Math.floor(s / 3600) + " h ago · " + t;
    var y = new Date(today); y.setDate(y.getDate() - 1);
    if (d >= y) return "Yesterday · " + t;
    return d.toLocaleDateString([], { day: "numeric", month: "short" }) + " · " + t;
  }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  var ICON = { lost: "🚨", health: "🩺", sipo: "💙", chat: "💬", account: "⭐", other: "🔔" };

  function build() {
    pop = el("div", "bell-pop");
    pop.setAttribute("role", "dialog"); pop.setAttribute("aria-label", "Notifications"); pop.hidden = true;
    document.body.appendChild(pop);
  }
  function render(j) {
    pop.textContent = "";
    var head = el("div", "bell-head");
    head.appendChild(el("b", null, "Notifications"));
    var all = el("button", "bell-all", "Read all"); all.type = "button"; all.disabled = !j.unread;
    all.addEventListener("click", function () {
      var fd = new FormData(); fd.append("csrf", csrf); fd.append("act", "read_all");
      fetch("api/notifications.php", { method: "POST", credentials: "same-origin", body: fd }).then(function (r) { return r.json(); }).then(function (r) {
        if (r && r.ok) { items.forEach(function (i) { i.read = true; }); setCount(0); render({ unread: 0, items: items, push: j.push }); }
      });
    });
    head.appendChild(all);
    var clr = el("button", "bell-all", "Clear read"); clr.type = "button";
    clr.disabled = !j.items.some(function (i) { return i.read; });
    clr.addEventListener("click", function () {
      var fd = new FormData(); fd.append("csrf", csrf); fd.append("act", "clear_read");
      fetch("api/notifications.php", { method: "POST", credentials: "same-origin", body: fd }).then(function (r) { return r.json(); }).then(function (r) {
        if (r && r.ok) { items = items.filter(function (i) { return !i.read; }); render({ unread: j.unread, items: items, push: j.push }); }
      });
    });
    head.appendChild(clr);
    pop.appendChild(head);
    var list = el("ul", "bell-list"); list.setAttribute("role", "list");
    if (!j.items.length) list.appendChild(el("li", "bell-empty", "Nothing yet. Reminders and SIPO check-ins will show here."));
    j.items.forEach(function (n) {
      var li = el("li", n.read ? "" : "is-unread");
      var a = el("a", "bell-item"); a.href = n.href;
      a.appendChild(el("span", "bell-ico", ICON[n.type] || ICON.other));
      var txt = el("span", "bell-txt");
      txt.appendChild(el("b", null, n.title));
      if (n.body) txt.appendChild(el("small", null, n.body));
      var tm = el("time", null, ago(n.at)); tm.setAttribute("datetime", n.at); tm.title = new Date(n.at).toLocaleString();
      txt.appendChild(tm);
      a.appendChild(txt);
      li.appendChild(a); list.appendChild(li);
    });
    pop.appendChild(list);
    var foot = el("div", "bell-foot");
    var note = el("small", null, "");
    foot.appendChild(note);
    pop.appendChild(foot);
    if (window.RRPPush && j.push && j.push.configured) {
      window.RRPPush.status().then(function (s) {
        if (!s.subscribed && s.permission !== "denied" && s.supported && !(s.ios && !s.standalone)) {
          var b = el("button", "btn outline bell-push", "Turn on phone notifications"); b.type = "button";
          b.addEventListener("click", function () {
            window.RRPPush.enable().then(function (r) { note.textContent = r && r.ok ? "Phone notifications are on 💙" : "Could not turn them on. Check the browser's site settings."; b.hidden = !!(r && r.ok); });
          });
          foot.insertBefore(b, note);
        } else if (s.permission === "denied") {
          note.textContent = "Notifications are blocked for this site in your browser settings.";
        }
      });
    }
  }
  function load() {
    return fetch("api/notifications.php", { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.ok) return;
      items = j.items; setCount(j.unread); if (open) render(j);
      return j;
    });
  }
  function place() {
    var r = bell.getBoundingClientRect();
    if (window.innerWidth < 560) { pop.style.left = "8px"; pop.style.right = "8px"; pop.style.top = (r.bottom + 8) + "px"; }
    else { pop.style.left = "auto"; pop.style.right = Math.max(8, window.innerWidth - r.right) + "px"; pop.style.top = (r.bottom + 8) + "px"; }
  }
  function close() { if (!open) return; open = false; pop.hidden = true; bell.setAttribute("aria-expanded", "false"); }
  bell.addEventListener("click", function (e) {
    e.preventDefault();
    if (!pop) build();
    if (open) { close(); return; }
    open = true; place(); pop.hidden = false; bell.setAttribute("aria-expanded", "true");
    render({ unread: Number(dot && !dot.hidden ? dot.textContent : 0), items: items, push: null });
    load();
  });
  document.addEventListener("click", function (e) { if (open && !pop.contains(e.target) && !bell.contains(e.target)) close(); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") { close(); bell.focus(); } });
  window.addEventListener("resize", function () { if (open) place(); });
  setInterval(function () { if (!document.hidden) load(); }, 60000);
})();
