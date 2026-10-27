<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Plus: price after care rewards, Square checkout, referral free months.
$owner = rrp_require_owner();
$nav = rrp_owner_nav_context((int) $owner['id']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'checkout') {
        $r = rrp_billing_checkout($owner, (string) ($_POST['plan'] ?? 'plus_monthly'));
        if (!empty($r['ok'])) {
            header('Location: ' . $r['url'], true, 303);
            exit;
        }
        $error = $r['error'] ?? 'Checkout failed.';
    } elseif ($act === 'free_month') {
        $r = rrp_billing_use_free_month($owner);
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Free month added 🎁 Enjoy Plus!' : ($r['error'] ?? 'Could not use it.'));
        rrp_redirect('plus.php');
    }
}
if (!empty($_GET['paid'])) {
    $st = rrp_db()->prepare('SELECT status FROM billing_orders WHERE id = ? AND owner_id = ?');
    $st->execute([(int) $_GET['paid'], (int) $owner['id']]);
    $paidStatus = $st->fetchColumn();
}
$owner = rrp_current_owner() ?? $owner;
$plan = rrp_owner_plan($owner);
$prices = rrp_billing_prices();
$monthly = rrp_billing_quote($owner, 'plus_monthly');
$yearly = rrp_billing_quote($owner, 'plus_yearly');
$ref = rrp_referral_progress((int) $owner['id']);
$money = static fn(int $c, string $cur) => ($cur === 'USD' ? '$' : $cur . ' ') . number_format($c / 100, 2);
$expires = !empty($owner['plan_expires_at']) ? (new DateTimeImmutable((string) $owner['plan_expires_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner))->format('j M Y') : null;
$csrf = rrp_csrf_token();

rrp_layout_head('Plus — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'home']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Plus</h2></div>
  <?php rrp_coin_badge($owner); ?>
</div>
<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
  <?php if (isset($paidStatus)): ?>
    <div class="flash <?= $paidStatus === 'paid' ? 'flash-ok' : '' ?>" role="status">
      <?= $paidStatus === 'paid' ? 'Payment received. Welcome to Plus! 🎉' : 'Thanks! Square is confirming your payment; Plus turns on in a minute or two.' ?></div>
  <?php endif; ?>

  <section class="care-status <?= $plan === 'plus' ? 'is-good' : '' ?>">
    <div>
      <h2><?= $plan === 'plus' ? 'You are on Plus' : 'Free plan' ?></h2>
      <p class="sub"><?= $plan === 'plus' ? ($expires ? 'Plus until ' . rrp_h($expires) . '.' : 'Plus is on for this account.') : 'Everything SIPO does is open during the test period. Plus keeps it on and adds up to 3 pets.' ?></p>
    </div>
  </section>

  <?php if ($ref['free_months'] > 0): ?>
    <form method="post" class="care-card">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="free_month" />
      <h3 class="label">🎁 Free months from friends</h3>
      <p class="sub">You have <?= (int) $ref['free_months'] ?>. Each adds one month of Plus, no payment.</p>
      <button class="btn primary" type="submit" style="margin-top:10px">Use 1 free month</button>
    </form>
  <?php endif; ?>

  <?php if ($monthly): ?>
  <section class="care-card">
    <h3 class="label">Plus · monthly</h3>
    <p class="plus-price"><?php if ($monthly['discount']): ?><s><?= rrp_h($money($monthly['list'], $monthly['currency'])) ?></s><?php endif; ?><?= rrp_h($money($monthly['amount'], $monthly['currency'])) ?><small class="meta"> / month</small></p>
    <?php if ($monthly['discount_pct']): ?><p class="daily-window">🌟 <?= (int) $monthly['discount_pct'] ?>% off from your perfect care weeks</p>
    <?php else: ?><p class="meta">Perfect care weeks take up to 20% off. <a class="link" href="rewards.php">See this week</a></p><?php endif; ?>
    <?php if (rrp_square_ready()): ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="checkout" /><input type="hidden" name="plan" value="plus_monthly" />
        <button class="btn primary" type="submit" style="margin-top:10px"><?= $plan === 'plus' ? 'Add a month' : 'Get Plus' ?></button></form>
      <p class="meta">Secure payment by Square. You'll come straight back to SIPAW.</p>
    <?php else: ?>
      <button class="btn outline" type="button" disabled style="margin-top:10px">Payments open soon</button>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($yearly && rrp_square_ready()): ?>
  <section class="care-card">
    <h3 class="label">Plus · yearly</h3>
    <p class="plus-price"><?= rrp_h($money($yearly['amount'], $yearly['currency'])) ?><small class="meta"> / year</small></p>
    <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="checkout" /><input type="hidden" name="plan" value="plus_yearly" />
      <button class="btn outline" type="submit" style="margin-top:10px">Get a year</button></form>
  </section>
  <?php endif; ?>

  <section class="care-card">
    <h3 class="label">Invite friends</h3>
    <p class="sub">3 friends join Plus = 1 free month for you. <a class="link" href="rewards.php">Get your link</a></p>
  </section>
</div>
<?php
rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
