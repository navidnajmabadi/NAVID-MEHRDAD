<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Login required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

if (!rrp_verify_csrf($input['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
}

$action = (string) ($input['action'] ?? '');
$ownerId = (int) $owner['id'];

if ($action === 'trigger') {
    $result = rrp_trigger_lost($ownerId, (int) ($input['pet_id'] ?? 0));
    if (empty($result['ok'])) {
        rrp_json($result, 400);
    }
    rrp_json($result);
}

if ($action === 'found') {
    $result = rrp_mark_lost_found($ownerId, (int) ($input['alert_id'] ?? 0));
    if (empty($result['ok'])) {
        rrp_json($result, 400);
    }
    rrp_json($result);
}

rrp_json(['ok' => false, 'error' => 'Unknown action.'], 400);
