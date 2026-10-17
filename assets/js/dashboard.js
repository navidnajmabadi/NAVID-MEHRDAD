(function () {
  "use strict";

  var map = null;
  var marker = null;
  var scanList = document.getElementById("scanList");
  var mapCard = document.getElementById("mapCard");
  var mapMeta = document.getElementById("mapMeta");

  function renderScans(scans) {
    if (!scanList) return;
    if (!scans || !scans.length) {
      scanList.innerHTML =
        '<p class="empty">No scans yet. Print a QR and ask someone to scan it on another phone.</p>';
      return;
    }
    scanList.innerHTML = scans
      .map(function (s) {
        var coords =
          s.lat != null
            ? " · " + s.lat.toFixed(5) + ", " + s.lng.toFixed(5)
            : "";
        return (
          '<div class="panel panel-soft" style="margin-top:10px;margin-bottom:0;display:flex;justify-content:space-between;gap:10px;align-items:center">' +
          "<div><strong>" +
          escapeHtml(s.pet_name) +
          '</strong><div class="meta">' +
          escapeHtml(s.mode) +
          " · " +
          escapeHtml(s.scanned_at) +
          " UTC" +
          coords +
          "</div></div>" +
          '<span class="pill ' +
          (s.active ? "pill-live" : "pill-ended") +
          '">' +
          (s.active ? "LIVE" : "ended") +
          "</span></div>"
        );
      })
      .join("");
  }

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function updateMap(live) {
    if (!mapCard || typeof L === "undefined") return;
    if (!live) {
      mapCard.style.display = "none";
      return;
    }
    mapCard.style.display = "block";
    if (mapMeta) {
      mapMeta.textContent =
        live.pet_name + " · updated " + live.updated_at + " UTC";
    }
    if (!map) {
      map = L.map("map").setView([live.lat, live.lng], 15);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap",
      }).addTo(map);
      marker = L.marker([live.lat, live.lng])
        .addTo(map)
        .bindPopup(live.pet_name + " — finder shared live location")
        .openPopup();
    } else {
      marker.setLatLng([live.lat, live.lng]);
      map.panTo([live.lat, live.lng]);
    }
    setTimeout(function () {
      map.invalidateSize();
    }, 50);
  }

  function poll() {
    fetch("api/dashboard.php", { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) return;
        renderScans(data.scans);
        updateMap(data.live);
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 4000);
})();
