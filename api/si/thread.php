<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$petId = (int) ($_GET['pet_id'] ?? 0);
$thread = rrp_si_thread_for_pet((int) $owner['id'], $petId);
if (!$thread) {
    rrp_json(['ok' => false, 'error' => 'Pet not found'], 404);
}

rrp_json([
    'ok' => true,
    'thread' => [
        'id' => (int) $thread['id'],
        'pet_id' => (int) $thread['pet_id'],
        'title' => $thread['title'] ?? null,
        'si_model' => $thread['si_model'] ?? null,
    ],
    'gate' => rrp_si_gate($owner),
]);
