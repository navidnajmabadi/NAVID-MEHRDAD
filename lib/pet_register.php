<?php
declare(strict_types=1);

require_once __DIR__ . '/openai/Client.php';

/**
 * Conversational SIPO pet registration — ask/collect, then create pet.
 */

function rrp_pet_register_fields(): array
{
    return [
        'name' => 'What do you call them?',
        'type' => 'Dog, cat, rabbit, or something else?',
        'breed' => 'Breed or mix? (ok to say Mixed / unsure)',
        'color' => 'Main color or markings?',
        'magic_word' => 'A private magic word only you and finders with the tag should know?',
        'identifying_marks' => 'Any scars, tags, or unique marks? (or say none)',
    ];
}

function rrp_pet_register_get_active(int $ownerId): ?array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT * FROM pet_register_sessions WHERE owner_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function rrp_pet_register_start(int $ownerId): array
{
    $limitOwner = rrp_current_owner();
    if ($limitOwner && (int) $limitOwner['id'] === $ownerId) {
        if (rrp_pet_count($ownerId, true) >= rrp_owner_pet_limit($limitOwner)) {
            return [
                'ok' => false,
                'gate' => rrp_is_super_owner($limitOwner) ? 'pet_cap' : (rrp_owner_plan($limitOwner) === 'plus' ? 'pet_cap' : 'plus_required'),
                'error' => 'You are at your active pet limit.',
                'upgrade_url' => 'plus.php',
            ];
        }
    }

    try {
        // Cancel older actives
        $c = rrp_db()->prepare("UPDATE pet_register_sessions SET status = 'cancelled' WHERE owner_id = ? AND status = 'active'");
        $c->execute([$ownerId]);

        $ins = rrp_db()->prepare(
            "INSERT INTO pet_register_sessions (owner_id, status, draft_json) VALUES (?, 'active', ?)"
        );
        $draft = json_encode(['fields' => []], JSON_UNESCAPED_UNICODE);
        $ins->execute([$ownerId, $draft]);
        $id = (int) rrp_db()->lastInsertId();

        $hello = "Hey — I’m SIPO 💙 Let’s register your pet together. What’s their name?";
        rrp_pet_register_add_message($id, 'sipo', $hello);

        $session = rrp_pet_register_session($id, $ownerId);
        return [
            'ok' => true,
            'session' => $session,
            'messages' => rrp_pet_register_messages($id),
        ];
    } catch (Throwable $e) {
        error_log('rrp_pet_register_start: ' . $e->getMessage());
        return [
            'ok' => false,
            'error' => 'Pet register chat needs sql/008_final_super_forgot_brain.sql imported.',
            'gate' => 'missing_table',
        ];
    }
}

function rrp_pet_register_session(int $sessionId, int $ownerId): ?array
{
    try {
        $stmt = rrp_db()->prepare('SELECT * FROM pet_register_sessions WHERE id = ? AND owner_id = ? LIMIT 1');
        $stmt->execute([$sessionId, $ownerId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function rrp_pet_register_messages(int $sessionId): array
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT id, sender_role, body, created_at FROM pet_register_messages WHERE session_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function rrp_pet_register_add_message(int $sessionId, string $role, string $body): void
{
    $role = $role === 'sipo' ? 'sipo' : 'owner';
    $body = mb_substr(trim($body), 0, 2000);
    $stmt = rrp_db()->prepare(
        'INSERT INTO pet_register_messages (session_id, sender_role, body) VALUES (?, ?, ?)'
    );
    $stmt->execute([$sessionId, $role, $body]);
}

function rrp_pet_register_draft(array $session): array
{
    $raw = (string) ($session['draft_json'] ?? '{}');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    if (!isset($data['fields']) || !is_array($data['fields'])) {
        $data['fields'] = [];
    }
    return $data;
}

function rrp_pet_register_save_draft(int $sessionId, array $draft): void
{
    $stmt = rrp_db()->prepare('UPDATE pet_register_sessions SET draft_json = ? WHERE id = ?');
    $stmt->execute([json_encode($draft, JSON_UNESCAPED_UNICODE), $sessionId]);
}

/**
 * Deterministic field fill + optional SI polish when key present.
 *
 * @return array{ok:bool, error?:string, gate?:string, session?:array, messages?:array, pet_id?:int, done?:bool}
 */
function rrp_pet_register_send(int $ownerId, array $owner, int $sessionId, string $text): array
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 1900) {
        return ['ok' => false, 'error' => 'Say something short (max 1900 chars).'];
    }

    $session = rrp_pet_register_session($sessionId, $ownerId);
    if (!$session || ($session['status'] ?? '') !== 'active') {
        return ['ok' => false, 'error' => 'Session not found. Start again.'];
    }

    rrp_pet_register_add_message($sessionId, 'owner', $text);
    $draft = rrp_pet_register_draft($session);
    $fields = rrp_pet_register_fields();
    $order = array_keys($fields);

    // Fill next missing field from owner reply (simple extract)
    $nextKey = null;
    foreach ($order as $key) {
        if (!isset($draft['fields'][$key]) || $draft['fields'][$key] === '') {
            $nextKey = $key;
            break;
        }
    }

    if ($nextKey !== null) {
        $value = $text;
        if ($nextKey === 'type') {
            $value = rrp_pet_register_normalize_type($text);
        }
        if ($nextKey === 'identifying_marks' && preg_match('/^(none|n\/a|no|nothing)$/i', trim($text))) {
            $value = '';
        }
        $draft['fields'][$nextKey] = $value;
        rrp_pet_register_save_draft($sessionId, $draft);
    }

    // Recompute next missing
    $missing = null;
    foreach ($order as $key) {
        if (!isset($draft['fields'][$key]) || $draft['fields'][$key] === '') {
            // identifying_marks may be intentionally empty after "none"
            if ($key === 'identifying_marks' && array_key_exists($key, $draft['fields'])) {
                continue;
            }
            $missing = $key;
            break;
        }
    }
    // After magic_word, identifying_marks is last — allow empty
    if ($missing === null && !array_key_exists('identifying_marks', $draft['fields'])) {
        $missing = 'identifying_marks';
    }

    if ($missing !== null) {
        $ask = $fields[$missing];
        $reply = rrp_pet_register_sipo_ask($owner, $draft, $missing, $ask, $text);
        rrp_pet_register_add_message($sessionId, 'sipo', $reply);
        return [
            'ok' => true,
            'session' => rrp_pet_register_session($sessionId, $ownerId),
            'messages' => rrp_pet_register_messages($sessionId),
            'done' => false,
        ];
    }

    // Complete → create pet
    $f = $draft['fields'];
    $typeRaw = (string) ($f['type'] ?? 'dog');
    $payload = [
        'name' => (string) ($f['name'] ?? 'Pet'),
        'type' => in_array($typeRaw, ['dog', 'cat', 'rabbit', 'custom'], true) ? $typeRaw : 'custom',
        'custom_type' => !in_array($typeRaw, ['dog', 'cat', 'rabbit'], true) ? $typeRaw : '',
        'breed' => (string) ($f['breed'] ?? ''),
        'color' => (string) ($f['color'] ?? ''),
        'magic_word' => (string) ($f['magic_word'] ?? ''),
        'identifying_marks' => (string) ($f['identifying_marks'] ?? ''),
        'medical_vaccines' => '',
        'medical_next_due' => '',
        'medical_pills' => '',
        'medical_conditions' => '',
    ];

    $created = rrp_create_pet($ownerId, $payload, null);
    if (empty($created['ok'])) {
        $err = $created['error'] ?? 'Could not create pet.';
        rrp_pet_register_add_message($sessionId, 'sipo', 'I hit a snag saving the profile: ' . $err . ' Want to try again?');
        return [
            'ok' => false,
            'error' => $err,
            'gate' => $created['gate'] ?? null,
            'messages' => rrp_pet_register_messages($sessionId),
        ];
    }

    $petId = (int) $created['pet_id'];
    $upd = rrp_db()->prepare(
        "UPDATE pet_register_sessions SET status = 'completed', pet_id = ? WHERE id = ?"
    );
    $upd->execute([$petId, $sessionId]);

    $name = $payload['name'];
    $doneMsg = "You’re all set — {$name} is on your pack 🐾 Next, pair a QR tag when you’re ready.";
    rrp_pet_register_add_message($sessionId, 'sipo', $doneMsg);

    return [
        'ok' => true,
        'done' => true,
        'pet_id' => $petId,
        'session' => rrp_pet_register_session($sessionId, $ownerId),
        'messages' => rrp_pet_register_messages($sessionId),
    ];
}

function rrp_pet_register_normalize_type(string $text): string
{
    $t = strtolower(trim($text));
    if (str_contains($t, 'dog') || str_contains($t, 'pup')) {
        return 'dog';
    }
    if (str_contains($t, 'cat') || str_contains($t, 'kitten')) {
        return 'cat';
    }
    if (str_contains($t, 'rabbit') || str_contains($t, 'bunny')) {
        return 'rabbit';
    }
    return 'custom';
}

function rrp_pet_register_sipo_ask(array $owner, array $draft, string $missing, string $ask, string $lastUser): string
{
    $name = $draft['fields']['name'] ?? '';
    $fallback = $name !== ''
        ? "Got it. For {$name}: {$ask}"
        : "Nice. {$ask}";

    $client = RrpOpenAiClient::product();
    if (!$client->configured()) {
        return $fallback;
    }

    $model = $client->modelFor('pet_register', 'gpt-6-luna');
    $sys = 'You are SIPO, caring SI companion in SIPAW. English only. One short warm question to collect the next pet field. Never ask for drug doses. No markdown.';
    $user = "Next field key={$missing}. Ask naturally: {$ask}. Last owner message: {$lastUser}. Known so far: " . json_encode($draft['fields']);
    $result = $client->chat([
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user', 'content' => $user],
    ], $model, 0.5);

    if (!empty($result['ok']) && trim((string) $result['content']) !== '') {
        return trim((string) $result['content']);
    }
    return $fallback;
}
