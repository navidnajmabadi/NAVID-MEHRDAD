<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/**
 * App Store Server Notifications V2 (set the URL in App Store Connect → App → App Information).
 * Apple sends {"signedPayload": "<JWS>"}; it is verified like a purchase, then renewals extend Plus and refunds end it.
 * Answers 200 for anything verified (so Apple stops retrying) and 400 for anything that is not signed by Apple.
 */
header('Content-Type: application/json; charset=utf-8');
$raw = (string) file_get_contents('php://input');
$body = json_decode($raw, true);
$n = is_array($body) && is_string($body['signedPayload'] ?? null) ? rrp_apple_verify_jws($body['signedPayload']) : null;
if (!$n) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}
$cfg = rrp_apple_cfg();
$data = (array) ($n['data'] ?? []);
if (($data['bundleId'] ?? '') !== $cfg['bundle_id']) {
    echo '{"ok":true,"ignored":"other app"}';
    exit;
}
$t = is_string($data['signedTransactionInfo'] ?? null) ? rrp_apple_verify_jws($data['signedTransactionInfo']) : null;
if (!$t || !in_array((string) ($t['productId'] ?? ''), $cfg['product_ids'], true)) {
    echo '{"ok":true,"ignored":"no transaction"}';
    exit;
}
$orig = (string) ($t['originalTransactionId'] ?? '');
$st = rrp_db()->prepare('SELECT id FROM owners WHERE apple_original_tx = ? LIMIT 1');
$st->execute([$orig]);
$ownerId = (int) $st->fetchColumn();
if ($ownerId <= 0) {
    echo '{"ok":true,"ignored":"unknown owner"}';   // the app has not linked this purchase to an account yet; it will on its next sync
    exit;
}
$type = (string) ($n['notificationType'] ?? '');
if (in_array($type, ['REFUND', 'REVOKE'], true)) {
    rrp_apple_revoke($ownerId, $orig);
} else {
    rrp_apple_store($ownerId, $t);   // SUBSCRIBED, DID_RENEW, EXPIRED, ...: Plus follows the verified expiry date
}
echo '{"ok":true}';
