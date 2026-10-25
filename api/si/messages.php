<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$threadId = (int) ($_GET['thread_id'] ?? 0);
$petId = (int) ($_GET['pet_id'] ?? 0);
if ($threadId <= 0 && $petId > 0) {
    $thread = rrp_si_thread_for_pet((int) $owner['id'], $petId);
    $threadId = $thread ? (int) $thread['id'] : 0;
}
if ($threadId <= 0) {
    rrp_json(['ok' => false, 'error' => 'thread_id or pet_id required'], 400);
}

$stmt = rrp_db()->prepare('SELECT * FROM chat_threads WHERE id = ? AND owner_id = ? LIMIT 1');
$stmt->execute([$threadId, (int) $owner['id']]);
$thread = $stmt->fetch();
if (!$thread) {
    rrp_json(['ok' => false, 'error' => 'Not allowed'], 403);
}

rrp_json([
    'ok' => true,
    'thread_id' => $threadId,
    'messages' => rrp_si_recent_messages($threadId, 100),
]);
