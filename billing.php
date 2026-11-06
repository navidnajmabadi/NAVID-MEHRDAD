<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Billing: pay for Plus (coupon, care weeks, paw coins, tax), the saved card and auto-renew, payment history and invoices (PDF).
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
        rrp_redirect('billing.php');
    }
}
if (!empty($_GET['paid'])) {
    $st = rrp_db()->prepare('SELECT status FROM billing_orders WHERE id = ? AND owner_id = ?');
    $st->execute([(int) $_GET['paid'], (int) $owner['id']]);
    $paidStatus = $st->fetchColumn();
}
$owner = rrp_current_owner() ?? $owner;
$plan = rrp_owner_plan($owner);
$acc = rrp_access($owner); // admin | plus | trial | expired | full
$isAdmin = $acc['state'] === 'admin';
$tz0 = rrp_sipo_owner_tz($owner);
$localDate = static fn(?string $utc) => $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz0)->format('j M Y') : null;
$profile = rrp_billing_profile((int) $owner['id']);
$monthly = rrp_billing_quote($owner, 'plus_monthly', ['use_coins' => (bool) $profile['use_coins']]);
$ref = rrp_referral_progress((int) $owner['id']);
$money = static fn(int $c, string $cur) => rrp_money($c, $cur);
$cardsReady = rrp_square_cards_ready();
$history = [];
try {
    $st = rrp_db()->prepare("SELECT * FROM billing_orders WHERE owner_id = ? AND status IN ('paid','failed') AND provider <> 'free_month' ORDER BY id DESC LIMIT 36");
    $st->execute([(int) $owner['id']]);
    $history = $st->fetchAll();
} catch (Throwable $e) {
}
$ownerTz = rrp_sipo_owner_tz($owner);
$sq = rrp_square_cfg();
$expires = !empty($owner['plan_expires_at']) ? (new DateTimeImmutable((string) $owner['plan_expires_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner))->format('j M Y') : null;
$csrf = rrp_csrf_token();

rrp_layout_head('Billing — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'billing']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Billing</h2></div>
  <?php rrp_coin_badge($owner); ?>
</div>
<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
  <?php if (isset($paidStatus)): ?>
    <div class="flash <?= $paidStatus === 'paid' ? 'flash-ok' : '' ?>" role="status">
      <?= $paidStatus === 'paid' ? 'Payment received. Welcome to Plus! 🎉' : 'Thanks! Square is confirming your payment; Plus turns on in a minute or two.' ?></div>
  <?php endif; ?>

  <?php $state = $acc['state'];
  $trialEnd = $localDate($acc['trial_ends_at']); $plusUntil = $localDate($acc['plus_until']) ?: $expires; ?>
  <section class="care-status <?= in_array($state, ['plus', 'admin'], true) ? 'is-good' : '' ?>">
    <div>
      <?php if ($state === 'admin'): ?>
        <h2>Admin account</h2>
        <p class="sub">Admin accounts have everything on and never subscribe or pay. Nothing to do here.</p>
      <?php elseif ($state === 'plus'): ?>
        <h2>You are on Plus</h2>
        <p class="sub"><?= $acc['canceled'] ? 'Canceled: Plus keeps working until ' . rrp_h((string) $plusUntil) . ', then stops. No more charges.' : ($plusUntil ? 'Plus until ' . rrp_h((string) $plusUntil) . '.' . ($profile['auto_renew'] ? ' It renews automatically.' : ' Auto-renew is off.') : 'Plus is on for this account.') ?></p>
      <?php elseif ($state === 'trial'): ?>
        <h2>Free trial: <?= (int) $acc['days_left'] ?> <?= $acc['days_left'] === 1 ? 'day' : 'days' ?> left</h2>
        <p class="sub">Everything is open until <?= rrp_h((string) $trialEnd) ?>. After that, Health stays visible to read and SIPO needs Plus. Subscribe any time.</p>
      <?php elseif ($state === 'expired'): ?>
        <h2>Your free trial has ended</h2>
        <p class="sub">Health is still here to read. Subscribe to Plus to talk to SIPO, add records and use everything else.</p>
      <?php else: ?>
        <h2>Everything is open</h2>
        <p class="sub">Trial rules are off at the moment.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($ref['free_months'] > 0 && !$isAdmin): ?>
    <form method="post" class="care-card">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="free_month" />
      <h3 class="label">🎁 Free months from friends</h3>
      <p class="sub">You have <?= (int) $ref['free_months'] ?>. Each adds one month of Plus, no payment.</p>
      <button class="btn primary" type="submit" style="margin-top:10px">Use 1 free month</button>
    </form>
  <?php endif; ?>

  <?php if ($monthly && !$isAdmin): ?>
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
    <?php $paidActive = $state === 'plus'; ?>
    <?php if ($paidActive && $acc['canceled']): ?>
      <div class="sub-ask">
        <b>Canceled</b>
        <p class="meta">Plus works until <?= rrp_h((string) $plusUntil) ?>, then stops. Changed your mind? Turn auto-renew back on and nothing is lost.</p>
        <button type="button" class="btn primary" data-manage="resume">Turn auto-renew back on</button>
      </div>
    <?php elseif ($paidActive && !$profile['auto_renew']): ?>
      <div class="sub-ask">
        <b>Auto-renew is off</b>
        <p class="meta">Plus ends on <?= rrp_h((string) $plusUntil) ?>. Keep it going without thinking about it? You can cancel any time.</p>
        <button type="button" class="btn primary" data-manage="resume">Yes, renew automatically</button>
      </div>
    <?php elseif ($paidActive): ?>
      <div class="sub-ask">
        <b>Renews automatically</b>
        <p class="meta">The card is charged shortly before Plus ends<?= $plusUntil ? ' (' . rrp_h((string) $plusUntil) . ')' : '' ?>. We remind you 3 days before.</p>
      </div>
    <?php else: ?>
      <label class="pp-check"><input type="checkbox" data-manage="auto_renew" <?= $profile['auto_renew'] ? 'checked' : '' ?> />
        <span><b>Renew automatically every month</b><small class="meta">Takes effect with your next payment.</small></span></label>
    <?php endif; ?>
    <label class="pp-check"><input type="checkbox" data-manage="use_coins" <?= $profile['use_coins'] ? 'checked' : '' ?> />
      <span><b>Use my paw coins in the subscription</b><small class="meta">Coins give a discount and are spent when used, so the next discount starts from what you have then.</small></span></label>
    <button type="button" class="btn outline" data-manage="remove_card" style="margin-top:8px">Remove saved card</button>
    <?php if ($paidActive && !$acc['canceled']): ?>
      <div class="cancel-box">
        <h3 class="label">Cancel subscription</h3>
        <p class="meta">You can cancel at any time. You will not be charged again, and Plus keeps working until <?= rrp_h((string) ($plusUntil ?: 'the end of the period you paid for')) ?>. The current period is not refunded. You can come back whenever you like.</p>
        <button type="button" class="btn outline cancel-open" id="cancelOpen">Cancel subscription</button>
        <form class="cancel-form" id="cancelForm" hidden>
          <label for="cancelWhy">Would you tell us why? (optional)</label>
          <select id="cancelWhy">
            <option value="">Prefer not to say</option>
            <option value="too_expensive">Too expensive</option>
            <option value="not_using">I do not use it enough</option>
            <option value="missing_feature">Something I need is missing</option>
            <option value="temporary">Only needed it for a while</option>
            <option value="other">Other</option>
          </select>
          <button type="submit" class="btn primary" id="cancelGo">Yes, cancel my subscription</button>
          <button type="button" class="btn outline" id="cancelKeep">Keep my subscription</button>
        </form>
      </div>
    <?php endif; ?>
    <p class="meta" id="pmMsg" role="status" aria-live="polite"></p>
  </section>
  <?php endif; ?>

  <section class="care-card" id="billHistory" data-csrf="<?= rrp_h($csrf) ?>" data-email="<?= rrp_h((string) ($owner['email'] ?? '')) ?>">
    <h3 class="label">Payments and invoices</h3>
    <?php if (!$history): ?>
      <p class="sub">No payments yet. Once you pay, each payment appears here with its invoice.</p>
    <?php else: ?>
      <ul class="bh-list" role="list">
        <?php foreach ($history as $h):
            $paid = $h['status'] === 'paid';
            $when = (new DateTimeImmutable((string) ($h['paid_at'] ?: $h['created_at']), new DateTimeZone('UTC')))->setTimezone($ownerTz)->format('j M Y');
            $num = $paid ? rrp_invoice_number($h) : ''; ?>
        <li class="bh-row" data-order="<?= (int) $h['id'] ?>">
          <div class="bh-main">
            <b>SIPAW Plus · <?= (int) $h['months'] === 12 ? '12 months' : '1 month' ?><?= !empty($h['auto_renewal']) ? ' <small class="meta">(auto-renewal)</small>' : '' ?></b>
            <small class="meta"><?= rrp_h($when) ?><?= $paid ? ' · ' . rrp_h($num) : '' ?><?= $paid && $h['card_last4'] ? ' · card ' . rrp_h((string) $h['card_last4']) : '' ?></small>
          </div>
          <div class="bh-amt">
            <b class="data"><?= rrp_h($money((int) $h['amount_cents'], (string) $h['currency'])) ?></b>
            <span class="chip-s <?= $paid ? 'bh-ok' : 'bh-bad' ?>"><?= $paid ? 'Paid' : 'Not charged' ?></span>
          </div>
          <?php if ($paid): ?>
          <div class="bh-acts">
            <a class="btn outline" href="invoice.php?id=<?= (int) $h['id'] ?>">Download PDF</a>
            <button type="button" class="btn outline" data-email-invoice="<?= (int) $h['id'] ?>">Email PDF</button>
          </div>
          <form class="bh-mail" data-mail-form="<?= (int) $h['id'] ?>" hidden>
            <label for="bhTo<?= (int) $h['id'] ?>">Send to</label>
            <input id="bhTo<?= (int) $h['id'] ?>" type="email" inputmode="email" autocomplete="email" value="<?= rrp_h((string) ($owner['email'] ?? '')) ?>" />
            <button type="submit" class="btn primary">Send invoice</button>
            <p class="meta" data-mail-msg role="status" aria-live="polite"></p>
          </form>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="meta">Invoices show the tax separately and the discounts you used. A "Not charged" line means the card was declined and nothing was taken.</p>
    <?php endif; ?>
  </section>

  <section class="care-card">
    <h3 class="label">Invite friends</h3>
    <p class="sub">3 friends join Plus = 1 free month for you. <a class="link" href="rewards.php">Get your link</a></p>
  </section>
</div>
<?php
rrp_app_end(['nav' => 'billing', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active'],
    'scripts' => array_merge($cardsReady ? ['https://web.squarecdn.com/v1/square.js'] : [], ['assets/js/plus-checkout.js'])]);
