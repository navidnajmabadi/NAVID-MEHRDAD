(function () {
  "use strict";

  var cfg = window.RRP_SCAN || {};
  var statusEl = document.getElementById("status");
  var stopBtn = document.getElementById("stopLive");
  var liveScanId = null;
  var watchId = null;
  var liveTimer = null;

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
      showStatus("Your browser doesn’t support location sharing.");
      return;
    }
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
              showStatus(res.error || "Could not share location.");
              return;
            }
            if (mode === "one-time") {
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
            showStatus("Sharing live location for 30 minutes. You can stop anytime.");
          })
          .catch(function () {
            showStatus("Network error — try again.");
          });
      },
      function (err) {
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
