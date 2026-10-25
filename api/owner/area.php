<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    rrp_json(['ok' => true, 'area' => rrp_owner_area($owner)]);
}

if ($method === 'PUT' || $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data) || !$data) {
        $data = $_POST;
    }
    if (!rrp_verify_csrf($data['csrf'] ?? null)) {
        rrp_json(['ok' => false, 'error' => 'Invalid CSRF'], 403);
    }
    $lat = (float) ($data['lat'] ?? 0);
    $lng = (float) ($data['lng'] ?? 0);
    $radius = (float) ($data['radius_km'] ?? 25);
    $label = isset($data['label']) ? (string) $data['label'] : null;
    $result = rrp_save_owner_area((int) $owner['id'], $lat, $lng, $radius, $label);
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

rrp_json(['ok' => false, 'error' => 'Method not allowed'], 405);
