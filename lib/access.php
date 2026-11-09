<?php
declare(strict_types=1);

/**
 * Who may use what.
 *
 *  admin   super owners (Navid, Mehrdad): always full access, never asked to subscribe.
 *  plus    a paid Plus period that has not ended (or a tester Plus with no end date): everything.
 *  trial   the first days after sign-up (7 by default; an admin can set it per account): everything.
 *  expired trial over and no Plus: Health stays visible but READ ONLY, plus Billing, Lost pet alerts and basic
 *          account pages. SIPO, Home, Routines and the rest ask the owner to subscribe on the Billing page.
 *  full    rules switched off in Admin → Pricing, or the migration has not run yet: nobody is locked.
 *
 * One switch protects against a bad deploy: Admin → Pricing → "Trial rules on".
 */

function rrp_access_enforced(): bool
{
    return rrp_setting('trial_enforced', '1') !== '0';
}

function rrp_trial_days_default(): int
{
    return max(0, min(365, (int) rrp_setting('trial_days', '7')));
}

/** Give a brand-new account its trial (called right after sign-up). Never throws. */
function rrp_trial_start(int $ownerId): void
{
    try {
        $days = rrp_trial_days_default();
        rrp_db()->prepare('UPDATE owners SET trial_ends_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY) WHERE id = ?')->execute([$days, $ownerId]);
    } catch (Throwable $e) {
        // migration 029 not run yet
    }
}

/**
 * @return array{state:string, trial_ends_at:?string, days_left:?int, plus_until:?string, canceled:bool, readonly:bool}
 */
function rrp_access(array $owner): array
{
    static $cache = [];
    $id = (int) ($owner['id'] ?? 0);
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    $out = ['state' => 'full', 'trial_ends_at' => null, 'days_left' => null, 'plus_until' => null, 'canceled' => false, 'readonly' => false];
    if (rrp_is_super_owner($owner)) {
        $out['state'] = 'admin';
        return $cache[$id] = $out;
    }
    if (!rrp_access_enforced()) {
        return $cache[$id] = $out;
    }
    try {
        $st = rrp_db()->prepare('SELECT plan, plan_expires_at, trial_ends_at, canceled_at, created_at FROM owners WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return $cache[$id] = $out; // migration 029 not run yet: nobody is locked out
    }
    if (!$r) {
        return $cache[$id] = $out;
    }
    return $cache[$id] = rrp_access_compute($owner, $r);
}

/** The state for an owner given the plan/trial columns of its row (shared with the admin list, which loads many at once). */
function rrp_access_compute(array $owner, array $r): array
{
    $out = ['state' => 'full', 'trial_ends_at' => null, 'days_left' => null, 'plus_until' => null, 'canceled' => false, 'readonly' => false];
    if (rrp_is_super_owner($owner)) {
        $out['state'] = 'admin';
        return $out;
    }
    if (!rrp_access_enforced()) {
        return $out;
    }
    $now = time();
    $out['canceled'] = !empty($r['canceled_at']);
    $until = !empty($r['plan_expires_at']) ? strtotime((string) $r['plan_expires_at'] . ' UTC') : null;
    if (strtolower((string) ($r['plan'] ?? '')) === 'plus' && ($until === null || $until > $now)) {
        $out['state'] = 'plus';
        $out['plus_until'] = $until ? gmdate('Y-m-d H:i:s', $until) : null;
        return $out;
    }
    $end = !empty($r['trial_ends_at']) ? strtotime((string) $r['trial_ends_at'] . ' UTC')
        : strtotime((string) ($r['created_at'] ?? 'now') . ' UTC') + rrp_trial_days_default() * 86400; // no value stored: from sign-up
    $out['trial_ends_at'] = gmdate('Y-m-d H:i:s', $end);
    if ($end > $now) {
        $out['state'] = 'trial';
        $out['days_left'] = (int) ceil(($end - $now) / 86400);
    } else {
        $out['state'] = 'expired';
        $out['readonly'] = true;
    }
    return $out;
}

/** Plan and trial columns for many owners (admin list). @return array<int,array<string,mixed>> */
function rrp_access_rows(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    try {
        $in = implode(',', $ids);
        $rows = rrp_db()->query("SELECT id, plan, plan_expires_at, trial_ends_at, canceled_at, created_at, auto_renew, card_last4 FROM owners WHERE id IN ($in)")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = $r;
    }
    return $out;
}

/**
 * Admin sets one account's trial. $mode: 'from_now' (N days from today), 'extend' (add N days to the current end, or to today if it is over), 'end_now'.
 * @return array{ok:bool, error?:string, ends?:string}
 */
function rrp_admin_trial_set(int $adminId, int $ownerId, string $mode, int $days): array
{
    $days = max(0, min(365, $days));
    try {
        $st = rrp_db()->prepare('SELECT trial_ends_at FROM owners WHERE id = ?');
        $st->execute([$ownerId]);
        $row = $st->fetch();
        if ($row === false) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        $curEnd = !empty($row['trial_ends_at']) ? strtotime((string) $row['trial_ends_at'] . ' UTC') : 0;
        $now = time();
        $end = match ($mode) {
            'end_now' => $now - 1,
            'extend' => max($now, $curEnd) + $days * 86400,
            default => $now + $days * 86400,
        };
        $endStr = gmdate('Y-m-d H:i:s', $end);
        rrp_db()->prepare('UPDATE owners SET trial_ends_at = ? WHERE id = ?')->execute([$endStr, $ownerId]);
        rrp_admin_log($adminId, 'trial_set', 'owners', $ownerId, "mode=$mode days=$days ends=$endStr");
        return ['ok' => true, 'ends' => $endStr];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not change the trial (has migration 029 run?).'];
    }
}

/** Admin removes a subscription: Plus ends now, auto-renew off, saved card removed. No refund is made here (do that in Square). */
function rrp_admin_remove_subscription(int $adminId, int $ownerId): array
{
    try {
        $p = rrp_billing_profile($ownerId);
        if ($p['card_id'] !== '' && rrp_square_ready()) {
            rrp_square_request('POST', '/v2/cards/' . rawurlencode($p['card_id']) . '/disable');
        }
        rrp_db()->prepare("UPDATE owners SET plan = 'free', plan_expires_at = NULL, auto_renew = 0, canceled_at = UTC_TIMESTAMP(), cancel_reason = 'admin',
                           square_card_id = NULL, card_brand = NULL, card_last4 = NULL WHERE id = ?")->execute([$ownerId]);
        rrp_admin_log($adminId, 'subscription_removed', 'owners', $ownerId, 'plan=free card removed');
        rrp_notify_owner_inbox($ownerId, 'billing', 'Subscription ended', 'Your SIPAW subscription was ended by the SIPAW team. Open Billing to see your options.');
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not remove the subscription.'];
    }
}

/** True when this owner is past the trial with no Plus. */
function rrp_access_expired(array $owner): bool
{
    return rrp_access($owner)['state'] === 'expired';
}

/** Pages and API folders an expired owner may still open. Health is read-only (care.php checks it). */
function rrp_access_script_allowed(string $script): bool
{
    $script = str_replace('\\', '/', $script);
    if (str_contains($script, '/api/billing/')) {
        return true;
    }
    return in_array(basename($script), [
        'billing.php', 'plus.php', 'invoice.php',      // paying and invoices
        'care.php',                                     // Health, read only
        'lost.php', 'lost-alert.php', 'rescued.php', 'chat.php',  // a lost pet must never be locked behind a payment
        'settings.php', 'account.php', 'account-delete.php', 'logout.php', 'legal.php',  // account basics
        'me.php', 'client-error.php', 'notifications.php', 'lost.php',
        'tour.php',                                     // the tour only shows sample data and changes nothing
    ], true);
}

/** Pages shown as locked in the menu for an expired owner. */
function rrp_access_href_allowed(string $href): bool
{
    return rrp_access_script_allowed('/' . ltrim(strtok($href, '?') ?: '', '/'));
}

/** Stops an expired owner from opening a page or API that needs Plus. */
function rrp_access_gate(array $owner, bool $api): void
{
    if (!rrp_access_expired($owner)) {
        return;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (rrp_access_script_allowed($script) || str_contains($script, '/cron/')) {
        return;
    }
    if ($api) {
        rrp_json(['ok' => false, 'error' => 'Your trial has ended. Subscribe to Plus to keep using this.', 'gate' => 'trial_ended', 'billing' => 'billing.php'], 402);
    }
    rrp_redirect('billing.php?trial=ended');
}
