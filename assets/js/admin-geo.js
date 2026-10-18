(function () {
  var points = window.RRP_ADMIN_GEO || [];
  var el = document.getElementById("adminMap");
  if (!el || typeof L === "undefined") return;

  var map = L.map(el, { scrollWheelZoom: false });
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap",
    maxZoom: 18,
  }).addTo(map);

  if (!points.length) {
    map.setView([20, 0], 2);
    return;
  }

  var bounds = [];
  points.forEach(function (p) {
    var marker = L.circleMarker([p.lat, p.lng], {
      radius: 7,
      color: "#0D9488",
      fillColor: "#5EEAD4",
      fillOpacity: 0.85,
      weight: 2,
    }).addTo(map);
    marker.bindPopup(
      "<strong>" +
        escapeHtml(p.name) +
        "</strong><br/>" +
        escapeHtml(p.city || "—") +
        ", " +
        escapeHtml(p.country || "—")
    );
    bounds.push([p.lat, p.lng]);
  });
  map.fitBounds(bounds, { padding: [24, 24], maxZoom: 12 });

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
})();
