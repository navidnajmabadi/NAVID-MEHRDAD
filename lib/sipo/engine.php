<?php
declare(strict_types=1);

/**
 * SIPO engine: one chat thread per pet, forever.
 * A new pet starts as an onboarding thread (pet_id NULL); SIPO creates the pet
 * with a tool and the same thread then belongs to that pet.
 */

const RRP_SIPO_MAX_TOOL_ROUNDS = 4;
const RRP_SIPO_MAX_BODY = 4000;

// -----------------------------------------------------------------------------
// Threads + current pet
// -----------------------------------------------------------------------------

function rrp_sipo_thread_for_pet(int $ownerId, int $petId): ?array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet || (int) ($pet['is_active'] ?? 1) !== 1) {
        return null;
    }
    $stmt = rrp_db()->prepare(
        "SELECT * FROM chat_threads WHERE owner_id = ? AND pet_id = ? AND thread_kind = 'si' ORDER BY id ASC LIMIT 1"
    );
    $stmt->execute([$ownerId, $petId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $ins = rrp_db()->prepare(
        "INSERT INTO chat_threads (thread_kind, onboarding, title, owner_id, pet_id) VALUES ('si', 0, ?, ?, ?)"
    );
    $ins->execute(['SIPO · ' . $pet['name'], $ownerId, $petId]);
    return rrp_sipo_thread_by_id((int) rrp_db()->lastInsertId(), $ownerId);
}

/** Open (or resume) the onboarding thread used to add a new pet. */
function rrp_sipo_onboarding_thread(int $ownerId): array
{
    $stmt = rrp_db()->prepare(
        "SELECT * FROM chat_threads WHERE owner_id = ? AND thread_kind = 'si' AND pet_id IS NULL AND onboarding = 1 ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$ownerId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $ins = rrp_db()->prepare(
        "INSERT INTO chat_threads (thread_kind, onboarding, title, owner_id, pet_id) VALUES ('si', 1, 'SIPO · new pet', ?, NULL)"
    );
    $ins->execute([$ownerId]);
    return rrp_sipo_thread_by_id((int) rrp_db()->lastInsertId(), $ownerId);
}

function rrp_sipo_thread_by_id(int $threadId, int $ownerId): ?array
{
    $stmt = rrp_db()->prepare("SELECT * FROM chat_threads WHERE id = ? AND owner_id = ? AND thread_kind = 'si' LIMIT 1");
    $stmt->execute([$threadId, $ownerId]);
    return $stmt->fetch() ?: null;
}

function rrp_sipo_bind_thread_to_pet(int $threadId, array $pet): void
{
    $stmt = rrp_db()->prepare('UPDATE chat_threads SET pet_id = ?, onboarding = 0, title = ? WHERE id = ?');
    $stmt->execute([(int) $pet['id'], 'SIPO · ' . $pet['name'], $threadId]);
}

function rrp_sipo_set_current_pet(int $ownerId, ?int $petId): void
{
    rrp_start_session();
    $_SESSION['current_pet_id'] = $petId;
    try {
        rrp_db()->prepare('UPDATE owners SET current_pet_id = ? WHERE id = ?')->execute([$petId, $ownerId]);
    } catch (Throwable $e) {
    }
}

/** The pet the whole app is showing (Instagram-style switcher). */
function rrp_sipo_current_pet(array $owner, ?array $pets = null): ?array
{
    $pets = $pets ?? rrp_pets_by_owner((int) $owner['id']);
    if (!$pets) {
        return null;
    }
    rrp_start_session();
    $want = (int) ($_SESSION['current_pet_id'] ?? $owner['current_pet_id'] ?? 0);
    foreach ($pets as $p) {
        if ((int) $p['id'] === $want) {
            return $p;
        }
    }
    return $pets[0];
}

// -----------------------------------------------------------------------------
// Messages
// -----------------------------------------------------------------------------

/**
 * Owner feedback (2026-10-28): no dashes in SIPO's voice. Number ranges keep a plain hyphen,
 * dash punctuation becomes a comma, "- " bullets become "• ", divider lines are dropped.
 */
function rrp_sipo_clean_text(string $text): string
{
    $t = preg_replace('/(\d)\s*[—–]\s*(\d)/u', '$1-$2', $text) ?? $text;
    $t = preg_replace('/^\s*[-—–_*]{3,}\s*$/mu', '', $t) ?? $t;
    $t = preg_replace('/^(\s*)[-–—]\s+/mu', '$1• ', $t) ?? $t;
    $t = preg_replace('/\s*[—–]\s*/u', ', ', $t) ?? $t;
    $t = preg_replace('/(\p{L}) - (\p{L})/u', '$1, $2', $t) ?? $t;
    $t = preg_replace('/,\s*([,.!?])/u', '$1', $t) ?? $t;
    $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
    return trim($t);
}

function rrp_sipo_insert_message(int $threadId, string $role, string $body, int $tokens = 0, ?array $meta = null): ?array
{
    $role = in_array($role, ['user', 'assistant'], true) ? $role : 'user';
    if ($role === 'assistant') {
        $body = rrp_sipo_clean_text($body);
    }
    $body = mb_substr($body, 0, RRP_SIPO_MAX_BODY);
    $stmt = rrp_db()->prepare(
        'INSERT INTO chat_messages (thread_id, sender_role, body, token_count, meta_json) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$threadId, $role, $body, $tokens > 0 ? $tokens : null, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    $id = (int) rrp_db()->lastInsertId();
    rrp_db()->prepare('UPDATE chat_threads SET last_message_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$threadId]);
    $q = rrp_db()->prepare('SELECT id, thread_id, sender_role, body, meta_json, created_at FROM chat_messages WHERE id = ?');
    $q->execute([$id]);
    return rrp_sipo_present_message($q->fetch() ?: []);
}

/** Shape a DB row for the client. */
function rrp_sipo_present_message(array $row): array
{
    if (!$row) {
        return [];
    }
    $meta = !empty($row['meta_json']) ? (json_decode((string) $row['meta_json'], true) ?: []) : [];
    $body = (string) $row['body'];
    $role = (string) $row['sender_role'];
    // Pre-006 fallback rows stored SIPO replies as owner + "[SIPO] " prefix.
    if ($role === 'owner' && str_starts_with($body, '[SIPO] ')) {
        $role = 'assistant';
        $body = substr($body, 7);
    }
    return [
        'id' => (int) $row['id'],
        'role' => $role === 'assistant' ? 'assistant' : 'user',
        'body' => $body,
        'mascot' => $meta['mascot'] ?? null,
        'proactive' => !empty($meta['proactive']),
        'created_at' => gmdate(DATE_ATOM, strtotime((string) $row['created_at'] . ' UTC') ?: time()),
    ];
}

/** @return list<array> newest last */
function rrp_sipo_messages(int $threadId, int $limit = 60, int $beforeId = 0, int $afterId = 0): array
{
    $limit = max(1, min(200, $limit));
    $where = 'thread_id = ?';
    $args = [$threadId];
    if ($beforeId > 0) {
        $where .= ' AND id < ?';
        $args[] = $beforeId;
    }
    if ($afterId > 0) {
        $where .= ' AND id > ?';
        $args[] = $afterId;
    }
    $stmt = rrp_db()->prepare(
        "SELECT id, thread_id, sender_role, body, meta_json, created_at FROM chat_messages WHERE {$where} ORDER BY id DESC LIMIT {$limit}"
    );
    $stmt->execute($args);
    return array_map('rrp_sipo_present_message', array_reverse($stmt->fetchAll()));
}

// -----------------------------------------------------------------------------
// Reply
// -----------------------------------------------------------------------------

/**
 * Owner sends a message → SIPO answers (with tools).
 * @return array{ok:bool, error?:string, gate?:string, message?:array, assistant?:array, pet?:array, events?:list<string>}
 */
function rrp_sipo_send(array $owner, array $thread, string $body): array
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > RRP_SIPO_MAX_BODY) {
        return ['ok' => false, 'error' => 'Message required (max ' . RRP_SIPO_MAX_BODY . ' characters).'];
    }
    $gate = rrp_sipo_gate($owner, $body);
    if (empty($gate['ok'])) {
        return $gate;
    }
    $ownerId = (int) $owner['id'];
    $pet = !empty($thread['pet_id']) ? rrp_pet_by_id((int) $thread['pet_id'], $ownerId) : null;

    $userMsg = rrp_sipo_insert_message((int) $thread['id'], 'user', $body);
    rrp_sipo_record_activity($owner);
    rrp_si_bump_usage($ownerId, 1, 0);

    $ctx = [
        'owner' => $owner,
        'thread' => $thread,
        'pet' => $pet,
        'user_message_id' => $userMsg['id'] ?? null,
        'emergency' => !empty($gate['emergency']),
        'events' => [],
    ];
    // Safety net (live bug 2026-10-28: "Is Violet a dog?" asked twice): read profile facts from
    // the owner's words ourselves, so nothing depends on the model remembering to call a tool.
    rrp_sipo_extract_profile($ctx);

    $reply = rrp_sipo_generate($ctx, null);

    $assistant = rrp_sipo_insert_message(
        (int) $ctx['thread']['id'],
        'assistant',
        $reply['text'],
        $reply['tokens'],
        ['mascot' => $reply['mascot'], 'tools' => $reply['tools'], 'model' => $reply['model'], 'pv' => rrp_naki_core_version_id()]
    );
    if (!empty($assistant['id'])) {
        rrp_si_maybe_encrypt_message((int) $assistant['id'], $reply['text']);
    }
    rrp_sipo_maybe_summarize($ctx['thread']);

    return [
        'ok' => true,
        'message' => $userMsg,
        'assistant' => $assistant,
        'pet' => $ctx['pet'] ? rrp_sipo_pet_brief($ctx['pet']) : null,
        'progress' => $ctx['pet'] ? rrp_sipo_profile_progress(rrp_pet_by_id((int) $ctx['pet']['id'], $ownerId) ?: $ctx['pet']) : null,
        'events' => $ctx['events'],
        'reminders' => $ctx['reminders'] ?? [],
        'coins' => rrp_coin_balance($ownerId),
        'warning' => $reply['warning'] ?? null,
    ];
}

/**
 * Run the model with tools. $instruction (proactive mode) is appended as a final system note.
 * @return array{text:string, tokens:int, mascot:string, tools:list<string>, model:?string, warning?:string}
 */
function rrp_sipo_generate(array &$ctx, ?string $instruction): array
{
    $owner = $ctx['owner'];
    $client = RrpOpenAiClient::product();
    if (!$client->configured()) {
        return rrp_sipo_offline_reply($ctx, $instruction) + ['warning' => 'missing_api_key'];
    }

    $live = rrp_sipo_live_context($owner);
    $onboarding = $ctx['pet'] === null;
    $messages = [['role' => 'system', 'content' => rrp_sipo_system_prompt($owner, $ctx['pet'], $ctx['thread'], $live, $ctx['emergency'])]];
    foreach (rrp_sipo_recent_for_model($ctx['thread']) as $m) {
        $messages[] = [
            'role' => $m['sender_role'] === 'assistant' ? 'assistant' : 'user',
            'content' => (string) $m['body'],
        ];
    }
    if ($instruction !== null) {
        $messages[] = ['role' => 'system', 'content' => $instruction];
    }

    $tokens = 0;
    $used = [];
    $model = $client->modelFor('si_chat');
    for ($round = 0; $round <= RRP_SIPO_MAX_TOOL_ROUNDS; $round++) {
        $tools = $round < RRP_SIPO_MAX_TOOL_ROUNDS ? rrp_sipo_tool_defs($ctx['pet'] === null) : [];
        $res = $client->complete($messages, $tools, $model, 0.6);
        if (empty($res['ok'])) {
            error_log('SIPO model error: ' . ($res['error'] ?? '?'));
            rrp_sipo_track_tokens((int) $owner['id'], $tokens);
            $text = 'I could not think that through just now — our side has a problem, not you. The team is being told. Please try again in a few minutes 💙';
            if (rrp_is_super_owner($owner)) {
                // Admins see the real cause instead of a silent "snag".
                $text .= "\n\n[Admin] OpenAI said (HTTP " . (int) ($res['status'] ?? 0) . ', model ' . ($res['model'] ?? $model) . '): '
                    . mb_substr((string) ($res['error'] ?? 'unknown error'), 0, 300)
                    . "\nFix in config/sipaw.php: openai.api_key, openai.models.si_chat, or set openai.fallback_model.";
            }
            return [
                'text' => $text,
                'tokens' => $tokens,
                'mascot' => 'idle',
                'tools' => $used,
                'model' => $model,
                'warning' => (string) ($res['error'] ?? 'model_error'),
            ];
        }
        $tokens += (int) ($res['usage']['total_tokens'] ?? 0);
        $calls = $res['tool_calls'] ?? [];
        if (!$calls) {
            rrp_sipo_track_tokens((int) $owner['id'], $tokens);
            rrp_si_bump_usage((int) $owner['id'], 0, $tokens);
            return [
                'text' => trim((string) $res['content']),
                'tokens' => $tokens,
                'mascot' => rrp_sipo_pick_mascot($ctx),
                'tools' => $used,
                'model' => $res['model'] ?? $model,
            ];
        }
        $messages[] = ['role' => 'assistant', 'content' => $res['content'] !== '' ? $res['content'] : null, 'tool_calls' => $calls];
        foreach ($calls as $call) {
            $name = (string) ($call['function']['name'] ?? '');
            $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
            $result = rrp_sipo_run_tool($name, is_array($args) ? $args : [], $ctx);
            $used[] = $name;
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => (string) ($call['id'] ?? ''),
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }
        if ($onboarding && $ctx['pet'] !== null) {
            // Pet now exists: refresh the system prompt so the next round sees the pet card + new tools.
            $onboarding = false;
            $messages[0]['content'] = rrp_sipo_system_prompt($owner, $ctx['pet'], $ctx['thread'], $live, $ctx['emergency']);
        }
    }
    rrp_sipo_track_tokens((int) $owner['id'], $tokens);
    return ['text' => 'Done 💙', 'tokens' => $tokens, 'mascot' => 'happy', 'tools' => $used, 'model' => $model];
}

/**
 * Server-side profile extraction while the profile is incomplete. One small JSON call reads the
 * last few messages and returns only facts the OWNER stated; we create the pet (onboarding) or
 * fill fields that are still empty. Never overwrites known values.
 */
function rrp_sipo_extract_profile(array &$ctx): void
{
    $pet = $ctx['pet'];
    if ($pet !== null && rrp_sipo_profile_progress($pet)['done'] >= 6) {
        return;
    }
    $client = RrpOpenAiClient::product();
    if (!$client->configured()) {
        return;
    }
    // Onboarding: read further back so a name said early (e.g. Violet's chat) is still found.
    $recent = rrp_sipo_recent_for_model($ctx['thread'], $pet === null ? 40 : 12);
    $lines = [];
    foreach ($recent as $m) {
        $lines[] = ($m['sender_role'] === 'assistant' ? 'SIPO' : 'Owner') . ': ' . mb_substr((string) $m['body'], 0, 500);
    }
    $res = $client->json([
        ['role' => 'system', 'content' =>
            'Extract pet profile facts that the OWNER clearly stated in this chat. Ignore guesses and SIPO\'s questions. '
            . 'Return JSON only: {"name":string|null,"kind":"dog"|"cat"|"bird"|"rabbit"|"hamster"|"other"|null,"other_kind":string|null,'
            . '"breed":string|null,"sex":"male"|"female"|null,"age_years":number|null,"birth_date":"YYYY-MM-DD"|null,'
            . '"weight_kg":number|null,"neutered":boolean|null}. Convert lb to kg. Pronouns she/her → female, he/him → male '
            . 'only when they clearly refer to the pet.'],
        ['role' => 'user', 'content' => implode("\n", $lines)],
    ]);
    rrp_sipo_note_extract($ctx, $res);
    if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
        return;
    }
    rrp_sipo_track_tokens((int) $ctx['owner']['id'], (int) ($res['usage']['total_tokens'] ?? 0));
    $x = $res['data'];
    $ownerId = (int) $ctx['owner']['id'];

    if ($pet === null) {
        $name = trim((string) ($x['name'] ?? ''));
        $kind = (string) ($x['kind'] ?? '');
        if ($name === '' || !in_array($kind, ['dog', 'cat', 'bird', 'rabbit', 'hamster', 'other'], true)) {
            return; // not enough yet; SIPO keeps asking
        }
        $made = rrp_sipo_run_tool('create_pet_profile', [
            'name' => $name, 'kind' => $kind, 'other_kind' => (string) ($x['other_kind'] ?? ''), 'breed' => (string) ($x['breed'] ?? ''),
        ], $ctx);
        if (empty($made['ok'])) {
            rrp_sipo_note_extract($ctx, ['ok' => false, 'error' => 'create_pet_profile failed: ' . ($made['error'] ?? '?')]);
            return;
        }
        $pet = $ctx['pet'];
    }
    $fill = [];
    foreach (['breed', 'sex', 'age_years', 'birth_date', 'weight_kg', 'neutered'] as $k) {
        if (!isset($x[$k]) || $x[$k] === null || $x[$k] === '') {
            continue;
        }
        $have = $pet[$k] ?? null;
        $empty = $have === null || $have === '' || ($k === 'sex' && $have === 'unknown');
        if ($empty) {
            $fill[$k] = $x[$k];
        }
    }
    if ($fill) {
        rrp_sipo_update_pet_fields((int) $pet['id'], $ownerId, $fill);
        $ctx['pet'] = rrp_pet_by_id((int) $pet['id'], $ownerId) ?: $pet;
        $ctx['events'][] = 'profile_updated';
    }
}

/** Last profile-extraction result, shown on Admin → SIPO check (live debugging without logs). */
function rrp_sipo_note_extract(array $ctx, array $res): void
{
    $info = [
        'at' => gmdate('c'),
        'thread_id' => (int) ($ctx['thread']['id'] ?? 0),
        'onboarding' => $ctx['pet'] === null,
        'ok' => !empty($res['ok']),
        'data' => $res['data'] ?? null,
        'error' => $res['error'] ?? null,
    ];
    @file_put_contents(sys_get_temp_dir() . '/sipaw-last-extract.json', json_encode($info), LOCK_EX);
}

function rrp_sipo_pick_mascot(array $ctx): string
{
    $ev = $ctx['events'] ?? [];
    if (in_array('emergency', $ev, true)) {
        return 'alert';
    }
    if (array_intersect($ev, ['pet_created', 'reminder_set', 'profile_updated'])) {
        return 'happy';
    }
    return 'idle';
}

/** No OpenAI key yet: still welcome the owner; never a 500. */
function rrp_sipo_offline_reply(array $ctx, ?string $instruction): array
{
    $nick = rrp_sipo_owner_nickname($ctx['owner']);
    if ($ctx['pet'] === null) {
        $text = "Hi {$nick}, I'm SIPO 💙 I'll help you keep track of your pet's care. My brain is still being switched on — "
            . 'you can add your pet from the form for now, and I\'ll pick up from there.';
    } elseif ($instruction !== null) {
        $text = "Hi {$nick} 💙 How is {$ctx['pet']['name']} doing today?";
    } else {
        $text = "I'm almost ready 💙 SIPO's brain needs its key in the Hostinger config (openai.api_key). Then ask me again about {$ctx['pet']['name']}.";
    }
    return ['text' => $text, 'tokens' => 0, 'mascot' => 'idle', 'tools' => [], 'model' => null];
}

function rrp_sipo_pet_brief(array $pet): array
{
    return [
        'id' => (int) $pet['id'],
        'name' => (string) $pet['name'],
        'kind' => rrp_pet_type_label($pet),
        'avatar' => !empty($pet['image_path']) ? rrp_asset((string) $pet['image_path']) : null,
        'initial' => mb_strtoupper(mb_substr((string) $pet['name'], 0, 1)),
    ];
}

// -----------------------------------------------------------------------------
// Proactive (SIPO speaks first)
// -----------------------------------------------------------------------------

/**
 * SIPO writes a message nobody asked for: a greeting when the owner opens the
 * app on a new day, or a nudge from cron. Respects Cool Down (not emergencies).
 * @return array|null the stored assistant message
 */
function rrp_sipo_proactive(array $owner, array $thread, string $kind): ?array
{
    if (rrp_sipo_cooldown_until($owner) !== null) {
        return null;
    }
    $pet = !empty($thread['pet_id']) ? rrp_pet_by_id((int) $thread['pet_id'], (int) $owner['id']) : null;
    $ctx = [
        'owner' => $owner,
        'thread' => $thread,
        'pet' => $pet,
        'user_message_id' => null,
        'emergency' => false,
        'events' => [],
    ];
    $instruction = rrp_sipo_proactive_instruction($kind, $pet !== null);
    $reply = rrp_sipo_generate($ctx, $instruction);
    if (trim($reply['text']) === '') {
        return null;
    }
    return rrp_sipo_insert_message(
        (int) $thread['id'],
        'assistant',
        $reply['text'],
        $reply['tokens'],
        ['mascot' => $reply['mascot'] === 'idle' ? 'happy' : $reply['mascot'], 'proactive' => $kind, 'model' => $reply['model']]
    );
}

function rrp_sipo_proactive_instruction(string $kind, bool $hasPet): string
{
    if (!$hasPet) {
        return 'The owner just opened a new-pet chat. Greet them warmly by nickname, introduce yourself in one line, '
            . 'and ask the pet\'s name. Do not call tools yet.';
    }
    $base = 'You are starting the conversation (the owner has not written yet). Write ONE short message (max 45 words). '
        . 'Do not call tools. Use local time and weather naturally. ';
    return $base . match ($kind) {
        'greeting' => 'They just opened the app. Greet them for this part of the day and ask one caring question: '
            . 'prefer an overdue/upcoming reminder, then the most useful unknown profile item, then how the pet is.',
        'checkin' => 'This is a gentle check-in sent as a phone notification. Ask one easy question about today '
            . '(meal, walk, mood, bathroom) or follow up on something unresolved from the summary.',
        'profile_gap' => 'Kindly ask for ONE missing profile item that would improve care, and say why in a few words.',
        'reminder' => 'Follow up on an overdue or due-today reminder: ask if it happened, offer to find a vet.',
        'walk' => 'No walk logged yet today. Suggest a good time from the walk window and weather in Daily life, '
            . 'warmly, and mention the walk tracker on the Daily page.',
        default => 'Say hello and ask how the pet is.',
    };
}

/** Greeting on open: once per local day per thread, only if SIPO hasn't already spoken today. */
function rrp_sipo_greet_if_due(array $owner, array $thread): ?array
{
    $now = rrp_sipo_local_now($owner);
    $startLocal = $now->setTime(0, 0);
    $startUtc = $startLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $stmt = rrp_db()->prepare('SELECT sender_role FROM chat_messages WHERE thread_id = ? AND created_at >= ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int) $thread['id'], $startUtc]);
    $last = $stmt->fetchColumn();
    if ($last !== false) {
        return null; // already talked today
    }
    $isNewThread = (int) rrp_db()->query('SELECT COUNT(*) FROM chat_messages WHERE thread_id = ' . (int) $thread['id'])->fetchColumn() === 0;
    $kind = $isNewThread && empty($thread['pet_id']) ? 'onboarding' : 'greeting';
    $msg = rrp_sipo_proactive($owner, $thread, $kind);
    if ($msg && !empty($thread['pet_id'])) {
        rrp_sipo_log_nudge($owner, (int) $thread['pet_id'], (int) $thread['id'], (int) $msg['id'], 'greeting', false);
    }
    return $msg;
}

function rrp_sipo_log_nudge(array $owner, int $petId, int $threadId, int $messageId, string $kind, bool $pushed): void
{
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO sipo_nudges (owner_id, pet_id, thread_id, message_id, kind, local_day, pushed, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            (int) $owner['id'], $petId, $threadId, $messageId, $kind,
            rrp_sipo_local_now($owner)->format('Y-m-d'), $pushed ? 1 : 0,
        ]);
    } catch (Throwable $e) {
    }
}
