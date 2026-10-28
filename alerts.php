<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$notes = rrp_notifications_for_owner((int) $owner['id'], 50);
$scans = []; // tags and QR scans were removed
$nearbyLost = rrp_lost_inbox_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
rrp_mark_notifications_read((int) $owner['id']);

$filter = (string) ($_GET['f'] ?? 'all');
$allowed = ['all', 'lost', 'health', 'messages'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

rrp_layout_head('Alerts — SIPAW');
rrp_app_start(['nav' => 'alerts']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Notifications</h2></div>
  <span style="width:40px"></span>
</div>

<div class="pad stack" style="gap:12px;padding-bottom:8px">
  <div class="box" id="pushNag" data-push-nag>
    <p class="label">Stay in the loop</p>
    <p class="sub" data-push-hint style="margin-top:8px;font-size:13px">Enable phone notifications for reminders, Lost nearby, and SIPO check-ins.</p>
    <button type="button" class="btn primary" id="pushEnableBtn" data-push-enable style="margin-top:12px">Enable notifications</button>
  </div>
  <div class="chips" role="group" aria-label="Filter">
    <?php
    $chips = [
        'all' => 'All',
        'lost' => 'Lost nearby',
        'health' => 'Health',
        'messages' => 'Messages',
    ];
    foreach ($chips as $k => $label):
        $pressed = $filter === $k ? 'true' : 'false';
    ?>
      <a class="chip" href="alerts.php?f=<?= rrp_h($k) ?>" aria-pressed="<?= $pressed ?>"><?= rrp_h($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="pad stack" style="gap:10px;padding-top:8px">
  <?php if (in_array($filter, ['all', 'lost'], true) && $nearbyLost): ?>
    <div class="sipo-inline" style="margin-bottom:4px">
      <sipo-mascot id="sipo" state="alert" theme="ice-blue" size="56" animated="true" label="Sipo alert — lost nearby"></sipo-mascot>
      <p class="sub" style="margin:0;font-size:13px">Lost pets nearby need eyes on the street.</p>
    </div>
    <?php foreach ($nearbyLost as $la): ?>
      <a class="event" href="lost-alert.php?id=<?= (int) $la['id'] ?>">
        <span class="d red"></span>
        <div>
          <b>Lost nearby: <?= rrp_h($la['pet_name']) ?></b>
          <small>~<?= rrp_h((string) $la['distance_km']) ?> km · <?= rrp_h($la['magic_word'] ?: 'no magic word') ?></small>
        </div>
        <time>LOST</time>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (in_array($filter, ['all', 'health', 'messages'], true)): ?>
    <?php
    $shown = 0;
    foreach ($notes as $n):
        $type = (string) ($n['type'] ?? '');
        if ($filter === 'health' && $type !== 'health') {
            continue;
        }
        if ($filter === 'messages' && !in_array($type, ['message', 'chat'], true)) {
            continue;
        }
        if ($type === 'scan') {
            continue; // tags and QR scans were removed
        }
        $shown++;
        if ($type === 'chat' || $type === 'message') {
            // Prefer deep-link into Chat if body contains chat.php?thread=
            if (preg_match('/chat\\.php\\?thread=(\\d+)/', (string) $n['body'], $mm)) {
                $href = 'chat.php?thread=' . (int) $mm[1];
            } else {
                $href = 'chat.php';
            }
        } elseif (!empty($n['lost_alert_id'])) {
            $href = 'lost-alert.php?id=' . (int) $n['lost_alert_id'];
        } elseif ($type === 'health') {
            $href = 'health-care.php';
        } else {
            $href = 'alerts.php';
        }
    ?>
      <a class="event" href="<?= rrp_h($href) ?>">
        <span class="d<?= $type === 'chat' || $type === 'message' ? ' on' : '' ?>"></span>
        <div>
          <b><?= rrp_h($n['title']) ?></b>
          <small><?= rrp_h(rrp_clip((string) $n['body'], 90)) ?></small>
        </div>
        <time><?= rrp_h(substr((string) $n['created_at'], 5, 11)) ?></time>
      </a>
    <?php endforeach; ?>
    <?php if ($filter === 'health' && $shown === 0): ?>
      <div class="box"><p class="sub" style="margin:0">No health alerts yet. Vaccine and med reminders show here when due.</p></div>
    <?php endif; ?>
    <?php if ($filter === 'messages' && $shown === 0): ?>
      <div class="box"><p class="sub" style="margin:0">No messages yet. When someone who found your pet writes to you, it shows here.</p></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($filter === 'all' && !$scans && !$notes && !$nearbyLost): ?>
    <div class="box"><p class="sub" style="margin:0">No alerts yet. Reminders, SIPO check-ins and lost pets nearby show here.</p></div>
  <?php endif; ?>

  <div class="gap"></div>
</div>
<?php if (!empty($nearbyLost) && in_array($filter, ['all', 'lost'], true)): ?>
<script>
window.SIPO_ASSET_BASE = <?= json_encode(rtrim(rrp_asset('assets/mascot/public'), '/')) ?>;
</script>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<?php endif; ?>
<?php
rrp_app_end(['nav' => 'alerts', 'unread' => 0, 'lost_active' => $nav['lost_active']]);
