/* Send one or several photos/PDFs to SIPO. One file: a normal upload. Several (any order): each is
   read and saved quietly, then SIPO answers once for the whole set. Resolves to the reply JSON,
   or {ok:false, error}. */
(function () {
  var OK = /^(image\/(jpeg|png|webp)|application\/pdf)$/;
  function post(url, fd) {
    return fetch(url, { method: "POST", credentials: "same-origin", body: fd }).then(function (r) {
      return r.json().catch(function () {
        var why = r.status === 413 ? "That file is too big. The limit is 10 MB."
          : r.status === 403 ? "That file name was blocked. Rename it (letters and numbers only) and try again."
          : r.status === 415 || r.status === 400 ? "I can read photos (JPG, PNG, WebP) and PDFs only."
          : "I couldn't read that file (error " + r.status + "). Try another photo or PDF.";
        return { ok: false, error: why };
      });
    });
  }
  function base(o) {
    var fd = new FormData();
    fd.append("csrf", o.csrf);
    try { fd.append("tz", Intl.DateTimeFormat().resolvedOptions().timeZone); } catch (e) {}
    fd.append("thread_id", String(o.threadId));
    return fd;
  }
  window.sipoUploadMany = function (files, o) {
    files = [].slice.call(files);
    if (files.length === 1) {
      var fd = base(o); fd.append("file", files[0]);
      return post(o.url, fd);
    }
    var bodies = [], i = 0;
    function next() {
      if (i >= files.length) {
        if (!bodies.length) return Promise.resolve({ ok: false, error: "I couldn't read any of those files." });
        var fin = base(o); fin.append("finish", "1");
        bodies.forEach(function (b) { fin.append("prior[]", b); });
        return post(o.url, fin);
      }
      var f = files[i++];
      if (o.onStep) o.onStep(i, files.length);
      var fd = base(o); fd.append("file", f); fd.append("quiet", "1");
      return post(o.url, fd).then(function (j) {
        bodies.push(j && j.ok ? j.body : "⚠ " + f.name + ": " + ((j && j.error) || "could not be read"));
      }, function () {
        bodies.push("⚠ " + f.name + ": upload failed");
      }).then(next);
    }
    return next();
  };
})();
