<?php
declare(strict_types=1);

/**
 * SIPO long memory: every message is kept; the model sees
 *   saved facts (sipo_memory) + rolling summary (chat_threads.summary) + last RRP_SIPO_RECENT_TURNS messages.
 * Older messages fold into the summary so token cost stays flat as a chat grows.
 */

const RRP_SIPO_RECENT_TURNS = 24;
const RRP_SIPO_SUMMARY_TRIGGER = 60; // unsummarised messages before folding

/** @return list<array{fact_key:string,fact_value:string,category:string}> */
function rrp_sipo_facts(int $petId): array
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT fact_key, fact_value, category FROM sipo_memory WHERE pet_id = ? ORDER BY category, fact_key LIMIT 120'
        );
        $stmt->execute([$petId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function rrp_sipo_save_fact(int $ownerId, int $petId, string $key, string $value, string $category = 'other', ?int $sourceMessageId = null): bool
{
    $key = strtolower(trim(preg_replace('/[^a-z0-9_ ]+/i', '', $key) ?? ''));
    $key = mb_substr(str_replace(' ', '_', $key), 0, 80);
    $value = mb_substr(trim($value), 0, 500);
    $allowed = ['identity', 'health', 'food', 'routine', 'behavior', 'home', 'owner', 'other'];
    if (!in_array($category, $allowed, true)) {
        $category = 'other';
    }
    if ($key === '' || $value === '') {
        return false;
    }
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO sipo_memory (owner_id, pet_id, fact_key, fact_value, category, source_message_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE fact_value = VALUES(fact_value), category = VALUES(category),
                                     source_message_id = VALUES(source_message_id)'
        );
        $stmt->execute([$ownerId, $petId, $key, $value, $category, $sourceMessageId]);
        return true;
    } catch (Throwable $e) {
        error_log('rrp_sipo_save_fact: ' . $e->getMessage());
        return false;
    }
}

/**
 * Recent messages after the summary point, oldest first.
 * @return list<array<string,mixed>>
 */
function rrp_sipo_recent_for_model(array $thread, int $limit = RRP_SIPO_RECENT_TURNS): array
{
    $limit = max(4, min(80, $limit));
    $stmt = rrp_db()->prepare(
        "SELECT id, sender_role, body, created_at FROM chat_messages
         WHERE thread_id = ? AND id > ? ORDER BY id DESC LIMIT {$limit}"
    );
    $stmt->execute([(int) $thread['id'], (int) ($thread['summary_upto_id'] ?? 0)]);
    return array_reverse($stmt->fetchAll());
}

/**
 * Fold older messages into the thread summary when enough have piled up.
 * Safe to call often: does nothing below the trigger or without a key.
 */
function rrp_sipo_maybe_summarize(array $thread, ?RrpOpenAiClient $client = null): bool
{
    $threadId = (int) $thread['id'];
    $upto = (int) ($thread['summary_upto_id'] ?? 0);
    try {
        $cnt = rrp_db()->prepare('SELECT COUNT(*) FROM chat_messages WHERE thread_id = ? AND id > ?');
        $cnt->execute([$threadId, $upto]);
        $pending = (int) $cnt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
    if ($pending < RRP_SIPO_SUMMARY_TRIGGER) {
        return false;
    }
    $client = $client ?? RrpOpenAiClient::product();
    if (!$client->configured()) {
        return false;
    }

    // Fold everything except the most recent turns.
    $keep = RRP_SIPO_RECENT_TURNS;
    $fold = $pending - $keep;
    $stmt = rrp_db()->prepare(
        "SELECT id, sender_role, body, created_at FROM chat_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC LIMIT {$fold}"
    );
    $stmt->execute([$threadId, $upto]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return false;
    }
    $transcript = [];
    foreach ($rows as $r) {
        $who = ($r['sender_role'] === 'assistant') ? 'SIPO' : 'Owner';
        $transcript[] = substr((string) $r['created_at'], 0, 10) . ' ' . $who . ': ' . mb_substr((string) $r['body'], 0, 600);
    }
    $prev = trim((string) ($thread['summary'] ?? ''));
    $messages = [
        ['role' => 'system', 'content' =>
            'You maintain the long-term memory of a pet-care chat. Merge the previous summary and the new '
            . 'transcript into one updated summary (max 350 words). Keep: facts about the pet, health events '
            . 'with dates, routines, preferences, open questions, promises SIPO made, owner mood patterns. '
            . 'Drop small talk. Plain bullet points, newest facts win.'],
        ['role' => 'user', 'content' => "Previous summary:\n" . ($prev !== '' ? $prev : '(none)')
            . "\n\nNew transcript:\n" . implode("\n", $transcript)],
    ];
    $res = $client->chat($messages, $client->modelFor('si_chat'), 0.2);
    if (empty($res['ok'])) {
        return false;
    }
    $last = (int) end($rows)['id'];
    try {
        $upd = rrp_db()->prepare('UPDATE chat_threads SET summary = ?, summary_upto_id = ? WHERE id = ?');
        $upd->execute([mb_substr((string) $res['content'], 0, 8000), $last, $threadId]);
        rrp_sipo_track_tokens((int) $thread['owner_id'], (int) ($res['usage']['total_tokens'] ?? 0));
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
