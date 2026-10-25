<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Convenience alias for clients that hit this URL for status.
    $configured = rrp_vapid_configured();
    $owner = rrp_current_owner();
    $count = 0;
    if ($owner) {
        $count = count(rrp_push_subscriptions_for_owner((int) $owner['id']));
    }
    rrp_json([
        'ok' => true,
        'configured' => $configured,
        'publicKey' => $configured ? rrp_vapid_public_key() : '',
        'logged_in' => (bool) $owner,
        'subscription_count' => $count,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST or GET required'], 405);
}

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$raw = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($raw)) {
    rrp_json(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$endpoint = (string) ($raw['endpoint'] ?? '');
$keys = $raw['keys'] ?? [];
$p256dh = (string) ($keys['p256dh'] ?? '');
$auth = (string) ($keys['auth'] ?? '');
$ua = isset($raw['userAgent']) ? (string) $raw['userAgent'] : rrp_client_ua();

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    rrp_json(['ok' => false, 'error' => 'Incomplete subscription'], 400);
}

if (!rrp_vapid_configured()) {
    // Still store so push works once VAPID is added — do not crash.
    rrp_save_push_subscription((int) $owner['id'], $endpoint, $p256dh, $auth, $ua);
    rrp_json([
        'ok' => true,
        'saved' => true,
        'configured' => false,
        'note' => 'Subscription saved. Server VAPID keys are empty — email + inbox still notify. Admin: add vapid keys to sipaw.php.',
    ]);
}

rrp_save_push_subscription((int) $owner['id'], $endpoint, $p256dh, $auth, $ua);
rrp_json(['ok' => true, 'saved' => true, 'configured' => true]);
