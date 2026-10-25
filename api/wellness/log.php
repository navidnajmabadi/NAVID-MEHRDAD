<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $petId = (int) ($_GET['pet_id'] ?? 0);
    if (rrp_owner_plan($owner) !== 'plus') {
        rrp_json(rrp_plus_required_payload('wellness'), 402);
    }
    rrp_json([
        'ok' => true,
        'defs' => rrp_wellness_symptom_defs(),
        'history' => rrp_wellness_history((int) $owner['id'], $petId, 30),
    ]);
}

if ($method !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (rrp_owner_plan($owner) !== 'plus') {
    rrp_json(rrp_plus_required_payload('wellness'), 402);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data) || !$data) {
    $data = $_POST;
}
if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF'], 403);
}

$scores = $data['scores'] ?? [];
if (!is_array($scores)) {
    $scores = [];
}
$result = rrp_wellness_save_day(
    (int) $owner['id'],
    (int) ($data['pet_id'] ?? 0),
    (string) ($data['date'] ?? date('Y-m-d')),
    $scores,
    isset($data['note']) ? (string) $data['note'] : null,
    isset($data['triggers']) && is_array($data['triggers']) ? $data['triggers'] : null
);
rrp_json($result, empty($result['ok']) ? 400 : 200);
