<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST required'], 405);
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

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    rrp_json(['ok' => false, 'error' => 'Incomplete subscription'], 400);
}

rrp_save_push_subscription((int) $owner['id'], $endpoint, $p256dh, $auth);
rrp_json(['ok' => true, 'note' => 'Subscription saved. Web Push delivery needs VAPID + a push library on the server; email + inbox still notify on scan.']);
