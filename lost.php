<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/views/lost_playbook.php';

$owner = rrp_require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        if ($action === 'lost_trigger') {
            // Brief §D: type the pet's name once to confirm before neighbours are alerted.
            $confirmPet = rrp_pet_by_id((int) ($_POST['pet_id'] ?? 0), (int) $owner['id']);
            $typed = mb_strtolower(trim((string) ($_POST['confirm_name'] ?? '')));
            if (!$confirmPet || $typed === '' || $typed !== mb_strtolower(trim((string) $confirmPet['name']))) {
                rrp_flash_set('error', 'Type ' . ($confirmPet['name'] ?? 'the pet\'s name') . ' exactly to confirm. No alert was sent.');
                rrp_redirect('lost.php?pet=' . (int) ($_POST['pet_id'] ?? 0));
            }
            $lat = is_numeric($_POST['seen_lat'] ?? null) ? round((float) $_POST['seen_lat'], 4) : null;
            $lng = is_numeric($_POST['seen_lng'] ?? null) ? round((float) $_POST['seen_lng'], 4) : null;
            $result = rrp_trigger_lost((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0), $lat, $lng);
            if (!empty($result['ok']) && !empty($result['alert_id'])) {
                $note = trim((string) ($_POST['seen_note'] ?? ''));
                try {
                    rrp_db()->prepare('UPDATE lost_alerts SET last_seen_lat = ?, last_seen_lng = ?, last_seen_note = ? WHERE id = ?')
                        ->execute([$lat, $lng, $note !== '' ? mb_substr($note, 0, 255) : null, (int) $result['alert_id']]);
                } catch (Throwable $e) {
                    // pre-012
                }
            }
            if (!empty($result['ok'])) {
                $n = (int) ($result['recipients'] ?? 0);
                rrp_flash_set(
                    'ok',
                    $n > 0
                        ? "Lost mode sent to {$n} nearby owner" . ($n === 1 ? '' : 's') . '.'
                        : 'Lost mode started. No owners nearby yet.'
                );
            } else {
                rrp_flash_set('error', $result['error'] ?? 'Could not start alert.');
            }
            rrp_redirect('lost.php?pet=' . (int) ($_POST['pet_id'] ?? 0));
        }
        if ($action === 'lost_found') {
            $result = rrp_mark_lost_found((int) $owner['id'], (int) ($_POST['alert_id'] ?? 0));
            if (!empty($result['ok'])) {
                $alert = rrp_lost_by_id((int) ($_POST['alert_id'] ?? 0));
                rrp_redirect('rescued.php?pet=' . rawurlencode($alert['pet_name'] ?? 'your pet'));
            }
            rrp_flash_set('error', $result['error'] ?? 'Could not mark found.');
            rrp_redirect('lost.php');
        }
    }
}

$pets = rrp_pets_by_owner((int) $owner['id']);
$myLost = rrp_lost_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

$openLostByPet = [];
foreach ($myLost as $la) {
    if ($la['status'] === 'open') {
        $openLostByPet[(int) $la['pet_id']] = $la;
    }
}

$petId = rrp_page_pet_id($owner, $pets);
$pet = null;
foreach ($pets as $p) {
    if ($petId === (int) $p['id'] || (!$petId && !$pet)) {
        $pet = $p;
        $petId = (int) $p['id'];
    }
}
$open = $pet ? ($openLostByPet[$petId] ?? null) : null;
$recipientCount = 0;
if ($open) {
    $cnt = rrp_db()->prepare('SELECT COUNT(*) FROM lost_alert_recipients WHERE lost_alert_id = ?');
    $cnt->execute([(int) $open['id']]);
    $recipientCount = (int) $cnt->fetchColumn();
}

$pingOffsets = defined('RRP_LOST_PING_OFFSETS') ? RRP_LOST_PING_OFFSETS : [0, 3600, 10800, 28800];
$pingCount = $open ? (int) ($open['ping_count'] ?? 0) : 0;

rrp_layout_head('Lost mode — SIPAW', ['extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" /><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />']);
rrp_app_start(['nav' => 'lost']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center">
    <h2>Lost mode</h2>
    <?php if ($open): ?><span class="status red data">Active</span><?php else: ?><span class="status idle data">Off</span><?php endif; ?>
  </div>
  <?php rrp_coin_badge($owner); ?>
</div>

<?php rrp_render_flash(); ?>

<?php if (!$pets): ?>
  <div class="pad stack"><div class="box"><p class="sub" style="margin:0">Add a pet before using Lost mode.</p><a class="btn primary" style="margin-top:12px" href="si-chat.php?new=1">Add a pet with SIPO</a></div></div>
<?php else: ?>
  <div class="pets" role="group" aria-label="Choose pet" style="padding-top:8px">
    <?php foreach ($pets as $i => $p): ?>
      <a class="petchip" href="lost.php?pet=<?= (int) $p['id'] ?>" aria-pressed="<?= (int) $p['id'] === $petId ? 'true' : 'false' ?>">
        <span class="ph">
          <?php if (!empty($p['image_path'])): ?><img src="<?= rrp_h($p['image_path']) ?>" alt="" /><?php else: ?>
          <svg viewBox="0 0 200 200"><use href="#<?= ($p['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg><?php endif; ?>
        </span>
        <?= rrp_h($p['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="mapbox" aria-hidden="true">
    <svg viewBox="0 0 400 230" preserveAspectRatio="xMidYMid slice"><use href="#map"/></svg>
    <div class="pulse"></div><div class="pulse d2"></div>
    <div class="maplabel data">Your home area</div>
  </div>

  <div class="pad stack" style="margin-top:16px;gap:16px">
    <?php if ($open): ?>
      <div class="counter">
        <div><b><?= $recipientCount ?></b><span>Owners</span></div>
        <div><b><?= $pingCount ?>/<?= count($pingOffsets) ?></b><span>Pings</span></div>
      </div>

      <div class="section">
        <span class="label red">Escalation</span>
        <div class="timeline">
          <?php
          $labels = [
              ['t0', 'Alert sent to nearby owners'],
              ['+1h', 'Reminder ping'],
              ['+5h', 'Final ping'],
          ];
          foreach ($labels as $i => $row):
              $cls = 'tl';
              if ($i < $pingCount) {
                  $cls .= ' done';
              } elseif ($i === $pingCount) {
                  $cls .= ' next';
              }
          ?>
            <div class="<?= $cls ?>">
              <span class="t"><?= rrp_h($row[0]) ?></span>
              <span class="rail"><i></i><span></span></span>
              <div class="body"><b><?= rrp_h($row[1]) ?></b><small>Owners near your home area</small></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lost_found" />
        <input type="hidden" name="alert_id" value="<?= (int) $open['id'] ?>" />
        <button type="submit" class="btn solid lost-found-btn">✓ Found! <?= rrp_h($pet['name'] ?? 'My pet') ?> is home</button>
      </form>
      <a class="btn outline" href="lost-alert.php?id=<?= (int) $open['id'] ?>">Alert details &amp; sightings</a>
      <p class="sub" style="font-size:12px"><?= $open['next_ping_at']
          ? 'Next reminder to neighbours: ' . rrp_h((new DateTimeImmutable((string) $open['next_ping_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner))->format('H:i'))
          : 'All 3 alerts sent. SIPO will check in with you daily until ' . rrp_h($pet['name']) . ' is home.' ?></p>
      <?php rrp_view_lost_playbook(rrp_lost_playbook($owner, $pet, $open), false); ?>
    <?php else: ?>
      <section class="box lost-intro">
        <p style="margin:0 0 8px;font-weight:600">If <?= rrp_h($pet['name'] ?? 'your pet') ?> goes missing, stay calm. Here is what Lost mode does 🧭</p>
        <ol class="lost-steps">
          <li><b>Alerts nearby SIPAW owners right away</b> with <?= rrp_h($pet['name'] ?? 'your pet') ?>'s photo, marks, magic word and the medical details that matter.</li>
          <li><b>Reminds them twice more</b>, after 1 hour and after 5 hours, so the search keeps going.</li>
          <li><b>SIPO guides you step by step</b>: where to look first for a <?= rrp_h($pet ? strtolower(rrp_pet_type_label($pet)) : 'pet') ?>, what to put out, who to call, and a printable poster.</li>
          <li><b>Neighbours can report sightings</b> on a map, and you get each one as a notification.</li>
          <li><b>Tap "home" when they're back</b> and everyone is told the good news.</li>
        </ol>
        <p class="meta" style="margin:8px 0 0">Your home address is never shown. Tip: you can also just tell SIPO "<?= rrp_h($pet['name'] ?? 'my pet') ?> is lost".</p>
      </section>
      <form method="post" class="stack lost-trigger" style="gap:10px" id="lostTrigger">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lost_trigger" />
        <input type="hidden" name="pet_id" value="<?= (int) $petId ?>" />
        <input type="hidden" name="seen_lat" id="seenLat" />
        <input type="hidden" name="seen_lng" id="seenLng" />
        <div class="field"><label for="seenNote">Where was <?= rrp_h($pet['name'] ?? 'your pet') ?> last seen?</label>
          <div class="in"><input id="seenNote" name="seen_note" maxlength="255" placeholder="e.g. back garden, park gate, 7 pm" /></div></div>
        <button type="button" class="btn outline" id="seenHere"><svg aria-hidden="true"><use href="#i-pin"/></svg>I'm where they were last seen</button>
        <div class="field"><label for="confirmName">Type <b><?= rrp_h($pet['name'] ?? '') ?></b> to confirm</label>
          <div class="in"><input id="confirmName" name="confirm_name" autocomplete="off" required placeholder="<?= rrp_h($pet['name'] ?? '') ?>" /></div>
          <small>Nearby SIPAW owners get <?= rrp_h($pet['name'] ?? 'your pet') ?>'s photo and details now, again in 1 hour, and a last time after 5 hours.</small></div>
        <button type="submit" class="btn lost" id="lostGo" disabled>Turn on Lost mode</button>
      </form>
      <script>
      (function(){
        var want = <?= json_encode(mb_strtolower(trim((string) ($pet['name'] ?? '')))) ?>;
        var input = document.getElementById('confirmName'), go = document.getElementById('lostGo');
        input.addEventListener('input', function(){ go.disabled = input.value.trim().toLowerCase() !== want; });
        document.getElementById('seenHere').addEventListener('click', function(){
          var b = this;
          if (!navigator.geolocation) { b.textContent = 'Location not available'; return; }
          b.disabled = true;
          navigator.geolocation.getCurrentPosition(function(p){
            document.getElementById('seenLat').value = p.coords.latitude.toFixed(4);
            document.getElementById('seenLng').value = p.coords.longitude.toFixed(4);
            b.textContent = '✓ Last-seen spot saved';
          }, function(){ b.disabled = false; b.textContent = 'Location not shared, add a note instead'; }, {timeout:10000});
        });
      })();
      </script>
    <?php endif; ?>

    <?php if ($pet): ?>
      <div class="section">
        <span class="label">Lost poster</span>
        <p class="sub" style="margin:8px 0 12px;font-size:13px">Download a flyer with photo, marks, and magic word.</p>
      </div>
      <div class="poster" id="lostPoster">
        <div class="top">
          <span class="lab">Lost pet</span>
          <img class="poster-logo" src="<?= rrp_h(rrp_asset('assets/img/logos/sipaw-wordmark-transparent-black.svg', true)) ?>" alt="SIPAW" width="40" height="24" />
        </div>
        <div class="hd"><?= rrp_h($pet['name']) ?></div>
        <div class="pic">
          <?php if (!empty($pet['image_path'])): ?>
            <img src="<?= rrp_h($pet['image_path']) ?>" alt="" crossorigin="anonymous" style="width:100%;height:100%;object-fit:cover" />
          <?php else: ?>
            <svg viewBox="0 0 200 200"><use href="#<?= ($pet['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
          <?php endif; ?>
        </div>
        <p><?= rrp_h(rrp_pet_type_label($pet)) ?><?= $pet['breed'] ? ' · ' . rrp_h($pet['breed']) : '' ?><?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?></p>
        <?php if (!empty($pet['identifying_marks'])): ?>
          <p><strong>Look for:</strong> <?= rrp_h($pet['identifying_marks']) ?></p>
        <?php endif; ?>
        <?php if (!empty($pet['magic_word'])): ?>
          <p><strong>Magic word:</strong> <?= rrp_h($pet['magic_word']) ?></p>
        <?php endif; ?>
        <?php if (!empty($owner['phone'])): ?>
          <p class="poster-phone"><strong>Call:</strong> <?= rrp_h((string) $owner['phone']) ?></p>
        <?php endif; ?>
        <?php $findUrl = rrp_app_url('pet.php?id=' . rawurlencode((string) $pet['public_id'])); ?>
        <div class="bar poster-find">
          <div class="poster-qr" id="posterQr" data-url="<?= rrp_h($findUrl) ?>" aria-label="QR code to contact the owner"></div>
          <div>
            <b>Seen <?= rrp_h($pet['name']) ?>?</b>
            <span>Scan the code or open the link to tell the owner where. No app needed.</span>
            <a href="<?= rrp_h($findUrl) ?>" target="_blank" rel="noopener">Open <?= rrp_h($pet['name']) ?>’s page</a>
          </div>
        </div>
      </div>
      <?php if (!empty($owner['phone'])): ?>
        <label class="poster-phone-toggle"><input type="checkbox" id="posterShowPhone" checked /> Show my phone number on the poster</label>
        <script>
        document.getElementById('posterShowPhone').addEventListener('change', function (e) { // QA B20: owner decides
          document.querySelectorAll('#lostPoster .poster-phone').forEach(function (el) { el.hidden = !e.target.checked; });
        });
        </script>
      <?php endif; ?>
      <button type="button" class="btn primary" id="posterPng">Download PNG</button>
      <button type="button" class="btn outline" id="posterPdf">Download PDF</button>
      <script>
      window.RRP_POSTER = { petName: <?= json_encode($pet['name'], JSON_UNESCAPED_UNICODE) ?> };
      </script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js" crossorigin="anonymous"></script>
      <script>
      (function () { // QR to the finder page; an <img> so the PNG/PDF download captures it
        var el = document.getElementById('posterQr');
        if (!el || typeof qrcode !== 'function') return;
        var qr = qrcode(0, 'M'); qr.addData(el.getAttribute('data-url')); qr.make();
        el.innerHTML = qr.createImgTag(4, 0);
      })();
      </script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous"></script>
      <script src="<?= rrp_h(rrp_asset('assets/js/poster.js', true)) ?>" defer></script>
    <?php endif; ?>
    <div class="gap"></div>
  </div>
<?php endif; ?>
<?php
rrp_app_end(['nav' => 'lost', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
