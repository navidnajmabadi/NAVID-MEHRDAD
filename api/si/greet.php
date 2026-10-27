<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** SIPO speaks first when the owner opens the chat on a new day (or a new pet). */
[$owner, $data] = rrp_api_owner_post();

$thread = rrp_sipo_thread_by_id((int) ($data['thread_id'] ?? 0), (int) $owner['id']);
if (!$thread) {
    rrp_json(['ok' => false, 'error' => 'Chat not found'], 404);
}
rrp_sipo_record_activity($owner);
$msg = rrp_sipo_greet_if_due($owner, $thread);
rrp_json(['ok' => true, 'assistant' => $msg]);
