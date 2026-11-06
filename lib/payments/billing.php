<?php
declare(strict_types=1);

/**
 * Plus billing with Square (live mode only).
 *
 * Card payment: the card is typed into Square's own form (Web Payments SDK), so the number never reaches
 * this server; we get a one-time token, save the card on file for the owner and charge it. With auto-renew on,
 * cron charges the saved card each month, with the price recomputed at that moment (admin price, coupon,
 * perfect weeks, paw coins). Older Payment Links and the signed webhook still work.
 *
 * config/sipaw.php (secrets live ONLY here, never in git or chat):
 *   'square' => ['application_id' => 'sq0idp-...', 'access_token' => 'EAAA...', 'location_id' => 'L...',
 *                'webhook_signature_key' => '' (optional), 'webhook_url' => 'https://sipaw.nakilab.com/api/square/webhook.php'],
 * Price, currency and discount rules are set in Admin → Pricing (config 'billing' is only the fallback).
 */

const RRP_SQUARE_VERSION = '2025-10-16';

function rrp_square_cfg(): array
{
    $c = rrp_config_ready() ? (rrp_load_config()['square'] ?? []) : [];
    return [
        'environment' => 'production',
        'application_id' => trim((string) ($c['application_id'] ?? '')),
        'access_token' => trim((string) ($c['access_token'] ?? '')),
        'location_id' => trim((string) ($c['location_id'] ?? '')),
        'webhook_signature_key' => trim((string) ($c['webhook_signature_key'] ?? '')),
        'webhook_url' => trim((string) ($c['webhook_url'] ?? '')) ?: rrp_app_url('api/square/webhook.php'),
    ];
}

/** Card form needs the public application id too; the server charge needs token + location. */
function rrp_square_cards_ready(): bool
{
    return rrp_square_ready() && rrp_square_cfg()['application_id'] !== '';
}

function rrp_square_ready(): bool
{
    $c = rrp_square_cfg();
    return $c['access_token'] !== '' && $c['location_id'] !== '';
}

function rrp_square_base(): string
{
    $override = rrp_config_ready() ? trim((string) (rrp_load_config()['square']['base_url'] ?? '')) : '';
    if ($override !== '') {
        return rtrim($override, '/'); // tests only
    }
    return 'https://connect.squareup.com';
}

// ---------------------------------------------------------------------------
// Admin-set settings (Admin → Pricing)
// ---------------------------------------------------------------------------

function rrp_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (rrp_db()->query('SELECT k, v FROM app_settings')->fetchAll() as $r) {
                $cache[(string) $r['k']] = (string) $r['v'];
            }
        } catch (Throwable $e) {
            // table not there yet (migration 027)
        }
    }
    return $cache[$key] ?? $default;
}

function rrp_setting_set(string $key, string $value): void
{
    rrp_db()->prepare('INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$key, $value]);
}

/** @return array{monthly:int, yearly:?int, currency:string} cents */
function rrp_billing_prices(): array
{
    $b = rrp_config_ready() ? (rrp_load_config()['billing'] ?? []) : [];
    $monthly = (int) rrp_setting('price_monthly_cents', (string) ($b['monthly_cents'] ?? 1999));
    $cur = strtoupper((string) rrp_setting('currency', (string) ($b['currency'] ?? 'CAD')));
    return [
        'monthly' => $monthly > 0 ? $monthly : 1999,
        'yearly' => isset($b['yearly_cents']) && $b['yearly_cents'] ? (int) $b['yearly_cents'] : null,
        'currency' => preg_match('/^[A-Z]{3}$/', $cur) ? $cur : 'CAD',
    ];
}

/**
 * Discount rules set by the admin.
 *   coin_value_cents: what ONE paw coin is worth (default 1 cent: 100 coins = 1.00)
 *   max_total_pct: the most the coins plus perfect weeks may take off one payment (default 50)
 * @return array{coin_value_cents:int, max_total_pct:int}
 */
function rrp_billing_policy(): array
{
    return [
        'coin_value_cents' => max(0, (int) rrp_setting('coin_value_cents', '1')),
        'max_total_pct' => min(100, max(0, (int) rrp_setting('max_total_pct', '50'))),
    ];
}

/**
 * Sales tax added on top of the price (after every discount). Default: none (0%): the business is under the small-supplier limit (CAD 30,000), so no HST is charged.
 * If that ever changes, Admin → Pricing sets the rate (for example 13), the name on the receipt and the countries it applies to (ISO codes, comma separated).
 * @return array{pct:float, label:string, countries:list<string>}
 */
function rrp_billing_tax_rule(): array
{
    $countries = array_values(array_filter(array_map(static fn($c) => strtoupper(trim($c)), explode(',', (string) rrp_setting('tax_countries', 'CA')))));
    return [
        'pct' => min(40.0, max(0.0, (float) rrp_setting('tax_pct', '0'))),
        'label' => mb_substr(trim((string) rrp_setting('tax_label', 'HST')) ?: 'Tax', 0, 20),
        'countries' => $countries,
    ];
}

/** Tax percentage for this owner (0 when their country is not on the admin's list). */
function rrp_billing_tax_pct_for(array $owner): float
{
    $rule = rrp_billing_tax_rule();
    $code = function_exists('rrp_onb_country_code') ? rrp_onb_country_code((string) ($owner['country'] ?? '')) : '';
    if (in_array('*', $rule['countries'], true)) {
        return $rule['pct'];
    }
    return $code !== '' && in_array($code, $rule['countries'], true) ? $rule['pct'] : 0.0;
}

/**
 * Active coupon by code, or null. @return array{id:int, code:string, type:string, value:float}|null
 */
function rrp_coupon_lookup(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
        return null;
    }
    try {
        $st = rrp_db()->prepare(
            "SELECT * FROM coupons WHERE code = ? AND status = 'active'
             AND (max_redemptions IS NULL OR redemption_count < max_redemptions)
             AND (valid_from IS NULL OR valid_from <= UTC_TIMESTAMP()) AND (valid_until IS NULL OR valid_until >= UTC_TIMESTAMP()) LIMIT 1"
        );
        $st->execute([$code]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }
    return $r ? ['id' => (int) $r['id'], 'code' => (string) $r['code'], 'type' => (string) $r['discount_type'], 'value' => (float) $r['discount_value']] : null;
}

/**
 * Price for a plan right now, with every discount shown on its own line.
 * Order: list price, coupon, perfect weeks, then paw coins (only up to the admin's max total %).
 *
 * @param array{coupon?:string, use_coins?:bool} $opt
 * @return array{plan:string, months:int, list:int, currency:string, weeks:list<string>, week_pct:int, week_discount:int,
 *   coupon:?array, coupon_discount:int, coins_balance:int, coins_used:int, coin_discount:int, coin_value_cents:int,
 *   discount:int, discount_pct:float, subtotal:int, tax_pct:float, tax_label:string, tax:int, amount:int, free:bool}|null
 * amount is what is charged: subtotal (price after discounts) plus tax.
 */
function rrp_billing_quote(array $owner, string $plan, array $opt = []): ?array
{
    $p = rrp_billing_prices();
    $pol = rrp_billing_policy();
    $months = $plan === 'plus_yearly' ? 12 : 1;
    $list = $plan === 'plus_yearly' ? $p['yearly'] : $p['monthly'];
    if (!$list) {
        return null;
    }
    $weeks = rrp_week_unspent($owner);
    $weekPct = min(RRP_WEEK_DISCOUNT_MAX, RRP_WEEK_DISCOUNT_PCT * count($weeks));
    $weekDisc = (int) round($p['monthly'] * $weekPct / 100); // one month's price, also for yearly

    $coupon = !empty($opt['coupon']) ? rrp_coupon_lookup((string) $opt['coupon']) : null;
    $couponDisc = 0;
    $free = false;
    if ($coupon) {
        if ($coupon['type'] === 'free_month') {
            $couponDisc = $list;
            $free = true;
        } elseif ($coupon['type'] === 'percent') {
            $couponDisc = (int) round($list * min(100, $coupon['value']) / 100);
        } else {
            $couponDisc = (int) round($coupon['value'] * 100);
        }
        $couponDisc = min($couponDisc, $list);
    }
    $weekDisc = min($weekDisc, $list - $couponDisc);

    $balance = rrp_coin_balance((int) $owner['id']);
    $coinsUsed = 0;
    $coinDisc = 0;
    if (!$free && !empty($opt['use_coins']) && $pol['coin_value_cents'] > 0 && $balance > 0) {
        $cap = (int) floor($list * $pol['max_total_pct'] / 100);
        $room = max(0, min($list - $couponDisc - $weekDisc, $cap - $couponDisc - $weekDisc));
        $coinsUsed = min($balance, intdiv($room, $pol['coin_value_cents']));
        $coinDisc = $coinsUsed * $pol['coin_value_cents'];
    }
    $discount = $couponDisc + $weekDisc + $coinDisc;
    $subtotal = max(0, $list - $discount);
    $taxPct = $subtotal > 0 ? rrp_billing_tax_pct_for($owner) : 0.0;
    $tax = (int) round($subtotal * $taxPct / 100);
    return [
        'plan' => $months === 12 ? 'plus_yearly' : 'plus_monthly',
        'months' => $months,
        'list' => $list,
        'currency' => $p['currency'],
        'weeks' => $weeks,
        'week_pct' => $weekPct,
        'week_discount' => $weekDisc,
        'coupon' => $coupon,
        'coupon_discount' => $couponDisc,
        'coins_balance' => $balance,
        'coins_used' => $coinsUsed,
        'coin_discount' => $coinDisc,
        'coin_value_cents' => $pol['coin_value_cents'],
        'discount' => $discount,
        'discount_pct' => $list > 0 ? round($discount * 100 / $list, 1) : 0.0,
        'subtotal' => $subtotal,
        'tax_pct' => $taxPct,
        'tax_label' => rrp_billing_tax_rule()['label'],
        'tax' => $tax,
        'amount' => $subtotal + $tax,
        'free' => $free,
    ];
}

/** @return array{ok:bool, url?:string, error?:string, order_id?:int} */
function rrp_billing_checkout(array $owner, string $plan): array
{
    if (!rrp_square_ready()) {
        return ['ok' => false, 'error' => 'Payments open soon.'];
    }
    $q = rrp_billing_quote($owner, $plan);
    if (!$q || $q['amount'] < 100) {
        return ['ok' => false, 'error' => 'This plan is not available yet.'];
    }
    $pdo = rrp_db();
    $pdo->prepare(
        "INSERT INTO billing_orders (owner_id, plan_code, months, list_cents, discount_cents, amount_cents, currency, discount_json, provider)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'square')"
    )->execute([(int) $owner['id'], $q['plan'], $q['months'], $q['list'], $q['discount'], $q['amount'], $q['currency'],
        json_encode(['perfect_weeks' => $q['weeks'], 'pct' => $q['discount_pct']])]);
    $orderId = (int) $pdo->lastInsertId();
    $c = rrp_square_cfg();
    $name = 'SIPAW Plus · ' . ($q['months'] === 12 ? '12 months' : '1 month') . ($q['discount_pct'] ? " ({$q['discount_pct']}% care reward)" : '');
    $res = rrp_square_request('POST', '/v2/online-checkout/payment-links', [
        'idempotency_key' => 'sipaw-order-' . $orderId,
        'order' => [
            'location_id' => $c['location_id'],
            'reference_id' => 'SIPAW-' . $orderId,
            'line_items' => [[
                'name' => $name,
                'quantity' => '1',
                'base_price_money' => ['amount' => $q['amount'], 'currency' => $q['currency']],
            ]],
        ],
        'checkout_options' => ['redirect_url' => rrp_app_url('billing.php?paid=' . $orderId)],
        'pre_populated_data' => ['buyer_email' => (string) ($owner['email'] ?? '')],
    ]);
    $link = $res['data']['payment_link'] ?? null;
    if (empty($res['ok']) || !$link) {
        $pdo->prepare("UPDATE billing_orders SET status = 'failed' WHERE id = ?")->execute([$orderId]);
        error_log('Square payment link failed: ' . ($res['error'] ?? '?'));
        return ['ok' => false, 'error' => 'Checkout is not available right now. Please try again later.'];
    }
    $pdo->prepare('UPDATE billing_orders SET provider_link_id = ?, provider_order_id = ?, checkout_url = ? WHERE id = ?')
        ->execute([(string) $link['id'], (string) ($link['order_id'] ?? ''), (string) $link['url'], $orderId]);
    return ['ok' => true, 'url' => (string) $link['url'], 'order_id' => $orderId];
}

/** @return array{ok:bool, status:int, data?:array, error?:string} */
function rrp_square_request(string $method, string $path, ?array $body = null): array
{
    $c = rrp_square_cfg();
    $ch = curl_init(rrp_square_base() . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $c['access_token'],
            'Square-Version: ' . RRP_SQUARE_VERSION,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || $status >= 400) {
        $err = is_array($data) ? (string) ($data['errors'][0]['detail'] ?? $data['errors'][0]['code'] ?? 'HTTP ' . $status) : 'network';
        return ['ok' => false, 'status' => $status, 'error' => $err];
    }
    return ['ok' => true, 'status' => $status, 'data' => $data];
}

/** Square signs notifications with HMAC-SHA256 over (notification URL + raw body), base64. */
function rrp_square_verify(string $rawBody, string $signature): bool
{
    $c = rrp_square_cfg();
    if ($c['webhook_signature_key'] === '' || $signature === '') {
        return false;
    }
    $expected = base64_encode(hash_hmac('sha256', $c['webhook_url'] . $rawBody, $c['webhook_signature_key'], true));
    return hash_equals($expected, $signature);
}

/**
 * Mark an order paid (idempotent) and extend Plus.
 * @return array{ok:bool, already?:bool, expires?:string}
 */
function rrp_billing_mark_paid(int $orderId, ?string $paymentId = null): array
{
    $pdo = rrp_db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM billing_orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o) {
            $pdo->rollBack();
            return ['ok' => false];
        }
        if ($o['status'] === 'paid') {
            $pdo->commit();
            return ['ok' => true, 'already' => true];
        }
        $pdo->prepare("UPDATE billing_orders SET status = 'paid', paid_at = UTC_TIMESTAMP(), provider_payment_id = COALESCE(?, provider_payment_id) WHERE id = ?")
            ->execute([$paymentId, $orderId]);
        $ownerId = (int) $o['owner_id'];
        $cur = $pdo->query('SELECT plan_expires_at FROM owners WHERE id = ' . $ownerId)->fetchColumn();
        $base = $cur && strtotime((string) $cur . ' UTC') > time() ? (string) $cur : gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', strtotime($base . ' UTC +' . (int) $o['months'] . ' months'));
        $pdo->prepare("UPDATE owners SET plan = 'plus', plan_expires_at = ?, subscription_status = 'active' WHERE id = ?")->execute([$expires, $ownerId]);
        $pdo->prepare(
            "INSERT INTO subscriptions (owner_id, provider, provider_subscription_id, plan_code, status, price_cents, currency, current_period_start, current_period_end)
             VALUES (?, ?, ?, ?, 'active', ?, ?, UTC_TIMESTAMP(), ?)"
        )->execute([$ownerId, $o['provider'] === 'free_month' ? 'free_month' : 'square', 'order-' . $orderId, $o['plan_code'], (int) $o['amount_cents'], $o['currency'], $expires]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('rrp_billing_mark_paid: ' . $e->getMessage());
        return ['ok' => false];
    }
    $d = json_decode((string) ($o['discount_json'] ?? ''), true) ?: [];
    rrp_week_spend($ownerId, (array) ($d['perfect_weeks'] ?? []), $orderId);
    // Coins used for the discount are taken from the balance now, so they cannot discount the next payment too.
    if ((int) ($o['coins_used'] ?? 0) > 0) {
        rrp_coin_spend($ownerId, (int) $o['coins_used'], 'Plus discount', 'order', $orderId);
    }
    if (!empty($o['coupon_code'])) {
        try {
            rrp_db()->prepare("UPDATE coupons SET redemption_count = redemption_count + 1,
                status = IF(max_redemptions IS NOT NULL AND redemption_count + 1 >= max_redemptions, 'exhausted', status) WHERE code = ?")->execute([$o['coupon_code']]);
        } catch (Throwable $e) {
            error_log('coupon redeem: ' . $e->getMessage());
        }
    }
    if ($o['provider'] === 'square' && (int) $o['amount_cents'] > 0) {
        rrp_referral_on_paid($ownerId);
    }
    return ['ok' => true, 'expires' => $expires];
}

/** Use one referral free month (no payment). */
function rrp_billing_use_free_month(array $owner): array
{
    $st = rrp_db()->prepare('UPDATE owners SET referral_free_months_owed = referral_free_months_owed - 1 WHERE id = ? AND referral_free_months_owed > 0');
    $st->execute([(int) $owner['id']]);
    if ($st->rowCount() === 0) {
        return ['ok' => false, 'error' => 'No free months yet.'];
    }
    rrp_db()->prepare(
        "INSERT INTO billing_orders (owner_id, plan_code, months, list_cents, discount_cents, amount_cents, currency, provider)
         VALUES (?, 'free_month', 1, 0, 0, 0, ?, 'free_month')"
    )->execute([(int) $owner['id'], rrp_billing_prices()['currency']]);
    return rrp_billing_mark_paid((int) rrp_db()->lastInsertId());
}

/** Square webhook body → order paid. @return array{ok:bool, handled:bool, note?:string} */
function rrp_square_handle_event(array $event): array
{
    $id = (string) ($event['event_id'] ?? '');
    $type = (string) ($event['type'] ?? '');
    if ($id === '') {
        return ['ok' => false, 'handled' => false, 'note' => 'no event_id'];
    }
    try {
        rrp_db()->prepare('INSERT INTO billing_events (event_id, event_type, payload) VALUES (?, ?, ?)')
            ->execute([$id, $type, json_encode($event)]);
    } catch (Throwable $e) {
        return ['ok' => true, 'handled' => false, 'note' => 'duplicate'];
    }
    $payment = $event['data']['object']['payment'] ?? null;
    if (!in_array($type, ['payment.created', 'payment.updated'], true) || !is_array($payment) || ($payment['status'] ?? '') !== 'COMPLETED') {
        return ['ok' => true, 'handled' => false];
    }
    $st = rrp_db()->prepare('SELECT id FROM billing_orders WHERE provider_order_id = ? LIMIT 1');
    $st->execute([(string) ($payment['order_id'] ?? '')]);
    $orderId = (int) $st->fetchColumn();
    if (!$orderId) {
        return ['ok' => true, 'handled' => false, 'note' => 'unknown order'];
    }
    $r = rrp_billing_mark_paid($orderId, (string) ($payment['id'] ?? ''));
    return ['ok' => !empty($r['ok']), 'handled' => true];
}

/** Cron: remind 3 days before Plus ends (once per period). */
function rrp_billing_renewal_tick(): array
{
    $stats = ['reminded' => 0];
    try {
        $rows = rrp_db()->query(
            "SELECT * FROM owners WHERE status = 'active' AND plan = 'plus' AND plan_expires_at IS NOT NULL
             AND plan_expires_at BETWEEN UTC_TIMESTAMP() AND (UTC_TIMESTAMP() + INTERVAL 3 DAY)"
        )->fetchAll();
    } catch (Throwable $e) {
        return $stats;
    }
    foreach ($rows as $o) {
        $seen = rrp_db()->prepare("SELECT 1 FROM notifications WHERE owner_id = ? AND type = 'billing' AND created_at > (UTC_TIMESTAMP() - INTERVAL 4 DAY) LIMIT 1");
        $seen->execute([(int) $o['id']]);
        if ($seen->fetchColumn()) {
            continue;
        }
        $auto = rrp_billing_profile((int) $o['id']);
        if (!empty($auto['auto_renew']) && !empty($auto['card_last4'])) {
            $q = rrp_billing_quote($o, 'plus_monthly', ['use_coins' => !empty($auto['use_coins'])]);
            $when = (new DateTimeImmutable((string) $o['plan_expires_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($o))->format('j M');
            $text = 'Plus renews on ' . $when . ' with your card ending ' . $auto['card_last4'] . ($q ? ' for ' . rrp_money($q['amount'], $q['currency'])
                . ($q['coins_used'] ? ' (' . $q['coins_used'] . ' coins applied)' : '') : '') . '. You can change this on the Plus page.';
            rrp_notify_owner_inbox((int) $o['id'], 'billing', 'Plus renews soon', $text);
            $stats['reminded']++;
            continue;
        }
        $pct = rrp_week_discount_pct($o);
        $text = 'Your Plus ends on ' . (new DateTimeImmutable((string) $o['plan_expires_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($o))->format('j M')
            . '. Renew on the Plus page' . ($pct ? " — you've earned {$pct}% off with your care weeks 🌟" : '') . '.';
        rrp_notify_owner_inbox((int) $o['id'], 'billing', 'Plus is ending soon', $text);
        rrp_try_web_push((int) $o['id'], 'SIPAW', $text, rrp_app_url('billing.php'));
        $stats['reminded']++;
    }
    return $stats;
}

// ---------------------------------------------------------------------------
// Card on file, pay now, auto-renew
// ---------------------------------------------------------------------------

function rrp_money(int $cents, string $currency): string
{
    return $currency . ' $' . number_format($cents / 100, 2);
}

/** @return array{customer_id:string, card_id:string, card_brand:string, card_last4:string, auto_renew:int, use_coins:int, renew_fail_count:int} */
function rrp_billing_profile(int $ownerId): array
{
    $blank = ['customer_id' => '', 'card_id' => '', 'card_brand' => '', 'card_last4' => '', 'auto_renew' => 0, 'use_coins' => 1, 'renew_fail_count' => 0];
    try {
        $st = rrp_db()->prepare('SELECT square_customer_id, square_card_id, card_brand, card_last4, auto_renew, use_coins, renew_fail_count FROM owners WHERE id = ?');
        $st->execute([$ownerId]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return $blank; // migration 027 not run yet
    }
    if (!$r) {
        return $blank;
    }
    return [
        'customer_id' => (string) $r['square_customer_id'], 'card_id' => (string) $r['square_card_id'],
        'card_brand' => (string) $r['card_brand'], 'card_last4' => (string) $r['card_last4'],
        'auto_renew' => (int) $r['auto_renew'], 'use_coins' => (int) $r['use_coins'], 'renew_fail_count' => (int) $r['renew_fail_count'],
    ];
}

function rrp_square_error_text(array $res): string
{
    $err = (string) ($res['error'] ?? '');
    return match (true) {
        stripos($err, 'DECLINED') !== false, stripos($err, 'declined') !== false => 'Your card was declined. Please try another card.',
        stripos($err, 'CVV') !== false, stripos($err, 'POSTAL') !== false, stripos($err, 'EXPIRATION') !== false, stripos($err, 'INVALID_CARD') !== false => 'The card details do not look right. Please check them and try again.',
        stripos($err, 'INSUFFICIENT') !== false => 'The card has insufficient funds.',
        default => 'The payment could not be completed. Please try again, or use another card.',
    };
}

/** Customer + card on file from the one-time token the card form produced. @return array{ok:bool, error?:string} */
function rrp_billing_save_card(array $owner, string $token): array
{
    $ownerId = (int) $owner['id'];
    $profile = rrp_billing_profile($ownerId);
    $customerId = $profile['customer_id'];
    if ($customerId === '') {
        $res = rrp_square_request('POST', '/v2/customers', [
            'idempotency_key' => 'sipaw-cust-' . $ownerId,
            'reference_id' => 'SIPAW-OWNER-' . $ownerId,
            'email_address' => (string) ($owner['email'] ?? ''),
            'nickname' => mb_substr((string) ($owner['nickname'] ?? $owner['name'] ?? ''), 0, 40),
        ]);
        $customerId = (string) ($res['data']['customer']['id'] ?? '');
        if (!$customerId) {
            error_log('Square customer failed: ' . ($res['error'] ?? '?'));
            return ['ok' => false, 'error' => 'Could not set up your payment profile. Please try again.'];
        }
    }
    $res = rrp_square_request('POST', '/v2/cards', [
        'idempotency_key' => 'sipaw-card-' . bin2hex(random_bytes(10)),
        'source_id' => $token,
        'card' => ['customer_id' => $customerId],
    ]);
    $card = $res['data']['card'] ?? null;
    if (empty($res['ok']) || !$card || empty($card['id'])) {
        error_log('Square card failed: ' . ($res['error'] ?? '?'));
        return ['ok' => false, 'error' => rrp_square_error_text($res)];
    }
    rrp_db()->prepare('UPDATE owners SET square_customer_id = ?, square_card_id = ?, card_brand = ?, card_last4 = ?, renew_fail_count = 0 WHERE id = ?')
        ->execute([$customerId, (string) $card['id'], mb_substr((string) ($card['card_brand'] ?? ''), 0, 24), substr((string) ($card['last_4'] ?? ''), 0, 4), $ownerId]);
    return ['ok' => true];
}

/**
 * Charge the saved card for one month of Plus. The amount comes from rrp_billing_quote, never from the browser.
 * @param array{coupon?:string, use_coins?:bool, auto?:bool} $opt
 * @return array{ok:bool, error?:string, order_id?:int, amount?:int, quote?:array}
 */
function rrp_billing_charge_saved_card(array $owner, array $opt = []): array
{
    $ownerId = (int) $owner['id'];
    $profile = rrp_billing_profile($ownerId);
    if ($profile['card_id'] === '' || $profile['customer_id'] === '') {
        return ['ok' => false, 'error' => 'No card is saved yet.'];
    }
    $q = rrp_billing_quote($owner, 'plus_monthly', $opt);
    if (!$q) {
        return ['ok' => false, 'error' => 'This plan is not available yet.'];
    }
    $pdo = rrp_db();
    $pdo->prepare(
        "INSERT INTO billing_orders (owner_id, plan_code, months, list_cents, discount_cents, amount_cents, currency, discount_json, provider, coupon_code, coins_used, coin_discount_cents, auto_renewal, tax_cents, tax_pct, card_brand, card_last4, tax_label)
         VALUES (?, 'plus_monthly', 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$ownerId, $q['list'], $q['discount'], $q['amount'], $q['currency'],
        json_encode(['perfect_weeks' => $q['weeks'], 'week_pct' => $q['week_pct'], 'coins' => $q['coins_used'],
            'coupon_cents' => $q['coupon_discount'], 'week_cents' => $q['week_discount'], 'coin_cents' => $q['coin_discount']]),
        $q['amount'] > 0 ? 'square' : 'manual', $q['coupon']['code'] ?? null, $q['coins_used'], $q['coin_discount'], !empty($opt['auto']) ? 1 : 0,
        $q['tax'], $q['tax_pct'], $profile['card_brand'] ?: null, $profile['card_last4'] ?: null, $q['tax_label']]);
    $orderId = (int) $pdo->lastInsertId();
    if ($q['amount'] === 0) {
        rrp_billing_mark_paid($orderId); // a 100% coupon: nothing to charge
        return ['ok' => true, 'order_id' => $orderId, 'amount' => 0, 'quote' => $q];
    }
    $c = rrp_square_cfg();
    $res = rrp_square_request('POST', '/v2/payments', [
        'idempotency_key' => 'sipaw-ord-' . $orderId,
        'source_id' => $profile['card_id'],
        'customer_id' => $profile['customer_id'],
        'location_id' => $c['location_id'],
        'amount_money' => ['amount' => $q['amount'], 'currency' => $q['currency']],
        'autocomplete' => true,
        'reference_id' => 'SIPAW-' . $orderId,
        'note' => 'SIPAW Plus, 1 month' . ($q['tax'] > 0 ? ' incl. ' . $q['tax_label'] . ' ' . rtrim(rtrim(number_format($q['tax_pct'], 2), '0'), '.') . '%' : '') . (!empty($opt['auto']) ? ' (auto-renewal)' : ''),
        'buyer_email_address' => (string) ($owner['email'] ?? ''),
    ]);
    $pay = $res['data']['payment'] ?? null;
    if (empty($res['ok']) || !$pay || ($pay['status'] ?? '') !== 'COMPLETED') {
        $pdo->prepare("UPDATE billing_orders SET status = 'failed' WHERE id = ?")->execute([$orderId]);
        error_log('Square charge failed (order ' . $orderId . '): ' . ($res['error'] ?? ($pay['status'] ?? '?')));
        return ['ok' => false, 'order_id' => $orderId, 'error' => rrp_square_error_text($res)];
    }
    $pdo->prepare('UPDATE billing_orders SET provider_order_id = ?, provider_payment_id = ? WHERE id = ?')
        ->execute([(string) ($pay['order_id'] ?? ''), (string) ($pay['id'] ?? ''), $orderId]);
    rrp_billing_mark_paid($orderId, (string) ($pay['id'] ?? ''));
    return ['ok' => true, 'order_id' => $orderId, 'amount' => $q['amount'], 'quote' => $q];
}

/**
 * Pay now from the Plus page: save the card from the token, remember the choices, charge.
 * @param array{coupon?:string, use_coins?:bool, auto_renew?:bool} $opt
 */
function rrp_billing_subscribe(array $owner, ?string $token, array $opt): array
{
    if (!rrp_square_cards_ready()) {
        return ['ok' => false, 'error' => 'Payments open soon.'];
    }
    $ownerId = (int) $owner['id'];
    // At most 6 tries an hour per person.
    $st = rrp_db()->prepare("SELECT COUNT(*) FROM billing_orders WHERE owner_id = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR) AND provider = 'square'");
    $st->execute([$ownerId]);
    if ((int) $st->fetchColumn() >= 6) {
        return ['ok' => false, 'error' => 'Too many payment tries. Please wait a little and try again.'];
    }
    if ($token !== null) {
        $saved = rrp_billing_save_card($owner, $token);
        if (empty($saved['ok'])) {
            return $saved;
        }
    } elseif (rrp_billing_profile($ownerId)['card_id'] === '') {
        return ['ok' => false, 'error' => 'No card is saved yet.'];
    }
    $useCoins = !empty($opt['use_coins']);
    $auto = !empty($opt['auto_renew']);
    rrp_db()->prepare('UPDATE owners SET use_coins = ?, auto_renew = ? WHERE id = ?')->execute([$useCoins ? 1 : 0, $auto ? 1 : 0, $ownerId]);
    return rrp_billing_charge_saved_card($owner, ['coupon' => (string) ($opt['coupon'] ?? ''), 'use_coins' => $useCoins, 'auto' => false]);
}

/**
 * Cancel the subscription the standard way: no more charges, Plus keeps working until the end of the period already paid,
 * no refund of the current period, the owner can resume at any time, and a confirmation is sent.
 * @return array{ok:bool, until:?string}
 */
function rrp_billing_cancel(array $owner, string $reason = ''): array
{
    $ownerId = (int) $owner['id'];
    $allowed = ['too_expensive', 'not_using', 'missing_feature', 'temporary', 'other'];
    $reason = in_array($reason, $allowed, true) ? $reason : '';
    rrp_db()->prepare('UPDATE owners SET auto_renew = 0, canceled_at = UTC_TIMESTAMP(), cancel_reason = ? WHERE id = ?')->execute([$reason !== '' ? $reason : null, $ownerId]);
    $a = rrp_access($owner);
    $until = null;
    try {
        $st = rrp_db()->prepare('SELECT plan_expires_at FROM owners WHERE id = ?');
        $st->execute([$ownerId]);
        $until = ($v = $st->fetchColumn()) ? (string) $v : null;
    } catch (Throwable $e) {
    }
    $when = $until ? (new DateTimeImmutable($until, new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner))->format('j M Y') : null;
    $text = 'Your SIPAW subscription is canceled. You will not be charged again.'
        . ($when ? " Plus keeps working until {$when}." : '') . ' You can turn it back on any time in Billing.';
    rrp_notify_owner_inbox($ownerId, 'billing', 'Subscription canceled', $text);
    $email = trim((string) ($owner['email'] ?? ''));
    if ($email !== '') {
        $name = (string) ($owner['nickname'] ?? '') ?: 'there';
        $mail = [
            'subject' => 'Your SIPAW subscription is canceled',
            'text' => "Hi {$name},\n\n{$text}\n\nSIPAW by NAKILAB\n",
            'html' => rrp_email_layout([
                'preheader' => 'No more charges. ' . ($when ? "Plus works until {$when}." : ''),
                'mood' => 'love',
                'headline' => 'Subscription canceled',
                'intro' => rrp_email_h("Hi {$name}, " . $text),
                'button' => ['Open Billing', rrp_email_abs('billing.php')],
            ]),
        ];
        rrp_email_send($email, $mail);
    }
    return ['ok' => true, 'until' => $until];
}

/** Settings on the Plus page after a card is saved. @param 'auto_renew'|'use_coins'|'remove_card' $what */
function rrp_billing_manage(int $ownerId, string $what, bool $on): void
{
    if ($what === 'auto_renew') {
        rrp_db()->prepare('UPDATE owners SET auto_renew = ?, renew_fail_count = 0, canceled_at = IF(? = 1, NULL, canceled_at) WHERE id = ?')->execute([$on ? 1 : 0, $on ? 1 : 0, $ownerId]);
    } elseif ($what === 'use_coins') {
        rrp_db()->prepare('UPDATE owners SET use_coins = ? WHERE id = ?')->execute([$on ? 1 : 0, $ownerId]);
    } elseif ($what === 'remove_card') {
        $p = rrp_billing_profile($ownerId);
        if ($p['card_id'] !== '' && rrp_square_ready()) {
            rrp_square_request('POST', '/v2/cards/' . rawurlencode($p['card_id']) . '/disable');
        }
        rrp_db()->prepare('UPDATE owners SET square_card_id = NULL, card_brand = NULL, card_last4 = NULL, auto_renew = 0 WHERE id = ?')->execute([$ownerId]);
    }
}

/**
 * Cron: charge the saved card of every owner whose Plus ends within 6 hours (auto-renew on).
 * Up to 3 tries (one a day); then auto-renew is switched off and the owner is told. A referral free month is used first.
 * @return array{checked:int, renewed:int, free_month:int, failed:int}
 */
function rrp_billing_auto_tick(int $max = 20): array
{
    $out = ['checked' => 0, 'renewed' => 0, 'free_month' => 0, 'failed' => 0];
    if (!rrp_square_ready()) {
        return $out;
    }
    try {
        $rows = rrp_db()->query(
            "SELECT * FROM owners WHERE status = 'active' AND auto_renew = 1 AND square_card_id IS NOT NULL AND plan = 'plus'
               AND plan_expires_at IS NOT NULL AND plan_expires_at <= (UTC_TIMESTAMP() + INTERVAL 6 HOUR)
               AND plan_expires_at > (UTC_TIMESTAMP() - INTERVAL 4 DAY) AND renew_fail_count < 3
               AND (renew_last_try IS NULL OR renew_last_try < (UTC_TIMESTAMP() - INTERVAL 20 HOUR))
             ORDER BY plan_expires_at ASC LIMIT " . (int) $max
        )->fetchAll();
    } catch (Throwable $e) {
        return $out; // migration 027 not run yet
    }
    foreach ($rows as $o) {
        $ownerId = (int) $o['id'];
        $lock = 'sipo_bill_' . $ownerId;
        if (!rrp_lock_try($lock)) {
            continue;
        }
        try {
            $out['checked']++;
            $own = rrp_db()->query('SELECT referral_free_months_owed FROM owners WHERE id = ' . $ownerId)->fetchColumn();
            if ((int) $own > 0) {
                $r = rrp_billing_use_free_month($o);
                if (!empty($r['ok'])) {
                    $out['free_month']++;
                    rrp_notify_owner_inbox($ownerId, 'billing', 'Plus renewed', 'One of your free months was used, so Plus continues with no payment 🎁');
                    continue;
                }
            }
            rrp_db()->prepare('UPDATE owners SET renew_last_try = UTC_TIMESTAMP() WHERE id = ?')->execute([$ownerId]);
            $profile = rrp_billing_profile($ownerId);
            $r = rrp_billing_charge_saved_card($o, ['use_coins' => !empty($profile['use_coins']), 'auto' => true]);
            if (!empty($r['ok'])) {
                $out['renewed']++;
                rrp_db()->prepare('UPDATE owners SET renew_fail_count = 0 WHERE id = ?')->execute([$ownerId]);
                $q = $r['quote'];
                $text = 'Plus renewed for another month: ' . rrp_money((int) $r['amount'], $q['currency'])
                    . ($q['coins_used'] ? ', with ' . $q['coins_used'] . ' paw coins used (' . rrp_money($q['coin_discount'], $q['currency']) . ' off)' : '') . '. Thank you 💙';
                rrp_notify_owner_inbox($ownerId, 'billing', 'Plus renewed', $text);
                rrp_try_web_push($ownerId, 'SIPAW', $text, rrp_app_url('billing.php'));
            } else {
                $out['failed']++;
                rrp_db()->prepare('UPDATE owners SET renew_fail_count = renew_fail_count + 1 WHERE id = ?')->execute([$ownerId]);
                $fails = (int) rrp_db()->query('SELECT renew_fail_count FROM owners WHERE id = ' . $ownerId)->fetchColumn();
                if ($fails >= 3) {
                    rrp_db()->prepare('UPDATE owners SET auto_renew = 0 WHERE id = ?')->execute([$ownerId]);
                }
                $text = 'We could not renew Plus with your card (' . ($r['error'] ?? 'declined') . ').'
                    . ($fails >= 3 ? ' Auto-renew is now off. Open the Plus page to add a new card.' : ' We will try again tomorrow. You can update your card on the Plus page.');
                rrp_notify_owner_inbox($ownerId, 'billing', 'Plus payment did not go through', $text);
                rrp_try_web_push($ownerId, 'SIPAW', $text, rrp_app_url('billing.php'));
            }
        } finally {
            rrp_lock_release($lock);
        }
    }
    return $out;
}
