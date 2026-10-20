<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        if ($action === 'delete_pet') {
            rrp_delete_pet((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
            rrp_flash_set('ok', 'Pet removed.');
            rrp_redirect('dashboard.php');
        }
        if ($action === 'lost_trigger') {
            $result = rrp_trigger_lost((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
            if (!empty($result['ok'])) {
                $n = (int) ($result['recipients'] ?? 0);
                rrp_flash_set(
                    'ok',
                    $n > 0
                        ? "Lost mode sent to {$n} nearby owner" . ($n === 1 ? '' : 's') . '.'
                        : 'Lost mode started. No nearby owners within 25 km yet — alerts will still escalate on schedule.'
                );
            } else {
                rrp_flash_set('error', $result['error'] ?? 'Could not start alert.');
            }
            rrp_redirect('lost.php');
        }
        if ($action === 'lost_found') {
            $result = rrp_mark_lost_found((int) $owner['id'], (int) ($_POST['alert_id'] ?? 0));
            if (!empty($result['ok'])) {
                $alert = rrp_lost_by_id((int) ($_POST['alert_id'] ?? 0));
                $petName = $alert['pet_name'] ?? 'your pet';
                rrp_redirect('rescued.php?pet=' . rawurlencode($petName));
            }
            rrp_flash_set('error', $result['error'] ?? 'Could not mark found.');
            rrp_redirect('dashboard.php');
        }
    }
}

$pets = rrp_pets_by_owner((int) $owner['id']);
$scans = rrp_scans_for_owner((int) $owner['id'], 8);
$notes = rrp_notifications_for_owner((int) $owner['id'], 5);
$myLost = rrp_lost_for_owner((int) $owner['id']);
$nearbyLost = rrp_lost_inbox_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

$openLostByPet = [];
foreach ($myLost as $la) {
    if ($la['status'] === 'open') {
        $openLostByPet[(int) $la['pet_id']] = $la;
    }
}

$activeId = isset($_GET['pet']) ? (int) $_GET['pet'] : 0;
$active = null;
foreach ($pets as $p) {
    if ($activeId === (int) $p['id']) {
        $active = $p;
        break;
    }
}
if (!$active && $pets) {
    $active = $pets[0];
    $activeId = (int) $active['id'];
}

$weekScans = 0;
foreach ($scans as $s) {
    if ((int) ($s['pet_id'] ?? 0) === $activeId || !$activeId) {
        $weekScans++;
    }
}

$initial = strtoupper(substr((string) ($owner['name'] ?? 'U'), 0, 1));
$extra = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />';
rrp_layout_head('Home — siPaw', ['extra_head' => $extra]);
rrp_app_start(['nav' => 'home']);
?>
<div class="homehead">
  <a href="dashboard.php" aria-label="siPaw home"><?php rrp_logo_wordmark(); ?></a>
  <div class="row" style="gap:10px">
    <a class="iconbtn" href="alerts.php" aria-label="Notifications"><svg aria-hidden="true"><use href="#i-bell"/></svg></a>
    <a class="avatar" href="account.php" aria-label="Account"><?= rrp_h($initial) ?></a>
  </div>
</div>

<?php rrp_render_flash(); ?>

<div class="pets" id="pet-strip" role="group" aria-label="Your pets">
  <?php foreach ($pets as $i => $pet): ?>
    <?php $pressed = (int) $pet['id'] === $activeId; ?>
    <a class="petchip" href="dashboard.php?pet=<?= (int) $pet['id'] ?>" aria-pressed="<?= $pressed ? 'true' : 'false' ?>">
      <span class="ph">
        <span class="ix"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <?php if (!empty($pet['image_path'])): ?>
          <img src="<?= rrp_h($pet['image_path']) ?>" alt="" />
        <?php else: ?>
          <svg viewBox="0 0 200 200" aria-hidden="true"><use href="#<?= ($pet['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
        <?php endif; ?>
      </span>
      <?= rrp_h($pet['name']) ?>
    </a>
  <?php endforeach; ?>
  <?php if (count($pets) < RRP_MAX_PETS): ?>
    <a class="petchip add" href="pet-form.php">
      <span class="ph"><svg aria-hidden="true"><use href="#i-plus"/></svg></span>
      Add
    </a>
  <?php endif; ?>
</div>

<?php if ($active): ?>
  <?php $open = $openLostByPet[(int) $active['id']] ?? null; ?>
  <a class="petcard" href="pet-profile.php?id=<?= (int) $active['id'] ?>">
    <div class="info">
      <span class="label">Your pets</span>
      <h3><?= rrp_h($active['name']) ?></h3>
      <?php if ($open): ?>
        <span class="status red data">Lost mode on</span>
      <?php else: ?>
        <span class="status live data">Tag <?= rrp_h(substr((string) $active['public_id'], -4)) ?> active</span>
      <?php endif; ?>
      <div class="facts">
        <div><b><?= (int) $weekScans ?></b><span>Recent scans</span></div>
        <div><b><?= count($pets) ?>/<?= RRP_MAX_PETS ?></b><span>Pets</span></div>
      </div>
    </div>
    <div class="art">
      <?php if (!empty($active['image_path'])): ?>
        <img src="<?= rrp_h($active['image_path']) ?>" alt="" />
      <?php else: ?>
        <svg viewBox="0 0 200 200" aria-hidden="true"><use href="#<?= ($active['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
      <?php endif; ?>
    </div>
  </a>
<?php elseif (!$pets): ?>
  <div class="pad stack" style="margin-top:24px">
    <div class="box">
      <p class="h2" style="margin-bottom:8px">Add your first pet</p>
      <p class="sub" style="margin-bottom:16px">Generate a QR collar tag. Up to <?= RRP_MAX_PETS ?> pets per account.</p>
      <a class="btn primary" href="pet-form.php">Add a pet</a>
    </div>
  </div>
<?php endif; ?>

<div class="pad stack" style="margin-top:24px">
  <?php if ($nearbyLost): ?>
    <span class="label red">Nearby lost</span>
    <?php foreach ($nearbyLost as $la): ?>
      <a class="event" href="lost-alert.php?id=<?= (int) $la['id'] ?>">
        <span class="d red"></span>
        <div>
          <b><?= rrp_h($la['pet_name']) ?> needs help</b>
          <small>~<?= rrp_h((string) $la['distance_km']) ?> km · magic word <?= rrp_h($la['magic_word'] ?: '—') ?></small>
        </div>
        <time>LOST</time>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

  <span class="label">Latest</span>
  <?php if ($scans || $notes): ?>
    <?php foreach (array_slice($scans, 0, 3) as $s): ?>
      <a class="event" href="alerts.php">
        <span class="d <?= !empty($s['active']) ? 'on' : '' ?>"></span>
        <div>
          <b><?= rrp_h($s['pet_name']) ?>’s tag was scanned</b>
          <small><?= rrp_h($s['mode']) ?><?php if ($s['lat'] !== null): ?> · location shared<?php endif; ?></small>
        </div>
        <time><?= rrp_h(substr((string) $s['scanned_at'], 11, 5)) ?></time>
      </a>
    <?php endforeach; ?>
    <?php foreach (array_slice($notes, 0, 2) as $n): ?>
      <?php if (($n['type'] ?? '') === 'scan') {
          continue;
      } ?>
      <a class="event" href="<?= !empty($n['lost_alert_id']) ? 'lost-alert.php?id=' . (int) $n['lost_alert_id'] : 'alerts.php' ?>">
        <span class="d"></span>
        <div>
          <b><?= rrp_h($n['title']) ?></b>
          <small><?= rrp_h(rrp_clip((string) $n['body'], 80)) ?></small>
        </div>
        <time><?= rrp_h(substr((string) $n['created_at'], 11, 5)) ?></time>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="box"><p class="sub" style="margin:0">No scans yet. Print a QR and ask someone to scan it on another phone.</p></div>
  <?php endif; ?>

  <?php if ($active): ?>
    <div class="row between" style="margin-top:16px">
      <span class="label">Today</span>
      <a class="link" href="health-care.php?pet=<?= (int) $active['id'] ?>">Health</a>
    </div>
    <div class="list">
      <?php if (!empty($active['medical_pills'])): ?>
        <a class="li" href="health-care.php?pet=<?= (int) $active['id'] ?>&tab=meds">
          <div><b>Medication</b><small><?= rrp_h(rrp_clip((string) $active['medical_pills'], 60)) ?></small></div>
          <span class="data">MEDS</span>
        </a>
      <?php endif; ?>
      <?php if (!empty($active['medical_vaccines']) || !empty($active['medical_next_due'])): ?>
        <a class="li" href="health-care.php?pet=<?= (int) $active['id'] ?>&tab=vax">
          <div>
            <b>Vaccines</b>
            <small><?= rrp_h(rrp_clip((string) ($active['medical_vaccines'] ?: 'Next due ' . $active['medical_next_due']), 60)) ?></small>
          </div>
          <span class="data"><?= rrp_h($active['medical_next_due'] ? strtoupper(date('d M', strtotime((string) $active['medical_next_due']))) : 'VAX') ?></span>
        </a>
      <?php endif; ?>
      <?php if (empty($active['medical_pills']) && empty($active['medical_vaccines']) && empty($active['medical_next_due'])): ?>
        <a class="li" href="health-care.php?pet=<?= (int) $active['id'] ?>">
          <div><b>Health notes</b><small>Add vaccines, meds, and food in Health.</small></div>
          <span class="data">—</span>
        </a>
      <?php endif; ?>
    </div>

    <?php if ($open): ?>
      <a class="lostentry" href="lost.php?pet=<?= (int) $active['id'] ?>" style="border-color:var(--red)">
        <svg aria-hidden="true"><use href="#i-lost"/></svg>
        <div><b>Lost mode on</b><small><?= rrp_h($active['name']) ?> — tap to manage alerts and mark home.</small></div>
        <svg style="width:16px;height:16px;transform:rotate(180deg)" aria-hidden="true"><use href="#i-back"/></svg>
      </a>
    <?php else: ?>
      <a class="lostentry" href="lost.php?pet=<?= (int) $active['id'] ?>">
        <svg aria-hidden="true"><use href="#i-lost"/></svg>
        <div><b>Lost mode</b><small>If <?= rrp_h($active['name']) ?> goes missing, alert owners within 25 km.</small></div>
        <svg style="width:16px;height:16px;transform:rotate(180deg)" aria-hidden="true"><use href="#i-back"/></svg>
      </a>
    <?php endif; ?>
  <?php endif; ?>

  <div class="box" id="pushBanner" aria-labelledby="pushHeading" style="margin-top:8px">
    <div class="row between">
      <span class="label" id="pushHeading" data-push-title>Phone notifications</span>
      <span class="status data" data-push-badge>…</span>
    </div>
    <p class="sub" data-push-hint style="margin-top:8px;font-size:13px">Checking push status…</p>
    <button type="button" class="btn outline" id="pushEnableBtn" data-push-enable style="margin-top:12px" hidden>Enable notifications</button>
  </div>

  <section id="mapCard" style="display:none" aria-labelledby="mapHeading">
    <span class="label" id="mapHeading">Live location</span>
    <p class="sub" id="mapMeta" style="font-size:13px"></p>
    <div id="map"></div>
  </section>

  <div class="gap"></div>
  <p class="sub" style="font-size:12px;text-align:center">
    <a class="link" href="support.php">Support</a>
    ·
    <a class="link" href="logout.php">Log out</a>
  </p>
  <div class="gap"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
<script src="assets/js/dashboard.js" defer></script>
<?php
rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
