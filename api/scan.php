<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST required'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    $data = $_POST;
}

if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
}

$action = (string) ($data['action'] ?? '');
$publicId = trim((string) ($data['pet_public_id'] ?? ''));
$pet = $publicId !== '' ? rrp_pet_by_public_id($publicId) : null;
if (!$pet) {
    rrp_json(['ok' => false, 'error' => 'Pet not found'], 404);
}

$stmt = rrp_db()->prepare('SELECT * FROM owners WHERE id = ? LIMIT 1');
$stmt->execute([(int) $pet['owner_id']]);
$owner = $stmt->fetch();
if (!$owner || $owner['status'] !== 'active') {
    rrp_json(['ok' => false, 'error' => 'Owner unavailable'], 404);
}

if ($action === 'share') {
    $mode = (string) ($data['mode'] ?? 'one-time');
    if (!in_array($mode, ['one-time', 'live'], true)) {
        rrp_json(['ok' => false, 'error' => 'Invalid mode'], 400);
    }
    $lat = isset($data['lat']) ? (float) $data['lat'] : null;
    $lng = isset($data['lng']) ? (float) $data['lng'] : null;
    if ($lat === null || $lng === null) {
        rrp_json(['ok' => false, 'error' => 'Location required'], 400);
    }

    $created = rrp_record_scan((int) $pet['id'], $mode, $lat, $lng);
    rrp_notify_scan($pet, $owner, $created['scan_id'], $mode);
    rrp_json([
        'ok' => true,
        'scan_id' => $created['scan_id'],
        'expires_at' => $created['expires_at'],
    ]);
}

if ($action === 'update') {
    $scanId = (int) ($data['scan_id'] ?? 0);
    $lat = isset($data['lat']) ? (float) $data['lat'] : null;
    $lng = isset($data['lng']) ? (float) $data['lng'] : null;
    if (!$scanId || $lat === null || $lng === null) {
        rrp_json(['ok' => false, 'error' => 'scan_id and location required'], 400);
    }
    $scan = rrp_scan_by_id($scanId);
    if (!$scan || (int) $scan['pet_id'] !== (int) $pet['id']) {
        rrp_json(['ok' => false, 'error' => 'Scan not found'], 404);
    }
    $ok = rrp_update_live_scan($scanId, $lat, $lng);
    rrp_json(['ok' => $ok]);
}

if ($action === 'stop') {
    $scanId = (int) ($data['scan_id'] ?? 0);
    $scan = rrp_scan_by_id($scanId);
    if (!$scan || (int) $scan['pet_id'] !== (int) $pet['id']) {
        rrp_json(['ok' => false, 'error' => 'Scan not found'], 404);
    }
    rrp_stop_live_scan($scanId);
    rrp_json(['ok' => true]);
}

rrp_json(['ok' => false, 'error' => 'Unknown action'], 400);
