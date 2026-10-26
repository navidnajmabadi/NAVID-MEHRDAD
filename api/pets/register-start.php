<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$owner = rrp_current_owner();
if (!$owner) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Login required.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}
if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Session expired.']);
    exit;
}

$result = rrp_pet_register_start((int) $owner['id']);
http_response_code(!empty($result['ok']) ? 200 : 400);
echo json_encode($result);
