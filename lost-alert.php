<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

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

rrp_layout_head($alert ? ('Help find ' . $alert['pet_name'] . ' — SIPAW') : 'Alert — SIPAW');
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
      · ~<?= rrp_h((string) $alert['radius_km']) ?> km radius
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
          No nearby owners within <?= rrp_h((string) $alert['radius_km']) ?> km yet.
          Check <a class="link" href="account.php">Account</a> that your address mapped correctly.
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
  <div class="gap"></div>
</div>
<script>
window.SIPO_ASSET_BASE = <?= json_encode(rtrim(rrp_asset('assets/mascot/public'), '/')) ?>;
</script>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<?php
rrp_app_end(['nav' => 'lost', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
