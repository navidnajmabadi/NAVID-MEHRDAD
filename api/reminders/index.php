<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$petId = (int) ($_GET['pet_id'] ?? ($_POST['pet_id'] ?? 0));

if ($method === 'GET') {
    if (rrp_owner_plan($owner) !== 'plus') {
        rrp_json(rrp_plus_required_payload('reminders'), 402);
    }
    $format = (string) ($_GET['format'] ?? 'json');
    if ($format === 'ics') {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="sipaw-reminders.ics"');
        echo rrp_care_reminders_ics((int) $owner['id'], $petId);
        exit;
    }
    rrp_json([
        'ok' => true,
        'reminders' => rrp_care_reminders_for_pet($petId),
        'templates' => rrp_routine_templates(),
    ]);
}

if ($method !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (rrp_owner_plan($owner) !== 'plus') {
    rrp_json(rrp_plus_required_payload('reminders'), 402);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data) || !$data) {
    $data = $_POST;
}
if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF'], 403);
}

$action = (string) ($data['action'] ?? 'create');
$petId = (int) ($data['pet_id'] ?? $petId);

if ($action === 'create') {
    $pet = rrp_pet_by_id($petId, (int) $owner['id']);
    if (!$pet) {
        rrp_json(['ok' => false, 'error' => 'Pet not found'], 404);
    }
    $result = rrp_care_reminder_create($petId, $data);
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

if ($action === 'status') {
    $result = rrp_care_reminder_set_status((int) $owner['id'], (int) ($data['reminder_id'] ?? 0), (string) ($data['status'] ?? ''));
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

if ($action === 'apply_pack') {
    $result = rrp_apply_routine_pack((int) $owner['id'], $petId, (string) ($data['pack_key'] ?? ''));
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

rrp_json(['ok' => false, 'error' => 'Unknown action'], 400);
