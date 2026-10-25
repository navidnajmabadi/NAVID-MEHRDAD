<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$plan = rrp_owner_plan($owner);
$nav = rrp_owner_nav_context((int) $owner['id']);

rrp_layout_head('Plus — SIPAW');
rrp_app_start(['nav' => 'home']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Plus</h2></div>
  <span></span>
</div>
<div class="pad stack" style="gap:16px">
  <p class="label">Plan</p>
  <h1 class="title" style="font-size:28px"><?= $plan === 'plus' ? 'You are on Plus' : 'Free → Plus' ?></h1>
  <p class="sub">Free: social + 1 pet. Plus: up to 3 pets, SIPO SI chat, health/wellness, care reminders, food photo tips.</p>
  <div class="box">
    <p class="label">Included on Plus</p>
    <ul class="sub" style="margin:12px 0 0;padding-left:18px;font-size:14px;line-height:1.6">
      <li>3 active pets</li>
      <li>SIPO SI chat (daily caps)</li>
      <li>Wellness check-ins + trends</li>
      <li>Care reminders + .ics export</li>
      <li>Food photo soft rating</li>
    </ul>
  </div>
  <?php if ($plan !== 'plus'): ?>
    <p class="sub" style="font-size:13px">Stripe checkout ships in T21–T22. For now, ops can set <span class="data">owners.plan = plus</span> in phpMyAdmin for testers.</p>
    <a class="btn primary" href="support.php">Ask about Plus</a>
  <?php else: ?>
    <a class="btn solid" href="si-chat.php">Talk to SIPO</a>
  <?php endif; ?>
  <div class="gap"></div>
</div>
<?php rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]); ?>
