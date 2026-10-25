<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST required'], 405);
}

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data) || !$data) {
    $data = $_POST;
}
if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF'], 403);
}

$petId = (int) ($data['pet_id'] ?? $data['id'] ?? 0);
$result = rrp_archive_pet((int) $owner['id'], $petId);
rrp_json($result, empty($result['ok']) ? 400 : 200);
