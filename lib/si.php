<?php
declare(strict_types=1);

require_once __DIR__ . '/openai/Client.php';

function rrp_si_toronto_day(): string
{
    $tz = new DateTimeZone('America/Toronto');
    return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
}

function rrp_si_usage_row(int $ownerId, string $day): array
{
    try {
        $stmt = rrp_db()->prepare('SELECT * FROM si_usage_daily WHERE owner_id = ? AND usage_day = ? LIMIT 1');
        $stmt->execute([$ownerId, $day]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $ins = rrp_db()->prepare(
            'INSERT INTO si_usage_daily (owner_id, usage_day, message_count, token_count) VALUES (?, ?, 0, 0)'
        );
        $ins->execute([$ownerId, $day]);
        $stmt->execute([$ownerId, $day]);
        return $stmt->fetch() ?: [
            'owner_id' => $ownerId,
            'usage_day' => $day,
            'message_count' => 0,
            'token_count' => 0,
        ];
    } catch (Throwable $e) {
        return [
            'owner_id' => $ownerId,
            'usage_day' => $day,
            'message_count' => 0,
            'token_count' => 0,
        ];
    }
}

function rrp_si_bump_usage(int $ownerId, int $msgs = 1, int $tokens = 0): void
{
    $day = rrp_si_toronto_day();
    rrp_si_usage_row($ownerId, $day);
    try {
        $stmt = rrp_db()->prepare(
            'UPDATE si_usage_daily SET message_count = message_count + ?, token_count = token_count + ? WHERE owner_id = ? AND usage_day = ?'
        );
        $stmt->execute([$msgs, $tokens, $ownerId, $day]);
    } catch (Throwable $e) {
        // table may be missing until 006
    }
}

/**
 * @return array{ok:bool, gate?:string, error?:string, teaser?:bool, upgrade_url?:string}
 */
function rrp_si_gate(array $owner): array
{
    if (rrp_is_super_owner($owner)) {
        return ['ok' => true, 'teaser' => false, 'super' => true];
    }

    $plan = rrp_owner_plan($owner);
    $day = rrp_si_toronto_day();
    $usage = rrp_si_usage_row((int) $owner['id'], $day);
    $msgs = (int) ($usage['message_count'] ?? 0);

    if ($plan !== 'plus') {
        if ($msgs >= RRP_SI_TEASER_MSGS) {
            return rrp_plus_required_payload('si_chat') + ['teaser' => false];
        }
        return ['ok' => true, 'teaser' => true];
    }

    if ($msgs >= RRP_SI_PLUS_DAILY_MSGS) {
        return [
            'ok' => false,
            'gate' => 'rate_limit',
            'error' => 'Daily SI message limit reached. Resets at midnight America/Toronto.',
        ];
    }
    if ((int) ($usage['token_count'] ?? 0) >= RRP_SI_PLUS_DAILY_TOKENS) {
        return [
            'ok' => false,
            'gate' => 'rate_limit',
            'error' => 'Daily SI token limit reached. Resets at midnight America/Toronto.',
        ];
    }
    return ['ok' => true, 'teaser' => false];
}

function rrp_si_looks_like_dosing(string $text): bool
{
    return (bool) preg_match('/\b(\d+\s*(mg|mcg|ml)\b|mg\/kg|dose\s+of|give\s+\d+)/i', $text);
}

function rrp_si_system_prompt(array $pet): string
{
    $name = $pet['name'] ?? 'your pet';
    $type = rrp_pet_type_label($pet);
    return <<<TXT
You are SIPO, the caring SI (Super Intelligence) pet companion inside SIPAW by Nakilab.
Speak warmly, briefly, with occasional caring emoji. English only.
Pet: {$name} ({$type}).
Never invent drug doses (mg/kg). If asked for dosing or diagnosis, refuse and say to call their vet.
You may draft reminder ideas, explain health notes at a high level, and encourage logging wellness — not medical practice.
TXT;
}

function rrp_si_thread_for_pet(int $ownerId, int $petId): ?array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet || (int) ($pet['is_active'] ?? 1) !== 1) {
        return null;
    }
    try {
        $stmt = rrp_db()->prepare(
            "SELECT * FROM chat_threads WHERE owner_id = ? AND pet_id = ? AND thread_kind = 'si' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$ownerId, $petId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $ins = rrp_db()->prepare(
            "INSERT INTO chat_threads (thread_kind, title, owner_id, pet_id, finder_token) VALUES ('si', ?, ?, ?, NULL)"
        );
        $ins->execute(['SIPO · ' . $pet['name'], $ownerId, $petId]);
    } catch (Throwable $e) {
        // Pre-006: no thread_kind — use a dedicated finder_token marker for SI
        $token = 'si:' . $ownerId . ':' . $petId;
        $stmt = rrp_db()->prepare(
            'SELECT * FROM chat_threads WHERE owner_id = ? AND pet_id = ? AND finder_token = ? LIMIT 1'
        );
        $stmt->execute([$ownerId, $petId, $token]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $ins = rrp_db()->prepare(
            'INSERT INTO chat_threads (owner_id, pet_id, finder_token, finder_name) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$ownerId, $petId, $token, 'SIPO']);
    }
    $id = (int) rrp_db()->lastInsertId();
    $stmt = rrp_db()->prepare('SELECT * FROM chat_threads WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * @return array{ok:bool, error?:string, gate?:string, message?:array, assistant?:array, warning?:string}
 */
function rrp_si_send(int $ownerId, array $owner, int $petId, string $body): array
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 1900) {
        return ['ok' => false, 'error' => 'Message required (max 1900 chars).'];
    }

    $gate = rrp_si_gate($owner);
    if (empty($gate['ok'])) {
        return $gate;
    }

    $thread = rrp_si_thread_for_pet($ownerId, $petId);
    if (!$thread) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }

    $userMsg = rrp_si_insert_message((int) $thread['id'], 'user', $body);
    if (!$userMsg) {
        return ['ok' => false, 'error' => 'Could not save message.'];
    }

    if (rrp_si_looks_like_dosing($body)) {
        $refusal = "I can't suggest drug doses or mg/kg 🩺 — please ask your vet for exact dosing for {$pet['name']}.";
        $assistant = rrp_si_insert_message((int) $thread['id'], 'assistant', $refusal);
        rrp_si_bump_usage($ownerId, 1, 0);
        return ['ok' => true, 'message' => $userMsg, 'assistant' => $assistant, 'gate' => 'medical_refusal'];
    }

    $history = rrp_si_recent_messages((int) $thread['id'], 20);
    $messages = [['role' => 'system', 'content' => rrp_si_system_prompt($pet)]];
    foreach ($history as $m) {
        $sr = (string) ($m['sender_role'] ?? '');
        $role = in_array($sr, ['assistant'], true) ? 'assistant' : 'user';
        $messages[] = ['role' => $role, 'content' => (string) $m['body']];
    }

    $client = new RrpOpenAiClient();
    $result = $client->chat($messages);
    if (empty($result['ok'])) {
        $fallback = "SIPO is almost ready 💙 Add your OpenAI key in Hostinger config (openai.api_key), then ask again about {$pet['name']}.";
        if (($result['gate'] ?? '') !== 'missing_api_key') {
            $fallback = 'SIPO hit a snag talking to SI. Try again in a moment — or ask on the owner boards.';
        }
        $assistant = rrp_si_insert_message((int) $thread['id'], 'assistant', $fallback);
        rrp_si_bump_usage($ownerId, 1, 0);
        return [
            'ok' => true,
            'message' => $userMsg,
            'assistant' => $assistant,
            'warning' => $result['error'] ?? 'openai_unavailable',
            'gate' => $result['gate'] ?? null,
        ];
    }

    $tokens = (int) (($result['usage']['total_tokens'] ?? 0));
    $assistant = rrp_si_insert_message(
        (int) $thread['id'],
        'assistant',
        (string) $result['content'],
        $tokens
    );
    if (!empty($result['model'])) {
        try {
            $upd = rrp_db()->prepare('UPDATE chat_threads SET si_model = ? WHERE id = ?');
            $upd->execute([(string) $result['model'], (int) $thread['id']]);
        } catch (Throwable $e) {
            // column may be missing
        }
    }
    rrp_si_bump_usage($ownerId, 1, $tokens);
    rrp_si_maybe_encrypt_message((int) ($assistant['id'] ?? 0), (string) $result['content']);

    return ['ok' => true, 'message' => $userMsg, 'assistant' => $assistant];
}

function rrp_si_insert_message(int $threadId, string $role, string $body, int $tokens = 0): ?array
{
    $role = in_array($role, ['user', 'assistant', 'owner', 'finder'], true) ? $role : 'user';
    $body = mb_substr($body, 0, 2000);
    try {
        if ($tokens > 0) {
            $stmt = rrp_db()->prepare(
                'INSERT INTO chat_messages (thread_id, sender_role, body, token_count) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$threadId, $role, $body, $tokens]);
        } else {
            $stmt = rrp_db()->prepare(
                'INSERT INTO chat_messages (thread_id, sender_role, body) VALUES (?, ?, ?)'
            );
            $stmt->execute([$threadId, $role, $body]);
        }
    } catch (Throwable $e) {
        // Pre-006 enum may only allow owner|finder
        $fallbackRole = $role === 'assistant' ? 'owner' : 'owner';
        if ($role === 'user') {
            $fallbackRole = 'owner';
        }
        try {
            $stmt = rrp_db()->prepare(
                'INSERT INTO chat_messages (thread_id, sender_role, body) VALUES (?, ?, ?)'
            );
            $stmt->execute([$threadId, $fallbackRole, ($role === 'assistant' ? '[SIPO] ' : '') . $body]);
        } catch (Throwable $e2) {
            error_log('rrp_si_insert_message: ' . $e2->getMessage());
            return null;
        }
    }
    try {
        rrp_db()->prepare('UPDATE chat_threads SET last_message_at = NOW() WHERE id = ?')->execute([$threadId]);
    } catch (Throwable $e) {
    }
    $id = (int) rrp_db()->lastInsertId();
    $q = rrp_db()->prepare('SELECT * FROM chat_messages WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function rrp_si_recent_messages(int $threadId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = rrp_db()->prepare(
        "SELECT id, thread_id, sender_role, body, created_at FROM chat_messages WHERE thread_id = ? ORDER BY id DESC LIMIT {$limit}"
    );
    $stmt->execute([$threadId]);
    $rows = $stmt->fetchAll();
    return array_reverse($rows);
}

function rrp_si_maybe_encrypt_message(int $messageId, string $plaintext): void
{
    if ($messageId <= 0 || $plaintext === '' || !rrp_config_ready()) {
        return;
    }
    $cfg = rrp_load_config()['openai']['si_transcript'] ?? [];
    $master = (string) ($cfg['master_key'] ?? '');
    if ($master === '') {
        return;
    }
    $raw = base64_decode($master, true);
    if ($raw === false || strlen($raw) !== 32) {
        return;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $raw, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        return;
    }
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO si_message_secrets (message_id, ciphertext, iv, tag, sha256_hex)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ciphertext = VALUES(ciphertext), iv = VALUES(iv), tag = VALUES(tag), sha256_hex = VALUES(sha256_hex)'
        );
        $stmt->execute([
            $messageId,
            $cipher,
            $iv,
            $tag,
            hash('sha256', $plaintext),
        ]);
    } catch (Throwable $e) {
        // 007 not imported
    }
}
