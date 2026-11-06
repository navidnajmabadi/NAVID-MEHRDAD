/* Tells SIPAW when something breaks in the browser (a script error or a failed request), so
   Admin → Errors shows it. Only runs for signed-in pages (needs the CSRF token); max 5 per page. */
(function () {
  var el = document.querySelector("[data-csrf]");
  var csrf = el ? el.getAttribute("data-csrf") : "";
  if (!csrf || !window.fetch) return;
  var sent = 0, realFetch = window.fetch.bind(window);

  function report(kind, message, source, line, stack) {
    if (sent >= 5 || !message) return;
    sent++;
    var fd = new FormData();
    fd.append("csrf", csrf);
    fd.append("kind", kind);
    fd.append("message", String(message).slice(0, 400));
    fd.append("page", location.pathname + location.search.slice(0, 80));
    fd.append("source", source || "");
    fd.append("line", line || 0);
    if (stack) fd.append("stack", String(stack).slice(0, 3000));
    realFetch("api/client-error.php", { method: "POST", credentials: "same-origin", body: fd }).catch(function () {});
  }

  window.addEventListener("error", function (e) {
    if (e && e.message && !/ResizeObserver|Script error\.?$/.test(e.message)) {
      report("js", e.message, e.filename, e.lineno, e.error && e.error.stack);
    }
  });
  window.addEventListener("unhandledrejection", function (e) {
    var r = e && e.reason;
    var msg = r && (r.message || String(r));
    if (msg && !/AbortError|NetworkError|Failed to fetch|Load failed/.test(msg)) report("js", "Unhandled: " + msg, "", 0, r && r.stack);
  });
  window.fetch = function (input, init) {
    return realFetch(input, init).then(function (res) {
      var url = typeof input === "string" ? input : (input && input.url) || "";
      if (res.status >= 500 && url.indexOf("client-error") === -1) report("http", "HTTP " + res.status + " from " + url.split("?")[0], "", 0, "");
      return res;
    });
  };
})();
