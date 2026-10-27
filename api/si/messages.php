<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** GET ?thread_id=&after_id= (polling) or ?pet_id= / ?before_id= (history). */
$owner = rrp_api_owner();
$ownerId = (int) $owner['id'];

$threadId = (int) ($_GET['thread_id'] ?? 0);
$petId = (int) ($_GET['pet_id'] ?? 0);
$thread = $threadId > 0
    ? rrp_sipo_thread_by_id($threadId, $ownerId)
    : ($petId > 0 ? rrp_sipo_thread_for_pet($ownerId, $petId) : null);
if (!$thread) {
    rrp_json(['ok' => false, 'error' => 'Chat not found'], 404);
}

rrp_json([
    'ok' => true,
    'thread_id' => (int) $thread['id'],
    'messages' => rrp_sipo_messages(
        (int) $thread['id'],
        (int) ($_GET['limit'] ?? 60),
        (int) ($_GET['before_id'] ?? 0),
        (int) ($_GET['after_id'] ?? 0)
    ),
]);
