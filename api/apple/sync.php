<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/**
 * The iPhone app sends the signed transactions Apple gave it (after a purchase, a restore, or on launch).
 * Each one is verified here (Apple's certificate chain and signature, our bundle and product, this owner's account token).
 */
[$owner, $data] = rrp_api_owner_post();
$list = array_slice(array_values(array_filter((array) ($data['transactions'] ?? []), 'is_string')), 0, 12);
if (!$list) {
    rrp_json(['ok' => false, 'error' => 'No purchase was sent.'], 400);
}
$last = null;
$errors = [];
foreach ($list as $jws) {
    $r = rrp_apple_apply_transaction((int) $owner['id'], $jws);
    if (!empty($r['ok'])) {
        $last = $r;
    } else {
        $errors[] = $r['error'] ?? 'Not accepted.';
    }
}
if (!$last) {
    rrp_json(['ok' => false, 'error' => $errors[0] ?? 'The purchase could not be verified.'], 400);
}
rrp_json(['ok' => true, 'plan' => $last['plan'], 'expires' => $last['expires']]);
