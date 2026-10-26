<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $result = rrp_update_owner_profile((int) $owner['id'], $_POST);
        if (!empty($result['ok'])) {
            rrp_flash_set('ok', 'Profile saved. Address will be used for nearby Lost mode alerts.');
            rrp_redirect('account.php');
        }
        $error = $result['error'] ?? 'Could not save.';
    }
    $owner = rrp_require_owner();
}

$stmt = rrp_db()->prepare(
    'SELECT id, name, email, phone, address, lat, lng, city, country, geocoded_at, subscription_status, wallet_balance
     FROM owners WHERE id = ? LIMIT 1'
);
$stmt->execute([(int) $owner['id']]);
$profile = $stmt->fetch() ?: $owner;
$nav = rrp_owner_nav_context((int) $owner['id']);
$initial = strtoupper(substr((string) ($profile['name'] ?? 'U'), 0, 1));

rrp_layout_head('Account — SIPAW');
rrp_app_start(['nav' => 'home']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Account</h2></div>
  <span class="avatar"><?= rrp_h($initial) ?></span>
</div>

<div class="pad stack" style="gap:16px">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <p class="sub" style="font-size:14px">Keep your address accurate — Lost mode alerts nearby owners within about 25 km.</p>

  <form method="post" class="stack" style="gap:16px" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <div class="field"><label for="name">Your name</label><div class="in"><input id="name" name="name" required value="<?= rrp_h($profile['name'] ?? '') ?>" /></div></div>
    <div class="field"><label for="phone">Phone</label><div class="in"><input id="phone" name="phone" type="tel" required value="<?= rrp_h($profile['phone'] ?? '') ?>" /></div></div>
    <div class="field"><label for="address">Registered address</label><div class="in area"><textarea id="address" name="address" required rows="3"><?= rrp_h($profile['address'] ?? '') ?></textarea></div><small>Geocoded privately. Not shown on the public scan page.</small></div>
    <div class="field"><label for="email">Email</label><div class="in"><input id="email" value="<?= rrp_h($profile['email'] ?? '') ?>" disabled /></div><small>Email cannot be changed here yet.</small></div>
    <button type="submit" class="btn primary">Save</button>
  </form>

  <div class="box">
    <span class="label">Map location</span>
    <?php if (!empty($profile['lat']) && !empty($profile['lng'])): ?>
      <p class="sub" style="margin:10px 0 0;font-size:13px">
        <?= rrp_h((string) ($profile['city'] ?? '—')) ?><?= !empty($profile['country']) ? ', ' . rrp_h((string) $profile['country']) : '' ?>
        · <span class="data"><?= rrp_h((string) $profile['lat']) ?>, <?= rrp_h((string) $profile['lng']) ?></span>
      </p>
      <p class="sub" style="margin:6px 0 0;font-size:12px">Last geocoded: <?= rrp_h((string) ($profile['geocoded_at'] ?? '—')) ?> UTC</p>
    <?php else: ?>
      <p class="sub" style="margin:10px 0 0;font-size:13px">Not mapped yet. Save your address, or wait a moment and save again.</p>
    <?php endif; ?>
  </div>

  <?php
    $plan = rrp_owner_plan($owner);
    $area = rrp_owner_area($owner);
  ?>
  <div class="box">
    <span class="label">Plan</span>
    <p class="sub" style="margin:10px 0 0;font-size:14px">
      <?php if (rrp_is_super_owner($owner)): ?>
        SUPER · unlimited pets · full Admin
      <?php else: ?>
        <?= rrp_h(strtoupper($plan)) ?> · <?= (int) rrp_pet_count((int) $owner['id']) ?> / <?= (int) rrp_owner_pet_limit($owner) ?> pets
      <?php endif; ?>
    </p>
    <?php if (rrp_is_super_owner($owner)): ?>
      <p style="margin-top:10px"><a class="link" href="<?= rrp_h(rrp_asset('admin/')) ?>">Open Admin</a></p>
    <?php else: ?>
      <p style="margin-top:10px"><a class="link" href="plus.php"><?= $plan === 'plus' ? 'Plus details' : 'Upgrade to Plus' ?></a></p>
    <?php endif; ?>
  </div>

  <div class="box">
    <span class="label">Home area</span>
    <p class="sub" style="margin:10px 0 0;font-size:13px">
      <?= !empty($area['has_home'])
        ? rrp_h((string) ($area['label'] ?: 'Pinned')) . ' · ' . rrp_h((string) $area['radius_km']) . ' km'
        : 'Not set — needed for Finder / Places.' ?>
    </p>
    <p style="margin-top:10px"><a class="link" href="area.php">Set home area</a></p>
  </div>

  <p class="sub" style="font-size:12px;text-align:center">
    <a class="link" href="forum.php">Boards</a> ·
    <a class="link" href="si-chat.php">SIPO</a> ·
    <a class="link" href="support.php">Support</a> ·
    <a class="link" href="logout.php">Log out</a>
  </p>
  <div class="gap"></div>
</div>
<?php
rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
