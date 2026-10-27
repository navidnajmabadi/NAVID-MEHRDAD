<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/views/lost_playbook.php';

$owner = rrp_require_owner();
$alertId = (int) ($_GET['id'] ?? 0);
$alert = $alertId > 0 ? rrp_lost_by_id($alertId) : null;

$allowed = false;
$isOwner = false;
$recipientCount = 0;
if ($alert) {
    $isOwner = (int) $alert['owner_id'] === (int) $owner['id'];
    if ($isOwner) {
        $allowed = true;
        $cnt = rrp_db()->prepare('SELECT COUNT(*) FROM lost_alert_recipients WHERE lost_alert_id = ?');
        $cnt->execute([$alertId]);
        $recipientCount = (int) $cnt->fetchColumn();
    } else {
        $chk = rrp_db()->prepare(
            'SELECT 1 FROM lost_alert_recipients WHERE lost_alert_id = ? AND recipient_owner_id = ? LIMIT 1'
        );
        $chk->execute([$alertId, (int) $owner['id']]);
        $allowed = (bool) $chk->fetchColumn();
    }
}

$nav = rrp_owner_nav_context((int) $owner['id']);

// "I saw this pet" from a helper.
if ($alert && $allowed && !$isOwner && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $r = rrp_lost_add_sighting($owner, $alert,
        is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null,
        is_numeric($_POST['lng'] ?? null) ? (float) $_POST['lng'] : null,
        (string) ($_POST['note'] ?? ''));
    rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Thank you! The owner has been told right away. 💙' : ($r['error'] ?? 'Could not send.'));
    rrp_redirect('lost-alert.php?id=' . $alertId);
}
$lostPet = $alert ? rrp_pet_by_id((int) $alert['pet_id'], (int) $alert['owner_id']) : null;
$lostOwner = $alert ? (rrp_db()->query('SELECT * FROM owners WHERE id = ' . (int) $alert['owner_id'])->fetch() ?: null) : null;
$playbook = ($alert && $allowed && $lostPet && $lostOwner) ? rrp_lost_playbook($lostOwner, $lostPet, $alert) : null;
$sightings = ($alert && $allowed) ? rrp_lost_sightings($alertId) : [];
// Map centre: last-seen point, else the owner's area — rounded to ~100 m so a home is never pinpointed.
$mapLat = $alert ? round((float) ($alert['last_seen_lat'] ?? $alert['center_lat'] ?? 0), 3) : 0;
$mapLng = $alert ? round((float) ($alert['last_seen_lng'] ?? $alert['center_lng'] ?? 0), 3) : 0;
rrp_layout_head($alert ? ('Help find ' . $alert['pet_name'] . ' — SIPAW') : 'Alert — SIPAW', ['extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" /><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />']);
rrp_app_start(['nav' => 'lost']);
?>
<div class="topbar">
  <a class="iconbtn" href="<?= $isOwner ? 'lost.php' : 'alerts.php' ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Lost alert</h2></div>
  <span style="width:40px"></span>
</div>

<div class="pad stack" style="gap:16px">
  <?php if (!$alert || !$allowed): ?>
    <div class="sipo-inline">
      <sipo-mascot id="sipo" state="alert" theme="ice-blue" size="96" animated="true" label="Sipo, SIPaw assistant"></sipo-mascot>
    </div>
    <h2 class="title" style="font-size:28px">Alert not available</h2>
    <p class="sub">This Lost alert isn’t shared with your account, or it doesn’t exist.</p>
  <?php else: ?>
    <div class="sipo-inline">
      <sipo-mascot id="sipo" state="alert" theme="ice-blue" size="72" animated="true" label="Sipo alert — lost pet"></sipo-mascot>
    </div>
    <?php if (!empty($alert['image_path'])): ?>
      <img src="<?= rrp_h($alert['image_path']) ?>" alt="<?= rrp_h($alert['pet_name']) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border:1px solid var(--line)" />
    <?php endif; ?>
    <span class="status red data">Lost help me</span>
    <h2 class="title" style="font-size:32px">Help find <?= rrp_h($alert['pet_name']) ?></h2>
    <p class="sub">
      <?= rrp_h($alert['pet_type']) ?>
      · Alert <?= rrp_h($alert['status']) ?>
      <?= !empty($alert['last_seen_note']) ? '· Last seen: ' . rrp_h($alert['last_seen_note']) : '' ?>
    </p>

    <div class="box">
      <span class="label">Distinctive marks</span>
      <p style="margin:10px 0 0;white-space:pre-wrap"><?= rrp_h($alert['identifying_marks'] ?: '—') ?></p>
    </div>

    <div class="magic">
      <span class="label">Magic word</span>
      <b><?= rrp_h($alert['magic_word'] ?: '—') ?></b>
      <p class="sub" style="font-size:12px">Use gently if you think you’ve found them.</p>
    </div>

    <?php if (!empty($alert['medical_notes'])): ?>
      <div class="box">
        <span class="label">Medical (helpers)</span>
        <p style="margin:10px 0 0;white-space:pre-wrap"><?= rrp_h($alert['medical_notes']) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($isOwner): ?>
      <?php if ($recipientCount === 0): ?>
        <div class="flash flash-error" role="status">
          No SIPAW owners nearby yet. Check your home area is set on the <a class="link" href="area.php">map</a>.
        </div>
      <?php else: ?>
        <p class="sub" style="font-size:13px">Notified <?= (int) $recipientCount ?> nearby owner<?= $recipientCount === 1 ? '' : 's' ?>.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($alert['status'] === 'found'): ?>
      <div class="flash flash-ok">This pet was marked Found. Thank you!</div>
    <?php elseif ($alert['status'] === 'open' && $isOwner): ?>
      <form method="post" action="lost.php" class="stack" style="gap:12px">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lost_found" />
        <input type="hidden" name="alert_id" value="<?= (int) $alert['id'] ?>" />
        <button type="submit" class="btn solid">She / he is home</button>
      </form>
    <?php else: ?>
      <p class="sub" style="font-size:13px">Keep an eye out nearby. You’ll get a reminder after 1 hour and a last one after 5 hours if the pet is still missing.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($alert && $allowed && $mapLat && $mapLng): ?>
    <section class="care-card">
      <h3 class="label">Where to look</h3>
      <div id="lostMap" class="daily-map" style="height:240px" aria-label="Approximate search area"></div>
      <p class="meta">Shaded: the area where most lost <?= rrp_h(mb_strtolower((string) $alert['pet_type'])) ?>s are found. Exact homes are never shown.</p>
    </section>
  <?php endif; ?>
  <?php if ($playbook): rrp_view_lost_playbook($playbook, !$isOwner); endif; ?>
  <?php if ($alert && $allowed && $alert['status'] === 'open' && !$isOwner): ?>
    <section class="care-card">
      <h3 class="label">Seen <?= rrp_h($alert['pet_name']) ?>?</h3>
      <form method="post" class="stack" style="gap:10px" id="sawForm">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="lat" id="sawLat" /><input type="hidden" name="lng" id="sawLng" />
        <div class="field"><div class="in"><input name="note" maxlength="500" placeholder="Where and when? e.g. running toward the park, 5 min ago" /></div></div>
        <button type="button" class="btn outline" id="sawHere"><svg aria-hidden="true"><use href="#i-pin"/></svg>Add my location (rounded)</button>
        <button type="submit" class="btn lost">I saw this pet</button>
      </form>
    </section>
  <?php endif; ?>
  <?php if ($isOwner && $sightings): ?>
    <section class="care-card">
      <h3 class="label">Sightings</h3>
      <ul class="lost-spots" role="list"><?php foreach ($sightings as $s): ?>
        <li><span class="data"><?= rrp_h((new DateTimeImmutable((string) $s['seen_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner))->format('D H:i')) ?></span> <?= rrp_h((string) ($s['note'] ?: 'Seen nearby')) ?></li>
      <?php endforeach; ?></ul>
    </section>
  <?php endif; ?>
  <div class="gap"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function(){
  var el = document.getElementById('lostMap');
  if (el && window.L) {
    var c = [<?= json_encode($mapLat) ?>, <?= json_encode($mapLng) ?>];
    var m = L.map(el, {zoomControl:false}).setView(c, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:18, attribution:'&copy; OSM'}).addTo(m);
    var area = L.circle(c, {radius: <?= (int) ($playbook['radius_m'] ?? 800) ?>, color:'#FF4D4D', weight:1, fillOpacity:.12}).addTo(m);
    <?php foreach (($playbook['spots'] ?? []) as $sp): ?>
    L.circleMarker([<?= json_encode($sp['lat']) ?>, <?= json_encode($sp['lng']) ?>], {radius:6, color:'#2E6AFF', fillOpacity:.9}).addTo(m).bindTooltip(<?= json_encode($sp['label'] ?: 'Walk spot') ?>);
    <?php endforeach; ?>
    <?php foreach ($sightings as $s): if ($s['lat'] === null) continue; ?>
    L.circleMarker([<?= json_encode((float) $s['lat']) ?>, <?= json_encode((float) $s['lng']) ?>], {radius:7, color:'#fff', fillColor:'#FF4D4D', fillOpacity:1}).addTo(m).bindTooltip('Seen');
    <?php endforeach; ?>
    m.fitBounds(L.latLng(c).toBounds(<?= (int) ($playbook['radius_m'] ?? 800) ?> * 2), {padding:[10,10]});
  }
  var here = document.getElementById('sawHere');
  here && here.addEventListener('click', function(){
    if (!navigator.geolocation) return;
    here.disabled = true;
    navigator.geolocation.getCurrentPosition(function(p){
      document.getElementById('sawLat').value = p.coords.latitude.toFixed(3);
      document.getElementById('sawLng').value = p.coords.longitude.toFixed(3);
      here.textContent = '✓ Location added';
    }, function(){ here.disabled = false; here.textContent = 'Location not shared, a note is enough'; }, {timeout:10000});
  });
})();
</script>
<script>
window.SIPO_ASSET_BASE = <?= json_encode(rtrim(rrp_asset('assets/mascot/public'), '/')) ?>;
</script>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<?php
rrp_app_end(['nav' => 'lost', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
