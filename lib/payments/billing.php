<?php
declare(strict_types=1);

/**
 * Plus billing with Square (Navid 2026-10-28: Square account, keys later).
 * Plus is bought in months through a Square Payment Link; the price on each link already includes
 * the perfect-week discount (up to 20%). Square confirms payment by a signed webhook; then Plus is
 * extended. Referral free months extend Plus with no payment. Without keys: "Payments open soon".
 *
 * config/sipaw.php:
 *   'square' => ['environment' => 'sandbox'|'production', 'access_token' => '', 'location_id' => '',
 *                'webhook_signature_key' => '', 'webhook_url' => 'https://sipaw.nakilab.com/api/square/webhook.php'],
 *   'billing' => ['monthly_cents' => 1999, 'yearly_cents' => null, 'currency' => 'USD'],
 */

const RRP_SQUARE_VERSION = '2025-10-16';

function rrp_square_cfg(): array
{
    $c = rrp_config_ready() ? (rrp_load_config()['square'] ?? []) : [];
    return [
        'environment' => ($c['environment'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
        'access_token' => trim((string) ($c['access_token'] ?? '')),
        'location_id' => trim((string) ($c['location_id'] ?? '')),
        'webhook_signature_key' => trim((string) ($c['webhook_signature_key'] ?? '')),
        'webhook_url' => trim((string) ($c['webhook_url'] ?? '')) ?: rrp_app_url('api/square/webhook.php'),
    ];
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
    return rrp_square_cfg()['environment'] === 'production' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
}

/** @return array{monthly:int, yearly:?int, currency:string} cents */
function rrp_billing_prices(): array
{
    $b = rrp_config_ready() ? (rrp_load_config()['billing'] ?? []) : [];
    return [
        'monthly' => (int) ($b['monthly_cents'] ?? 1999),
        'yearly' => isset($b['yearly_cents']) && $b['yearly_cents'] ? (int) $b['yearly_cents'] : null,
        'currency' => strtoupper((string) ($b['currency'] ?? 'USD')),
    ];
}

/**
 * Price for a plan right now, with this owner's discount.
 * @return array{plan:string, months:int, list:int, discount_pct:int, discount:int, amount:int, currency:string, weeks:list<string>}|null
 */
function rrp_billing_quote(array $owner, string $plan): ?array
{
    $p = rrp_billing_prices();
    $months = $plan === 'plus_yearly' ? 12 : 1;
    $list = $plan === 'plus_yearly' ? $p['yearly'] : $p['monthly'];
    if (!$list) {
        return null;
    }
    $weeks = rrp_week_unspent($owner);
    $pct = min(RRP_WEEK_DISCOUNT_MAX, RRP_WEEK_DISCOUNT_PCT * count($weeks));
    // The discount is for one month's price (yearly gets the same dollar amount off).
    $discount = (int) round($p['monthly'] * $pct / 100);
    return [
        'plan' => $months === 12 ? 'plus_yearly' : 'plus_monthly',
        'months' => $months,
        'list' => $list,
        'discount_pct' => $pct,
        'discount' => min($discount, $list),
        'amount' => max(0, $list - $discount),
        'currency' => $p['currency'],
        'weeks' => $weeks,
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
        'checkout_options' => ['redirect_url' => rrp_app_url('plus.php?paid=' . $orderId)],
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
        $pct = rrp_week_discount_pct($o);
        $text = 'Your Plus ends on ' . (new DateTimeImmutable((string) $o['plan_expires_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($o))->format('j M')
            . '. Renew on the Plus page' . ($pct ? " — you've earned {$pct}% off with your care weeks 🌟" : '') . '.';
        rrp_notify_owner_inbox((int) $o['id'], 'billing', 'Plus is ending soon', $text);
        rrp_try_web_push((int) $o['id'], 'SIPAW', $text, rrp_app_url('plus.php'));
        $stats['reminded']++;
    }
    return $stats;
}
