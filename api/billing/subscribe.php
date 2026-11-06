<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Pay now: the card form gave a one-time token; we save the card and charge the price computed here. */
[$owner, $data] = rrp_api_owner_post();
$token = !empty($data['saved']) ? null : trim((string) ($data['token'] ?? ''));
if ($token !== null && ($token === '' || strlen($token) > 200)) {
    rrp_json(['ok' => false, 'error' => 'The card could not be read. Please try again.'], 400);
}
$r = rrp_billing_subscribe($owner, $token, [
    'coupon' => (string) ($data['coupon'] ?? ''),
    'use_coins' => !empty($data['use_coins']),
    'auto_renew' => !empty($data['auto_renew']),
]);
if (empty($r['ok'])) {
    rrp_json(['ok' => false, 'error' => $r['error'] ?? 'The payment could not be completed.']);
}
rrp_json(['ok' => true, 'order_id' => $r['order_id'] ?? null, 'amount' => $r['amount'] ?? null]);
