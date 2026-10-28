<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Settings hub (left tab): profile, pets, home area, Lost mode, plan, help.
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
$plan = rrp_owner_plan($owner);
$canAdd = rrp_vm_pet_switcher($owner)['can_add'];
$initial = strtoupper(substr((string) ($owner['name'] ?? 'U'), 0, 1));

rrp_layout_head('Settings — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'settings']);
?>
<div class="topbar">
  <span></span>
  <div class="center"><h2>Settings</h2></div>
  <?php rrp_coin_badge($owner); ?>
</div>
<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>

  <a class="care-status set-me" href="account.php">
    <span class="avatar"><?= rrp_h($initial) ?></span>
    <div>
      <h2><?= rrp_h((string) ($owner['name'] ?? 'Your profile')) ?></h2>
      <p class="sub"><?= rrp_h((string) ($owner['email'] ?? '')) ?> · Edit profile, phone and address</p>
    </div>
  </a>

  <?php if ($nav['lost_active']): ?>
    <a class="btn set-lost" href="lost.php">🚨 Lost mode is on. Open it</a>
  <?php elseif ($pets): ?>
    <a class="btn set-lost" href="lost.php">🚨 My pet is lost</a>
  <?php endif; ?>

  <section class="care-card">
    <h3 class="label">My pets</h3>
    <ul class="set-list">
      <?php foreach ($pets as $p): ?>
        <li><a href="pet-profile.php?id=<?= (int) $p['id'] ?>"><?php rrp_pet_avatar(rrp_sipo_pet_brief($p)); ?><span><b><?= rrp_h($p['name']) ?></b><small class="meta"><?= rrp_h(ucfirst((string) ($p['type'] ?? ''))) ?><?= !empty($p['breed']) ? ' · ' . rrp_h($p['breed']) : '' ?></small></span><i aria-hidden="true">›</i></a></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($canAdd): ?>
      <a class="btn primary" href="si-chat.php?new=1" style="margin-top:10px">➕ Add a new pet</a>
      <p class="meta">SIPO sets up the new profile with you 🐾</p>
    <?php else: ?>
      <p class="sub">Free includes 1 pet for now. <a class="link" href="plus.php">Plus adds up to 3</a></p>
    <?php endif; ?>
  </section>

  <section class="care-card">
    <h3 class="label">App</h3>
    <ul class="set-list">
      <li><a href="alerts.php"><span>🔔</span><span><b>Notifications</b><small class="meta"><?= $nav['unread'] ? (int) $nav['unread'] . ' unread' : 'All caught up' ?></small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="area.php"><span>📍</span><span><b>Home area</b><small class="meta">Used for nearby Lost mode alerts</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="plus.php"><span>⭐</span><span><b><?= $plan === 'plus' ? 'Plus' : 'Free plan' ?></b><small class="meta"><?= $plan === 'plus' ? 'Plan details' : 'See Plus' ?></small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="rewards.php"><span>🐾</span><span><b>Rewards</b><small class="meta">Paw coins and invite friends</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="dashboard.php"><span>🏠</span><span><b>Home overview</b><small class="meta">All pets on one screen</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="support.php"><span>💬</span><span><b>Help and support</b><small class="meta">We reply within 7 days</small></span><i aria-hidden="true">›</i></a></li>
      <?php if (rrp_is_super_owner($owner)): ?><li><a href="<?= rrp_h(rrp_asset('admin/')) ?>"><span>🛠️</span><span><b>Admin</b></span><i aria-hidden="true">›</i></a></li><?php endif; ?>
    </ul>
  </section>

  <a class="btn outline" href="logout.php">Log out</a>
  <p class="meta" style="text-align:center"><a class="link" href="legal.php">Terms and privacy</a></p>
</div>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
