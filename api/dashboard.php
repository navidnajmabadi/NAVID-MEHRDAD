<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

rrp_start_session();
$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$scans = rrp_scans_for_owner((int) $owner['id'], 40);
$live = rrp_live_scan_for_owner((int) $owner['id']);

rrp_json([
    'ok' => true,
    'scans' => array_map(static function (array $s): array {
        return [
            'id' => (int) $s['id'],
            'pet_name' => $s['pet_name'],
            'public_id' => $s['public_id'],
            'mode' => $s['mode'],
            'lat' => $s['lat'] !== null ? (float) $s['lat'] : null,
            'lng' => $s['lng'] !== null ? (float) $s['lng'] : null,
            'active' => (bool) $s['active'],
            'scanned_at' => $s['scanned_at'],
            'updated_at' => $s['updated_at'],
            'expires_at' => $s['expires_at'],
        ];
    }, $scans),
    'live' => $live ? [
        'id' => (int) $live['id'],
        'pet_name' => $live['pet_name'],
        'lat' => (float) $live['lat'],
        'lng' => (float) $live['lng'],
        'updated_at' => $live['updated_at'],
        'expires_at' => $live['expires_at'],
    ] : null,
]);
