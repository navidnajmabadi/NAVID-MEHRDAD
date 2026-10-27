<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Square → SIPAW payment notifications. Signature checked before anything is trusted. */
$raw = (string) file_get_contents('php://input');
$sig = (string) ($_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '');
if (!rrp_square_verify($raw, $sig)) {
    rrp_json(['ok' => false, 'error' => 'bad signature'], 401);
}
$event = json_decode($raw, true);
if (!is_array($event)) {
    rrp_json(['ok' => false, 'error' => 'bad json'], 400);
}
rrp_json(rrp_square_handle_event($event));
