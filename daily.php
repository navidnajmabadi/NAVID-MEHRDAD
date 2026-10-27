<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Daily life for the current pet: bathroom, walks, grooming (brief §C).
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
if (!$pets) {
    rrp_redirect('si-chat.php?new=1');
}
$pet = rrp_pet_by_id(rrp_page_pet_id($owner, $pets), (int) $owner['id']);
$vm = rrp_vm_daily($owner, $pet);
$nav = rrp_owner_nav_context((int) $owner['id']);
$csrf = rrp_csrf_token();
$b = $vm['bath'];
$name = $vm['pet']['name'];

rrp_layout_head('Daily — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />'
        . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />',
    'body_class' => 'page-care page-daily',
]);
rrp_app_start(['nav' => 'health']);
?>
<div class="topbar care-top">
  <div class="center" style="text-align:left"><h2>Daily</h2></div>
  <?php rrp_pet_switcher(rrp_vm_pet_switcher($owner)); ?>
</div>

<div class="pad stack care-page">
  <nav class="daily-tabs" aria-label="Health sections">
    <a href="care.php">Health</a><a href="daily.php" aria-current="page">Daily</a>
  </nav>

  <section class="care-card">
    <h3 class="label">Bathroom today</h3>
    <div class="daily-quick" role="group" aria-label="Log for <?= rrp_h($name) ?>">
      <button type="button" class="btn outline" data-log="pee">💧 Pee</button>
      <button type="button" class="btn outline" data-log="poop">💩 Poop</button>
      <button type="button" class="btn outline" data-log="poop" data-stool="diarrhea">Runny</button>
      <button type="button" class="btn outline" data-log="accident">Accident</button>
      <button type="button" class="btn outline" data-log="vomit">Vomit</button>
    </div>
    <p class="meta" id="dailyNote" aria-live="polite">Tap after it happens, or just tell SIPO.</p>
    <dl class="care-kv daily-counts">
      <div><dt>Pee</dt><dd><b class="data"><?= (int) $b['today']['pee'] ?></b> <span class="meta"><?= $b['learning'] ? '' : '· usual ' . rrp_h((string) $b['usual']['pee']) ?></span></dd></div>
      <div><dt>Poop</dt><dd><b class="data"><?= (int) $b['today']['poop'] ?></b> <span class="meta"><?= $b['learning'] ? '' : '· usual ' . rrp_h((string) $b['usual']['poop']) ?></span></dd></div>
      <div><dt>Accidents</dt><dd><b class="data"><?= (int) $b['today']['accident'] ?></b></dd></div>
    </dl>
    <?php if ($b['learning']): ?>
      <p class="sub">SIPO is still learning <?= rrp_h($name) ?>'s usual pattern (<?= (int) $b['usual']['logging_days'] ?> of 4 days logged).</p>
    <?php endif; ?>
    <?php foreach ($b['signals'] as $s): ?>
      <p class="daily-signal is-<?= rrp_h($s['level']) ?>"><?= $s['level'] === 'vet' ? '⚠ ' : '' ?><?= rrp_h($s['text']) ?></p>
    <?php endforeach; ?>
    <?php $max = max(1, max(array_map(static fn($d) => $d['pee'] + $d['poop'], $b['days']))); ?>
    <div class="daily-bars" role="img" aria-label="Last 15 days: pee and poop per day">
      <?php foreach ($b['days'] as $d): ?>
        <span class="daily-bar" title="<?= rrp_h($d['date'] . ': ' . $d['pee'] . ' pee, ' . $d['poop'] . ' poop') ?>">
          <i class="b-pee" style="height:<?= round(100 * $d['pee'] / $max) ?>%"></i><i class="b-poop" style="height:<?= round(100 * $d['poop'] / $max) ?>%"></i>
        </span>
      <?php endforeach; ?>
    </div>
    <p class="meta"><span class="key b-pee"></span> pee <span class="key b-poop"></span> poop · last 15 days</p>
  </section>

  <?php if ($vm['is_dog']): $w = $vm['walks']; ?>
  <section class="care-card">
    <h3 class="label">Walks</h3>
    <?php if ($vm['window']): ?><p class="daily-window">☀ <?= rrp_h($vm['window']['text']) ?></p><?php endif; ?>
    <div class="daily-walk-top">
      <div><b class="data daily-big"><?= (int) $w['today_minutes'] ?></b> min today<?= $w['goal'] ? ' of ' . (int) $w['goal'] : '' ?> · <b class="data"><?= rrp_h((string) $w['today_km']) ?></b> km</div>
      <a class="btn primary" href="walk.php">Start walk</a>
    </div>
    <?php if ($w['goal']): ?>
      <div class="care-meter"><span style="width:<?= min(100, (int) round(100 * $w['today_minutes'] / max(1, $w['goal']))) ?>%"></span></div>
    <?php else: ?>
      <p class="meta">No daily goal yet — ask your vet what suits <?= rrp_h($name) ?>, then tell SIPO.</p>
    <?php endif; ?>
    <?php $wm = max(1, $w['goal'] ?? 0, max(array_column($w['week'], 'minutes'))); ?>
    <div class="daily-week" role="img" aria-label="Walk minutes this week">
      <?php foreach ($w['week'] as $d): ?>
        <span><i style="height:<?= round(100 * $d['minutes'] / $wm) ?>%" class="<?= $w['goal'] && $d['minutes'] >= $w['goal'] ? 'is-goal' : '' ?>"></i><small><?= rrp_h(substr($d['dow'], 0, 2)) ?></small></span>
      <?php endforeach; ?>
    </div>
    <p class="meta">This week <?= (int) $w['week_minutes'] ?> min · <?= rrp_h((string) $w['week_km']) ?> km<?= $w['streak'] ? ' · 🔥 ' . (int) $w['streak'] . '-day streak' : '' ?></p>
    <p class="label" style="margin:16px 0 0">Log a walk by hand</p>
    <form class="daily-manual" id="walkManual">
      <input type="number" name="minutes" min="1" max="600" placeholder="Minutes" inputmode="numeric" required aria-label="Minutes" />
      <input type="number" name="km" min="0" max="60" step="0.1" placeholder="km (optional)" inputmode="decimal" aria-label="Kilometres" />
      <button class="btn outline" type="submit">Save</button>
    </form>
    <?php if (!empty($w['last']['route'])): ?>
      <div id="lastRoute" class="daily-map" data-route="<?= rrp_h(json_encode($w['last']['route'])) ?>" aria-label="Last walk route"></div>
      <p class="meta">Last walk: <?= (int) $w['last']['minutes'] ?> min<?= $w['last']['distance_m'] ? ', ' . round($w['last']['distance_m'] / 1000, 2) . ' km' : '' ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="care-card">
    <h3 class="label">Grooming · teeth · baths</h3>
    <ul class="care-routines" role="list">
      <?php foreach ($vm['grooming'] as $r): ?>
        <li class="is-<?= rrp_h($r['state']) ?>"><span class="dot" aria-hidden="true"></span>
          <div><b><?= rrp_h($r['title']) ?></b>
            <small><?= $r['last_done'] ? 'Last ' . rrp_h($r['last_done']) . ' · ' : '' ?><?= $r['next_due'] ? 'Next ' . rrp_h($r['next_due']) : 'Not set up' ?></small>
            <small class="meta"><?= rrp_h($r['when']) ?> — <?= rrp_h($r['note']) ?></small></div>
          <span class="chip-s"><?= rrp_h(['ok' => 'On track', 'due_soon' => 'Soon', 'overdue' => 'Overdue', 'unknown' => 'Ask SIPO'][$r['state']]) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="meta">Tell SIPO your rhythm ("brush teeth every 2 days") and it will remind you.</p>
  </section>
</div>
<script>
(function(){
  var csrf = <?= json_encode($csrf) ?>, petId = <?= (int) $pet['id'] ?>;
  var note = document.getElementById('dailyNote');
  function post(body){
    body.csrf = csrf; body.pet_id = petId;
    return fetch(<?= json_encode(rrp_asset('api/daily/log.php')) ?>, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)}).then(function(r){ return r.json(); });
  }
  document.querySelectorAll('[data-log]').forEach(function(btn){
    btn.addEventListener('click', function(){
      btn.disabled = true;
      post({what: btn.getAttribute('data-log'), stool: btn.getAttribute('data-stool')}).then(function(j){
        note.textContent = j.ok ? 'Logged ✓' : (j.error || 'Could not save.');
        if (j.ok) setTimeout(function(){ location.reload(); }, 500);
      }).catch(function(){ note.textContent = 'No connection.'; }).then(function(){ btn.disabled = false; });
    });
  });
  var f = document.getElementById('walkManual');
  f && f.addEventListener('submit', function(e){
    e.preventDefault();
    post({what:'walk', minutes: Number(f.minutes.value), km: f.km.value === '' ? null : Number(f.km.value)}).then(function(j){
      if (j.ok) location.reload(); else alert(j.error || 'Could not save.');
    });
  });
  var map = document.getElementById('lastRoute');
  // Leaflet loads at the end of the page.
  map && window.addEventListener('load', function(){
    if (!window.L) return;
    var pts = JSON.parse(map.getAttribute('data-route') || '[]');
    var m = L.map(map, {zoomControl:false, attributionControl:true});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:18, attribution:'&copy; OSM'}).addTo(m);
    var line = L.polyline(pts, {color:'#2E6AFF', weight:4}).addTo(m);
    m.fitBounds(line.getBounds(), {padding:[16,16]});
  });
})();
</script>
<?php
rrp_app_end([
    'nav' => 'health', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active'],
    'scripts' => !empty($vm['walks']['last']['route']) ? ['https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js'] : [],
]);
