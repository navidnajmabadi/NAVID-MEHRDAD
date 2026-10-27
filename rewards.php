<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Rewards: paw coins, this week's care progress, invite friends. Rules stay light on purpose (brief §F).
$owner = rrp_require_owner();
$nav = rrp_owner_nav_context((int) $owner['id']);
$balance = rrp_coin_balance((int) $owner['id']);
$history = rrp_coin_history((int) $owner['id'], 15);
$week = rrp_week_progress($owner, rrp_week_start($owner));
$pct = rrp_week_discount_pct($owner);
$ref = rrp_referral_progress((int) $owner['id']);
$link = rrp_referral_link($owner);

rrp_layout_head('Rewards — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care page-rewards',
]);
rrp_app_start(['nav' => 'home']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Rewards</h2></div>
  <span style="width:40px"></span>
</div>
<div class="pad stack care-page">
  <section class="care-status is-good rw-hero">
    <?= rrp_coin_svg(52) ?>
    <div><h2 class="data"><?= $balance ?></h2><p class="sub">paw coins · earned by caring for your pets 🐾</p></div>
  </section>

  <section class="care-card">
    <h3 class="label">This week</h3>
    <p class="sub">Do every reminder and check in on <?= RRP_WEEK_MIN_ACTIVE_DAYS ?> days for a perfect week: 5% off your next Plus month each, up to 20%.</p>
    <dl class="care-kv" style="margin-top:12px">
      <div><dt>Days checked in</dt><dd><b class="data"><?= (int) $week['active_days'] ?></b> / <?= (int) $week['need_days'] ?></dd></div>
      <div><dt>Reminders done</dt><dd><b class="data"><?= (int) $week['done'] ?></b> / <?= (int) $week['due'] ?></dd></div>
    </dl>
    <div class="care-meter" style="margin-top:10px"><span style="width:<?= (int) min(100, round(100 * min(1, $week['active_days'] / max(1, $week['need_days'])) * ($week['due'] ? $week['done'] / $week['due'] : 1))) ?>%"></span></div>
    <p class="meta"><?= $week['perfect'] ? '🌟 On track for a perfect week!' : 'Keep going, SIPO will cheer you on.' ?> · Saved so far: <b><?= $pct ?>% off</b></p>
  </section>

  <section class="care-card">
    <h3 class="label">Invite friends</h3>
    <p class="sub">Every 3 friends who join Plus = <b>1 free month</b> for you. It stacks, forever.</p>
    <div class="care-sharebox"><input type="text" readonly value="<?= rrp_h($link) ?>" id="refLink" aria-label="Your invite link" /><button type="button" class="btn primary" id="shareRef">Share</button></div>
    <div class="rw-dots" aria-label="<?= 3 - $ref['to_next'] ?> of 3 friends paid">
      <?php for ($i = 0; $i < RRP_REFERRALS_PER_MONTH; $i++): ?><span class="<?= $i < RRP_REFERRALS_PER_MONTH - $ref['to_next'] ? 'is-on' : '' ?>">🐾</span><?php endfor; ?>
      <small><?= RRP_REFERRALS_PER_MONTH - $ref['to_next'] ?>/3 to your next free month · <?= (int) $ref['signed_up'] ?> joined, not paid yet</small>
    </div>
    <?php if ($ref['free_months'] > 0): ?><p class="daily-window">🎁 You have <?= (int) $ref['free_months'] ?> free month<?= $ref['free_months'] === 1 ? '' : 's' ?>. <a class="link" href="plus.php">Use it</a></p><?php endif; ?>
  </section>

  <?php if ($history): ?>
  <section class="care-card">
    <h3 class="label">Recent coins</h3>
    <ul class="care-routines" role="list">
      <?php foreach ($history as $h): ?>
        <li><span class="dot" aria-hidden="true" style="background:<?= (int) $h['amount'] >= 0 ? 'var(--blue)' : 'var(--red)' ?>"></span>
          <div><b><?= rrp_h((string) $h['description']) ?></b><small><?= rrp_h(substr((string) $h['created_at'], 0, 16)) ?> UTC</small></div>
          <span class="chip-s"><?= (int) $h['amount'] > 0 ? '+' : '' ?><?= (int) $h['amount'] ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
<script>
(function(){
  var b = document.getElementById('shareRef'), u = document.getElementById('refLink');
  b && b.addEventListener('click', function(){
    var data = {title: 'SIPAW', text: 'SIPO keeps track of my pet\'s care. Join me:', url: u.value};
    if (navigator.share) { navigator.share(data).catch(function(){}); return; }
    u.select();
    (navigator.clipboard ? navigator.clipboard.writeText(u.value) : Promise.reject()).then(function(){ b.textContent = 'Copied'; }, function(){ document.execCommand('copy'); b.textContent = 'Copied'; });
  });
})();
</script>
<?php
rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
