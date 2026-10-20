<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$notes = rrp_notifications_for_owner((int) $owner['id'], 50);
$scans = rrp_scans_for_owner((int) $owner['id'], 30);
$nearbyLost = rrp_lost_inbox_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
rrp_mark_notifications_read((int) $owner['id']);

$filter = (string) ($_GET['f'] ?? 'all');
$allowed = ['all', 'scans', 'lost', 'health', 'messages'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

rrp_layout_head('Alerts — siPaw');
rrp_app_start(['nav' => 'alerts']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Notifications</h2></div>
  <span style="width:40px"></span>
</div>

<div class="pad" style="padding-bottom:8px">
  <div class="chips" role="group" aria-label="Filter">
    <?php
    $chips = [
        'all' => 'All',
        'scans' => 'Tag scans',
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

  <?php if (in_array($filter, ['all', 'scans'], true)): ?>
    <?php foreach ($scans as $s): ?>
      <div class="event">
        <span class="d <?= !empty($s['active']) ? 'on' : '' ?>"></span>
        <div>
          <b><?= rrp_h($s['pet_name']) ?>’s tag was scanned</b>
          <small><?= rrp_h($s['mode']) ?> · <?= rrp_h($s['scanned_at']) ?> UTC</small>
        </div>
        <time><?= !empty($s['active']) ? 'LIVE' : '' ?></time>
      </div>
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
        if ($filter === 'all' && $type === 'scan') {
            continue; // already listed via scans
        }
        $shown++;
        $href = !empty($n['lost_alert_id']) ? 'lost-alert.php?id=' . (int) $n['lost_alert_id'] : '#';
    ?>
      <a class="event" href="<?= rrp_h($href) ?>">
        <span class="d"></span>
        <div>
          <b><?= rrp_h($n['title']) ?></b>
          <small><?= rrp_h(rrp_clip((string) $n['body'], 90)) ?></small>
        </div>
        <time><?= rrp_h(substr((string) $n['created_at'], 5, 11)) ?></time>
      </a>
    <?php endforeach; ?>
    <?php if ($filter === 'health' && $shown === 0): ?>
      <div class="box"><p class="sub" style="margin:0">No health alerts yet. Vaccine due dates will show here.</p></div>
    <?php endif; ?>
    <?php if ($filter === 'messages' && $shown === 0): ?>
      <div class="box"><p class="sub" style="margin:0">Finder chat messages will appear here. <span class="soon">Coming soon</span></p></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($filter === 'all' && !$scans && !$notes && !$nearbyLost): ?>
    <div class="box"><p class="sub" style="margin:0">No alerts yet. You’ll get a note when a tag is scanned or a nearby pet is lost.</p></div>
  <?php endif; ?>

  <div class="box blue" style="margin-top:8px">
    <p style="margin:0 0 8px;font-weight:500">Phone notifications</p>
    <p class="sub" style="margin:0 0 12px;font-size:13px" id="pushBanner" data-push-hint>Enable push so scans reach you even when the app is closed.</p>
    <button type="button" class="btn outline" id="pushEnableBtn" data-push-enable>Enable notifications</button>
  </div>
  <div class="gap"></div>
</div>
<?php
rrp_app_end(['nav' => 'alerts', 'unread' => 0, 'lost_active' => $nav['lost_active']]);
