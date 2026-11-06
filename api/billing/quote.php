<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Price preview for the Plus page: every discount on its own line. The server decides; the page only shows it. */
[$owner, $data] = rrp_api_owner_post();
$q = rrp_billing_quote($owner, 'plus_monthly', ['coupon' => (string) ($data['coupon'] ?? ''), 'use_coins' => !empty($data['use_coins'])]);
if (!$q) {
    rrp_json(['ok' => false, 'error' => 'This plan is not available yet.']);
}
$couponAsked = trim((string) ($data['coupon'] ?? '')) !== '';
rrp_json([
    'ok' => true,
    'quote' => $q,
    'currency' => $q['currency'],
    'coupon_ok' => $q['coupon'] !== null,
    'coupon_error' => $couponAsked && $q['coupon'] === null ? 'That code is not valid or has run out.' : null,
]);
