<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Plus: price after coupon, care weeks and paw coins, plus tax; card payment with Square; auto-renew; referral free months.
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
$profile = rrp_billing_profile((int) $owner['id']);
$monthly = rrp_billing_quote($owner, 'plus_monthly', ['use_coins' => (bool) $profile['use_coins']]);
$ref = rrp_referral_progress((int) $owner['id']);
$money = static fn(int $c, string $cur) => rrp_money($c, $cur);
$cardsReady = rrp_square_cards_ready();
$sq = rrp_square_cfg();
$expires = !empty($owner['plan_expires_at']) ? (new DateTimeImmutable((string) $owner['plan_expires_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner))->format('j M Y') : null;
$csrf = rrp_csrf_token();

rrp_layout_head('Plus — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'settings']);
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
  <section class="care-card" id="plusPay"
    data-csrf="<?= rrp_h($csrf) ?>" data-app-id="<?= rrp_h($sq['application_id']) ?>" data-location-id="<?= rrp_h($sq['location_id']) ?>"
    data-has-card="<?= $profile['card_id'] !== '' ? '1' : '0' ?>" data-ready="<?= $cardsReady ? '1' : '0' ?>">
    <h3 class="label">Plus · monthly</h3>
    <p class="plus-price"><?= rrp_h($money($monthly['list'], $monthly['currency'])) ?><small class="meta"> / month</small></p>
    <?php if (!$cardsReady): ?>
      <button class="btn outline" type="button" disabled style="margin-top:10px">Payments open soon</button>
    <?php else: ?>
      <div class="pp-row">
        <label class="pp-lab" for="ppCoupon">Coupon code</label>
        <div class="pp-coupon"><input id="ppCoupon" maxlength="40" autocomplete="off" autocapitalize="characters" placeholder="Have a code?" /><button type="button" class="btn outline" id="ppApply">Apply</button></div>
        <p class="meta" id="ppCouponMsg" aria-live="polite"></p>
      </div>

      <label class="pp-check"><input type="checkbox" id="ppCoins" <?= $profile['use_coins'] ? 'checked' : '' ?> />
        <span><b>Use my paw coins for a discount</b><small id="ppCoinsHint" class="meta"></small></span></label>

      <dl class="pp-lines" id="ppLines" aria-live="polite"></dl>

      <?php if ($profile['card_id'] !== ''): ?>
        <p class="pp-card" id="ppSaved">💳 <?= rrp_h(ucwords(strtolower(str_replace('_', ' ', $profile['card_brand'] ?: 'Card')))) ?> ending <?= rrp_h($profile['card_last4']) ?>
          <button type="button" class="link" id="ppNewCard">Use another card</button></p>
      <?php endif; ?>
      <div id="ppCardWrap" <?= $profile['card_id'] !== '' ? 'hidden' : '' ?>>
        <p class="pp-lab">Card</p>
        <div id="sqCard" class="pp-sq"></div>
      </div>

      <label class="pp-check"><input type="checkbox" id="ppAuto" <?= ($profile['card_id'] === '' || $profile['auto_renew']) ? 'checked' : '' ?> />
        <span><b>Renew automatically every month</b><small class="meta">You can switch this off at any time. We remind you 3 days before.</small></span></label>

      <button class="btn primary" type="button" id="ppPay" style="margin-top:12px" disabled>Loading…</button>
      <p class="meta" id="ppMsg" role="status" aria-live="polite"></p>
      <p class="meta">Secure payment by Square. Your card number is typed into Square's own form and never reaches SIPAW.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($profile['card_id'] !== ''): ?>
  <section class="care-card" id="plusManage" data-csrf="<?= rrp_h($csrf) ?>">
    <h3 class="label">Your subscription</h3>
    <p class="sub">💳 <?= rrp_h(ucwords(strtolower(str_replace('_', ' ', $profile['card_brand'] ?: 'Card')))) ?> ending <?= rrp_h($profile['card_last4']) ?></p>
    <label class="pp-check"><input type="checkbox" data-manage="auto_renew" <?= $profile['auto_renew'] ? 'checked' : '' ?> />
      <span><b>Renew automatically every month</b><small class="meta"><?= $profile['auto_renew'] ? 'On: the card is charged shortly before Plus ends.' : 'Off: Plus ends on the date above unless you pay again.' ?></small></span></label>
    <label class="pp-check"><input type="checkbox" data-manage="use_coins" <?= $profile['use_coins'] ? 'checked' : '' ?> />
      <span><b>Use my paw coins in the subscription</b><small class="meta">Coins give a discount and are spent when used, so the next discount starts from what you have then.</small></span></label>
    <button type="button" class="btn outline" data-manage="remove_card" style="margin-top:8px">Remove saved card</button>
    <p class="meta" id="pmMsg" role="status" aria-live="polite"></p>
  </section>
  <?php endif; ?>

  <section class="care-card">
    <h3 class="label">Invite friends</h3>
    <p class="sub">3 friends join Plus = 1 free month for you. <a class="link" href="rewards.php">Get your link</a></p>
  </section>
</div>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active'],
    'scripts' => array_merge($cardsReady ? ['https://web.squarecdn.com/v1/square.js'] : [], ['assets/js/plus-checkout.js'])]);
