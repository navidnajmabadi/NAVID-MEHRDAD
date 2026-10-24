(function () {
  "use strict";

  var cfg = window.RRP_SCAN || {};
  var statusEl = document.getElementById("status");
  var stopBtn = document.getElementById("stopLive");
  var liveScanId = null;
  var watchId = null;
  var liveTimer = null;

  function sipo(state) {
    if (window.Sipo && typeof window.Sipo.setState === "function") {
      window.Sipo.setState(state);
      return;
    }
    var el = document.getElementById("sipo") || document.querySelector("sipo-mascot");
    if (el) el.setAttribute("state", state);
  }

  function showStatus(msg) {
    if (!statusEl) return;
    statusEl.style.display = "block";
    statusEl.textContent = msg;
  }

  function post(payload) {
    return fetch("api/scan.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(
        Object.assign({ csrf: cfg.csrf, pet_public_id: cfg.petPublicId }, payload)
      ),
    }).then(function (r) {
      return r.json();
    });
  }

  function share(mode) {
    if (!navigator.geolocation) {
      sipo("alert");
      showStatus("Your browser doesn’t support location sharing.");
      return;
    }
    sipo("thinking");
    showStatus("Requesting permission…");
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        post({
          action: "share",
          mode: mode,
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
        })
          .then(function (res) {
            if (!res.ok) {
              sipo("alert");
              showStatus(res.error || "Could not share location.");
              return;
            }
            if (mode === "one-time") {
              sipo("happy");
              showStatus("Thanks — location shared once with the owner.");
              return;
            }
            liveScanId = res.scan_id;
            watchId = navigator.geolocation.watchPosition(
              function (p) {
                post({
                  action: "update",
                  scan_id: liveScanId,
                  lat: p.coords.latitude,
                  lng: p.coords.longitude,
                });
              },
              function (err) {
                showStatus("Live sharing error: " + err.message);
              },
              { enableHighAccuracy: true, maximumAge: 5000 }
            );
            liveTimer = setTimeout(stopLive, 30 * 60 * 1000);
            if (stopBtn) stopBtn.style.display = "block";
            sipo("happy");
            showStatus("Sharing live location for 30 minutes. You can stop anytime.");
          })
          .catch(function () {
            sipo("alert");
            showStatus("Network error — try again.");
          });
      },
      function (err) {
        sipo("alert");
        showStatus("Location not shared: " + err.message);
      },
      { enableHighAccuracy: true, timeout: 12000 }
    );
  }

  function stopLive() {
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }
    if (liveTimer) {
      clearTimeout(liveTimer);
      liveTimer = null;
    }
    if (liveScanId) {
      post({ action: "stop", scan_id: liveScanId });
      liveScanId = null;
    }
    if (stopBtn) stopBtn.style.display = "none";
    showStatus("Live sharing stopped.");
  }

  var onceBtn = document.getElementById("shareOnce");
  var liveBtn = document.getElementById("shareLive");
  if (onceBtn) onceBtn.addEventListener("click", function () { share("one-time"); });
  if (liveBtn) liveBtn.addEventListener("click", function () { share("live"); });
  if (stopBtn) stopBtn.addEventListener("click", stopLive);

  window.addEventListener("beforeunload", function () {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
  });
})();
