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
            rrp_flash_set('ok', 'Profile saved. Address will be used for nearby LOST HELP ME alerts.');
            rrp_redirect('account.php');
        }
        $error = $result['error'] ?? 'Could not save.';
    }
    $owner = rrp_require_owner();
}

// Refresh geo fields
$stmt = rrp_db()->prepare(
    'SELECT id, name, email, phone, address, lat, lng, city, country, geocoded_at, subscription_status, wallet_balance
     FROM owners WHERE id = ? LIMIT 1'
);
$stmt->execute([(int) $owner['id']]);
$profile = $stmt->fetch() ?: $owner;

rrp_layout_head('Account — Ring Ring Paw');
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="dashboard.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="dashboard.php">Dashboard</a>
  </div>

  <h1>Your account</h1>
  <p class="lede">Keep your address accurate — LOST HELP ME alerts nearby owners within about 25 km of this address.</p>
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="panel" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <label for="name">Your name</label>
    <input id="name" name="name" required value="<?= rrp_h($profile['name'] ?? '') ?>" />

    <label for="phone">Phone</label>
    <input id="phone" name="phone" type="tel" required value="<?= rrp_h($profile['phone'] ?? '') ?>" />

    <label for="address">Registered address</label>
    <textarea id="address" name="address" required rows="3"><?= rrp_h($profile['address'] ?? '') ?></textarea>
    <p class="field-hint">Geocoded with OpenStreetMap Nominatim (cached). Not shown on the public scan page.</p>

    <label for="email">Email</label>
    <input id="email" value="<?= rrp_h($profile['email'] ?? '') ?>" disabled />
    <p class="field-hint">Email cannot be changed here yet.</p>

    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block">Save</button>
    </div>
  </form>

  <section class="panel panel-soft" aria-labelledby="geoHeading">
    <h2 id="geoHeading">Map location</h2>
    <?php if (!empty($profile['lat']) && !empty($profile['lng'])): ?>
      <p class="meta" style="margin:0">
        <?= rrp_h((string) ($profile['city'] ?? '—')) ?><?= !empty($profile['country']) ? ', ' . rrp_h((string) $profile['country']) : '' ?>
        · <?= rrp_h((string) $profile['lat']) ?>, <?= rrp_h((string) $profile['lng']) ?>
      </p>
      <p class="field-hint">Last geocoded: <?= rrp_h((string) ($profile['geocoded_at'] ?? '—')) ?> UTC</p>
    <?php else: ?>
      <p class="empty">Not mapped yet. Save your address, or wait a moment and save again.</p>
    <?php endif; ?>
  </section>

  <section class="panel panel-soft" aria-labelledby="billHeading">
    <h2 id="billHeading">Subscription (coming soon)</h2>
    <p class="meta" style="margin:0">
      Status: <strong><?= rrp_h((string) ($profile['subscription_status'] ?? 'none')) ?></strong>
      · Wallet: $<?= rrp_h(number_format((float) ($profile['wallet_balance'] ?? 0), 2)) ?>
    </p>
    <p class="field-hint">Billing at $5/pet/month will land in a later phase. Stub fields are ready.</p>
  </section>
</div>
<?php rrp_layout_foot(); ?>
