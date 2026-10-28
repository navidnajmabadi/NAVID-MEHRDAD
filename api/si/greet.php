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
$msg = null;
try {
    $msg = rrp_sipo_greet_if_due($owner, $thread);
} catch (Throwable $e) {
    error_log('SIPO greet failed: ' . $e->getMessage());
}
// QA B28: SIPO always speaks first in an empty chat, even if the model is down or slow to answer.
$empty = (int) rrp_db()->query('SELECT COUNT(*) FROM chat_messages WHERE thread_id = ' . (int) $thread['id'])->fetchColumn() === 0;
if ($empty && (!$msg || str_starts_with((string) ($msg['body'] ?? ''), 'I could not think'))) {
    $msg = rrp_sipo_insert_message((int) $thread['id'], 'assistant', rrp_sipo_static_welcome($owner, $thread), 0, ['mascot' => 'happy', 'proactive' => 'welcome']);
}
rrp_json(['ok' => true, 'assistant' => $msg]);
