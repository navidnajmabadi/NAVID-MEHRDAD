<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** The open chat fired a short reminder on time: log it in the chat and stop the cron repeating it. */
[$owner, $data] = rrp_api_owner_post();
$id = (int) ($data['reminder_id'] ?? 0);
$stmt = rrp_db()->prepare(
    "SELECT r.*, p.name AS pet_name FROM care_reminders r JOIN pets p ON p.id = r.pet_id
     WHERE r.id = ? AND p.owner_id = ? AND r.followup_stage < 5 LIMIT 1"
);
$stmt->execute([$id, (int) $owner['id']]);
$r = $stmt->fetch();
if (!$r) {
    rrp_json(['ok' => true, 'already' => true]);
}
// Atomic claim: when two open copies of the app fire together, only the first one posts the message.
$claim = rrp_db()->prepare("UPDATE care_reminders SET followup_stage = 5, last_followup_at = UTC_TIMESTAMP(), status = 'sent' WHERE id = ? AND followup_stage < 5");
$claim->execute([$id]);
if ($claim->rowCount() !== 1) {
    rrp_json(['ok' => true, 'already' => true]);
}
$thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $r['pet_id']);
$msg = $thread ? rrp_sipo_insert_message((int) $thread['id'], 'assistant', '⏰ Reminder: ' . $r['title'], 0, ['mascot' => 'happy', 'proactive' => 'reminder']) : null;
rrp_json(['ok' => true, 'assistant' => $msg]);
