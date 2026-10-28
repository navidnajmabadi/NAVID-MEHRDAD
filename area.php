<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$error = null;
$area = rrp_owner_area($owner);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired.';
    } else {
        $result = rrp_save_owner_area(
            (int) $owner['id'],
            (float) ($_POST['lat'] ?? 0),
            (float) ($_POST['lng'] ?? 0),
            rrp_lost_radius_km(),
            (string) ($_POST['label'] ?? '')
        );
        if (!empty($result['ok'])) {
            rrp_flash_set('ok', 'Home area saved.');
            rrp_redirect('area.php');
        }
        $error = $result['error'] ?? 'Could not save.';
    }
    $owner = rrp_require_owner();
    $area = rrp_owner_area($owner);
}

$nav = rrp_owner_nav_context((int) $owner['id']);
$lat = $area['lat'] ?? 43.65;
$lng = $area['lng'] ?? -79.38;
$extra = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />';
rrp_layout_head('Home area — SIPAW', ['extra_head' => $extra]);
rrp_app_start(['nav' => 'settings']);
?>
<div class="topbar">
  <a class="iconbtn" href="account.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Home area</h2></div>
  <span></span>
</div>
<div class="pad stack" style="gap:16px">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error"><?= rrp_h($error) ?></div><?php endif; ?>
  <p class="sub" style="font-size:14px">Pin your home area so SIPO knows your weather and nearby vets, and Lost mode can alert neighbours. It is never shown on public pet pages.</p>
  <div id="map" style="height:280px;border:1px solid var(--line);background:var(--raised)"></div>
  <form method="post" class="stack" style="gap:12px" id="area-form">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <input type="hidden" name="lat" id="lat" value="<?= rrp_h((string) $lat) ?>" />
    <input type="hidden" name="lng" id="lng" value="<?= rrp_h((string) $lng) ?>" />
    <div class="field"><label for="label">Area label</label><div class="in"><input id="label" name="label" value="<?= rrp_h((string) ($area['label'] ?? '')) ?>" placeholder="e.g. East Toronto" /></div></div>
    <button type="button" class="btn outline" id="useGps"><svg aria-hidden="true"><use href="#i-pin"/></svg>Use my location</button>
    <p class="sub" id="gpsNote" style="font-size:13px" hidden></p>
    <button class="btn primary" type="submit">Save area</button>
  </form>
  <div class="gap"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
<script>
(function(){
  var lat = parseFloat(document.getElementById('lat').value)||43.65;
  var lng = parseFloat(document.getElementById('lng').value)||-79.38;
  var map = L.map('map').setView([lat,lng], 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:18, attribution:'&copy; OSM'}).addTo(map);
  var marker = L.marker([lat,lng], {draggable:true}).addTo(map);
  function sync(ll){
    // Approximate is enough (brief §D): round to ~100 m.
    document.getElementById('lat').value = ll.lat.toFixed(3);
    document.getElementById('lng').value = ll.lng.toFixed(3);
  }
  marker.on('dragend', function(){ sync(marker.getLatLng()); });
  map.on('click', function(e){ marker.setLatLng(e.latlng); sync(e.latlng); });
  var gps = document.getElementById('useGps');
  var note = document.getElementById('gpsNote');
  gps.addEventListener('click', function(){
    if (!navigator.geolocation) { note.hidden = false; note.textContent = 'This browser cannot share location. Tap the map instead.'; return; }
    gps.disabled = true;
    navigator.geolocation.getCurrentPosition(function(p){
      var ll = L.latLng(p.coords.latitude, p.coords.longitude);
      marker.setLatLng(ll); map.setView(ll, 14); sync(ll);
      gps.disabled = false; note.hidden = false; note.textContent = 'Got it. Drag the pin if it is a bit off, then Save.';
    }, function(){
      gps.disabled = false; note.hidden = false; note.textContent = 'Location was not shared. Tap the map near home instead.';
    }, {enableHighAccuracy:false, timeout:10000, maximumAge:600000});
  });
})();
</script>
<?php rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]); ?>
