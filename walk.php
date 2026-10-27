<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// GPS walk tracker (brief §C2). Web GPS stops when the app is in the background,
// so the page keeps the screen awake and asks the owner to keep SIPAW open.
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
if (!$pets) {
    rrp_redirect('si-chat.php?new=1');
}
$pet = rrp_pet_by_id(rrp_page_pet_id($owner, $pets), (int) $owner['id']);
$csrf = rrp_csrf_token();
$area = rrp_owner_area($owner);

rrp_layout_head('Walk — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />'
        . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />',
    'body_class' => 'page-care page-walk',
]);
rrp_app_start(['nav' => false]);
?>
<div class="topbar">
  <a class="iconbtn" href="daily.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Walk · <?= rrp_h($pet['name']) ?></h2></div>
  <span style="width:40px"></span>
</div>
<div class="walk-map" id="walkMap" aria-label="Map"></div>
<div class="pad stack walk-panel">
  <div class="walk-stats" aria-live="polite">
    <div><b class="data" id="wTime">0:00</b><small>time</small></div>
    <div><b class="data" id="wKm">0.00</b><small>km</small></div>
    <div><b class="data" id="wPace">–</b><small>min/km</small></div>
  </div>
  <p class="meta" id="wNote">Keep SIPAW open on screen while you walk — phones pause GPS for web apps in the background.</p>
  <button class="btn primary" id="wStart" type="button">Start walk</button>
  <button class="btn outline" id="wStop" type="button" hidden>Finish &amp; save</button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function(){
  var csrf = <?= json_encode($csrf) ?>, petId = <?= (int) $pet['id'] ?>;
  var home = [<?= json_encode($area['lat'] ?: 43.65) ?>, <?= json_encode($area['lng'] ?: -79.38) ?>];
  var map = L.map('walkMap', {zoomControl:false}).setView(home, 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'&copy; OSM'}).addTo(map);
  var line = L.polyline([], {color:'#2E6AFF', weight:5}).addTo(map);
  var dot = null, pts = [], meters = 0, startMs = 0, watch = null, tick = null, lock = null;
  var $ = function(id){ return document.getElementById(id); };

  function hav(a, b){
    var R = 6371000, r = Math.PI / 180;
    var dLat = (b[0] - a[0]) * r, dLng = (b[1] - a[1]) * r;
    var x = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(a[0]*r) * Math.cos(b[0]*r) * Math.sin(dLng/2) * Math.sin(dLng/2);
    return 2 * R * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }
  function render(){
    var s = Math.floor((Date.now() - startMs) / 1000);
    $('wTime').textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    $('wKm').textContent = (meters / 1000).toFixed(2);
    $('wPace').textContent = meters > 150 ? ((s / 60) / (meters / 1000)).toFixed(1) : '–';
  }
  function onPos(p){
    var c = p.coords;
    if (c.accuracy > 40) return;               // skip poor fixes
    var pt = [c.latitude, c.longitude];
    if (pts.length) {
      var d = hav(pts[pts.length - 1], pt);
      if (d < 4) return;                        // standing still / jitter
      if (d > 120) { pts.push(pt); line.addLatLng(pt); return; } // GPS jump: draw, don't count
      meters += d;
    }
    pts.push(pt);
    line.addLatLng(pt);
    if (!dot) dot = L.circleMarker(pt, {radius:7, color:'#fff', fillColor:'#2E6AFF', fillOpacity:1}).addTo(map);
    dot.setLatLng(pt);
    map.panTo(pt);
    render();
  }
  async function keepAwake(){
    try { if ('wakeLock' in navigator) lock = await navigator.wakeLock.request('screen'); } catch (e) {}
  }
  document.addEventListener('visibilitychange', function(){ if (watch !== null && !document.hidden) keepAwake(); });

  $('wStart').addEventListener('click', function(){
    if (!navigator.geolocation) { $('wNote').textContent = 'This browser cannot use GPS. Log the walk on the Daily page instead.'; return; }
    startMs = Date.now();
    watch = navigator.geolocation.watchPosition(onPos, function(err){
      $('wNote').textContent = err.code === 1 ? 'Location is off for SIPAW. Allow it in your phone settings, or log the walk by hand.' : 'Looking for GPS…';
    }, {enableHighAccuracy:true, maximumAge:3000, timeout:20000});
    tick = setInterval(render, 1000);
    keepAwake();
    $('wStart').hidden = true; $('wStop').hidden = false;
    $('wNote').textContent = 'Walking… have fun!';
  });

  $('wStop').addEventListener('click', function(){
    if (watch !== null) navigator.geolocation.clearWatch(watch);
    clearInterval(tick);
    if (lock) { try { lock.release(); } catch (e) {} }
    var secs = Math.floor((Date.now() - startMs) / 1000);
    if (secs < 60) { $('wNote').textContent = 'Too short to save (under a minute).'; $('wStart').hidden = false; $('wStop').hidden = true; return; }
    $('wStop').disabled = true;
    $('wNote').textContent = 'Saving…';
    fetch(<?= json_encode(rrp_asset('api/walks/save.php')) ?>, {
      method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({csrf: csrf, pet_id: petId, seconds: secs, meters: Math.round(meters), started_ms: startMs, route: pts})
    }).then(function(r){ return r.json(); }).then(function(j){
      if (!j.ok) throw new Error(j.error || 'save failed');
      location.href = 'daily.php';
    }).catch(function(e){
      $('wStop').disabled = false;
      $('wNote').textContent = 'Could not save (' + e.message + '). Try again — your walk is still here.';
    });
  });
  window.addEventListener('beforeunload', function(e){ if (watch !== null && !$('wStop').disabled) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
