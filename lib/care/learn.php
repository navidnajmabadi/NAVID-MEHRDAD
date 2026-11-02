<?php
declare(strict_types=1);

/**
 * SIPO learns a pet's day: one question at a time (never more than one waiting), asked at most every
 * 3 hours, in code so the order and wording never drift. It logs the answer, learns the usual time
 * of day from the logs, re-checks it after 7 days (on a random day), and speaks up when a usual
 * routine slips. Also proposes the default care reminders and sets them up once the owner agrees.
 */

const RRP_DAILY_ITEMS = [
    'water' => ['💧', 'Water', 'drink water'],
    'pee' => ['💦', 'Pee', 'pee'],
    'poop' => ['💩', 'Poop', 'poop'],
    'teeth' => ['🪥', 'Teeth', 'have their teeth brushed'],
];

const RRP_LEARN_NOW = 'Just now';
const RRP_LEARN_1H = '1–2 hours ago';
const RRP_LEARN_EARLIER = 'Earlier today';
const RRP_LEARN_UNSURE = 'Not sure';
const RRP_LEARN_YES = 'Yes, that is right';
const RRP_LEARN_CHANGED = 'It changed';
const RRP_SLIP_FINE = 'All fine';
const RRP_SLIP_FORGOT = 'I forgot to log it';
const RRP_SLIP_OFF = 'Something is off';
const RRP_REM_YES = 'Yes, remind me';
const RRP_REM_LESS = 'Less often';
const RRP_REM_MORE = 'More often';
const RRP_REM_NO = 'No thanks';

/** Default reminders SIPO proposes: topic => [catalog key suffix, how to say it]. */
const RRP_REM_TOPICS = [
    'nails' => ['nails', 'trim %s nails'],
    'bath' => ['bath', 'give %s a bath'],
    'labs' => ['bloodwork', 'book %s blood and urine tests'],
    'vaccine' => ['rabies', 'book %s vaccine booster'],
];

// ---- logging -------------------------------------------------------------------------------

/** Log water or teeth (pee and poop use the bathroom log). $at defaults to now. */
function rrp_daily_check_log(array $owner, array $pet, string $kind, ?DateTimeImmutable $at = null, string $source = 'button'): array
{
    if (!isset(RRP_DAILY_ITEMS[$kind])) {
        return ['ok' => false, 'error' => 'Unknown check.'];
    }
    $tz = rrp_sipo_owner_tz($owner);
    $at = ($at ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);
    if ($kind === 'pee' || $kind === 'poop') {
        return rrp_bath_log($owner, $pet, $kind, null, null, $source === 'button' ? 'button' : 'chat', $at->format('H:i'));
    }
    try {
        rrp_db()->prepare('INSERT INTO pet_daily_checks (pet_id, kind, logged_at, local_day, source) VALUES (?, ?, ?, ?, ?)')
            ->execute([(int) $pet['id'], $kind, $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $at->format('Y-m-d'), mb_substr($source, 0, 12)]);
    } catch (Throwable $e) {
        error_log('rrp_daily_check_log: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save that.'];
    }
    return ['ok' => true];
}

/** Local times of the last $days days of logs for one kind, newest first. @return list<DateTimeImmutable> */
function rrp_daily_logs(array $owner, array $pet, string $kind, int $days = 30): array
{
    $tz = rrp_sipo_owner_tz($owner);
    try {
        if ($kind === 'pee' || $kind === 'poop') {
            $q = rrp_db()->prepare('SELECT logged_at FROM pet_bathroom_logs WHERE pet_id = ? AND kind = ? AND logged_at > (UTC_TIMESTAMP() - INTERVAL ' . (int) $days . ' DAY) ORDER BY logged_at DESC LIMIT 400');
            $q->execute([(int) $pet['id'], $kind]);
        } else {
            $q = rrp_db()->prepare('SELECT logged_at FROM pet_daily_checks WHERE pet_id = ? AND kind = ? AND logged_at > (UTC_TIMESTAMP() - INTERVAL ' . (int) $days . ' DAY) ORDER BY logged_at DESC LIMIT 400');
            $q->execute([(int) $pet['id'], $kind]);
        }
        return array_map(static fn($t) => (new DateTimeImmutable((string) $t, new DateTimeZone('UTC')))->setTimezone($tz), $q->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * The usual time of day for a routine, from the logs: the earliest time cluster is not guessed,
 * we use the median time of the FIRST log of each day (the "morning one"), needing 3 different days.
 * @return array{n:int, minute:int, hhmm:string}|null
 */
function rrp_learn_pattern(array $owner, array $pet, string $kind): ?array
{
    $firstPerDay = [];
    foreach (array_reverse(rrp_daily_logs($owner, $pet, $kind, 30)) as $t) {
        $d = $t->format('Y-m-d');
        $firstPerDay[$d] ??= (int) $t->format('G') * 60 + (int) $t->format('i');
    }
    if (count($firstPerDay) < 3) {
        return null;
    }
    $m = (int) round(rrp_median(array_values($firstPerDay)));
    return ['n' => count($firstPerDay), 'minute' => $m, 'hhmm' => sprintf('%02d:%02d', intdiv($m, 60), $m % 60)];
}

/** Today's count and last time per daily item, plus the learned usual time. */
function rrp_daily_today(array $owner, array $pet): array
{
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    $out = [];
    foreach (RRP_DAILY_ITEMS as $kind => $meta) {
        $todayLogs = array_values(array_filter(rrp_daily_logs($owner, $pet, $kind, 2), static fn($t) => $t->format('Y-m-d') === $today));
        $pat = rrp_learn_pattern($owner, $pet, $kind);
        $out[$kind] = ['icon' => $meta[0], 'label' => $meta[1], 'count' => count($todayLogs), 'last' => $todayLogs ? $todayLogs[0]->format('H:i') : null, 'usual' => $pat['hhmm'] ?? null];
    }
    return $out;
}

// ---- state (one small fact per topic, one pending question per pet) ---------------------------

function rrp_learn_state(int $petId, string $key): array
{
    try {
        $q = rrp_db()->prepare('SELECT fact_value FROM sipo_memory WHERE pet_id = ? AND fact_key = ? LIMIT 1');
        $q->execute([$petId, $key]);
        $v = (string) ($q->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $v = '';
    }
    [$stage, $at] = array_pad(explode('@', $v, 2), 2, '0');
    return ['stage' => $stage, 'at' => (int) $at];
}

function rrp_learn_set(int $ownerId, int $petId, string $key, string $stage): void
{
    rrp_sipo_save_fact($ownerId, $petId, $key, $stage . '@' . time(), 'routine');
}

/** @return array{type:string, topic:string, at:int}|null */
function rrp_learn_pending(int $petId): ?array
{
    $s = rrp_learn_state($petId, 'learn_pending');
    if ($s['stage'] === '' || $s['stage'] === 'none') {
        return null;
    }
    [$type, $topic] = array_pad(explode(':', $s['stage'], 2), 2, '');
    return $type !== '' && $topic !== '' ? ['type' => $type, 'topic' => $topic, 'at' => $s['at']] : null;
}

function rrp_learn_clear(int $ownerId, int $petId): void
{
    rrp_learn_set($ownerId, $petId, 'learn_pending', 'none');
}

// ---- what to ask next ---------------------------------------------------------------------------

function rrp_rem_interval(array $owner, array $pet, string $topic): int
{
    [$suffix] = RRP_REM_TOPICS[$topic];
    foreach (rrp_care_catalog(rrp_care_species($pet), rrp_care_region($owner, $pet), $pet['lifestyle'] ?? null) as $c) {
        if ($c['key'] === rrp_care_species($pet) . '_' . $suffix) {
            return (int) $c['interval_days'];
        }
    }
    return 0;
}

function rrp_days_phrase(int $d): string
{
    return match (true) {
        $d === 1 => 'day',
        $d % 365 === 0 => $d === 365 ? 'year' : ($d / 365) . ' years',
        $d % 30 === 0 => $d === 30 ? 'month' : ($d / 30) . ' months',
        $d % 7 === 0 => $d === 7 ? 'week' : ($d / 7) . ' weeks',
        default => $d . ' days',
    };
}

function rrp_learn_possessive(string $name): string
{
    return $name . (str_ends_with($name, 's') ? '’' : '’s');
}

/** @return array{type:string, topic:string, text:string, choices:list<string>, mascot:string}|null */
function rrp_learn_next(array $owner, array $pet): ?array
{
    $pid = (int) $pet['id'];
    $name = (string) $pet['name'];
    $poss = rrp_learn_possessive($name);
    if (!in_array($pet['type'] ?? '', ['dog', 'cat'], true)) {
        return null;
    }
    $now = rrp_sipo_local_now($owner);
    $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');
    $today = $now->format('Y-m-d');
    $today_ = rrp_daily_today($owner, $pet);

    // 1. A usual routine slipped today (3 hours past the usual time, nothing logged, asked once a day).
    foreach (['water', 'pee', 'poop'] as $kind) {
        $pat = rrp_learn_pattern($owner, $pet, $kind);
        $asked = rrp_learn_state($pid, 'slip_' . $kind);
        if ($pat && $today_[$kind]['count'] === 0 && $nowMin > $pat['minute'] + 180 && $nowMin < 21 * 60
            && $now->setTimestamp($asked['at'])->format('Y-m-d') !== $today) {
            $verb = RRP_DAILY_ITEMS[$kind][2];
            return ['type' => 'slip', 'topic' => $kind, 'mascot' => 'concerned', 'choices' => [RRP_SLIP_FINE, RRP_SLIP_FORGOT, RRP_SLIP_OFF],
                'text' => "{$name} usually does a {$kind} by about {$pat['hhmm']}, and I have not seen one today. Is everything okay?"];
        }
    }
    // 2. A learned time is re-checked after 7 days, on a random day, once every two weeks.
    foreach (array_keys(RRP_DAILY_ITEMS) as $kind) {
        $st = rrp_learn_state($pid, 'learn_' . $kind);
        $ver = rrp_learn_state($pid, 'verify_' . $kind);
        $pat = rrp_learn_pattern($owner, $pet, $kind);
        if ($st['stage'] === 'answered' && $pat && $st['at'] < time() - 7 * 86400 && $ver['at'] < time() - 14 * 86400
            && crc32($pid . $kind . $today) % 3 === 0) {
            return ['type' => 'verify', 'topic' => $kind, 'mascot' => 'thinking', 'choices' => [RRP_LEARN_YES, RRP_LEARN_CHANGED],
                'text' => "I have noticed {$name} usually does {$kind} around {$pat['hhmm']}. Is that still right?"];
        }
    }
    // 3. Learn the day, one topic at a time: water, pee, poop, teeth.
    foreach (array_keys(RRP_DAILY_ITEMS) as $kind) {
        if (rrp_learn_state($pid, 'learn_' . $kind)['stage'] === '') {
            $verb = RRP_DAILY_ITEMS[$kind][2];
            return ['type' => 'learn', 'topic' => $kind, 'mascot' => 'happy', 'choices' => [RRP_LEARN_NOW, RRP_LEARN_1H, RRP_LEARN_EARLIER, RRP_LEARN_UNSURE],
                'text' => "A little question to learn {$poss} day: when did {$name} last {$verb}? It helps me notice if something changes."];
        }
    }
    // 4. Default care reminders: guess first, owner confirms.
    foreach (RRP_REM_TOPICS as $topic => [$suffix, $phrase]) {
        if ($topic === 'bath' && ($pet['type'] ?? '') !== 'dog') {
            continue;
        }
        if (rrp_learn_state($pid, 'rem_' . $topic)['stage'] !== '') {
            continue;
        }
        $days = rrp_rem_interval($owner, $pet, $topic);
        if ($days < 1) {
            continue;
        }
        $what = sprintf($phrase, $poss);
        $hasPlan = rrp_learn_state($pid, 'interval_' . rrp_care_species($pet) . '_' . $suffix)['stage'] !== '';
        if ($hasPlan) {
            continue;
        }
        return ['type' => 'rem', 'topic' => $topic, 'mascot' => 'happy', 'choices' => [RRP_REM_YES, RRP_REM_LESS, RRP_REM_MORE, RRP_REM_NO],
            'text' => "Shall I remind you to {$what} every " . rrp_days_phrase($days) . "? That is what vets usually suggest for a {$pet['type']}. I will pick a time that suits you, and you can change it."];
    }
    return null;
}

// ---- answering (called from the chat before the model) ------------------------------------------

function rrp_learn_intercept(array &$ctx, string $body): ?array
{
    $pet = $ctx['pet'] ?? null;
    if (!$pet) {
        return null;
    }
    $owner = $ctx['owner'];
    $pid = (int) $pet['id'];
    $pend = rrp_learn_pending($pid);
    if (!$pend || $pend['at'] < time() - 3 * 86400) {
        return null;
    }
    $b = trim($body);
    $name = (string) $pet['name'];
    $kind = $pend['topic'];
    $reply = static fn(string $t, string $m = 'happy'): array => ['text' => $t, 'tokens' => 0, 'mascot' => $m, 'tools' => [], 'model' => null];
    $oid = (int) $owner['id'];

    if ($pend['type'] === 'learn' && in_array($b, [RRP_LEARN_NOW, RRP_LEARN_1H, RRP_LEARN_EARLIER, RRP_LEARN_UNSURE], true) && isset(RRP_DAILY_ITEMS[$kind])) {
        rrp_learn_clear($oid, $pid);
        rrp_learn_set($oid, $pid, 'learn_' . $kind, 'answered');
        if ($b === RRP_LEARN_UNSURE) {
            return $reply("That is okay 💙 When you notice it, tap it in Routines or tell me. I will learn {$name}'s usual time over the next days.");
        }
        $tz = rrp_sipo_owner_tz($owner);
        $now = new DateTimeImmutable('now', $tz);
        $at = $b === RRP_LEARN_NOW ? $now : $now->modify($b === RRP_LEARN_1H ? '-90 minutes' : '-4 hours');
        if ($at->format('Y-m-d') !== $now->format('Y-m-d')) {
            $at = $now->setTime(0, 5);
        }
        rrp_daily_check_log($owner, $pet, $kind, $at, 'chat');
        return $reply('Thank you 💙 I wrote it down at ' . $at->format('H:i') . ". I will watch {$name}'s usual time over the next days and tell you if something changes.");
    }
    if ($pend['type'] === 'verify' && in_array($b, [RRP_LEARN_YES, RRP_LEARN_CHANGED], true)) {
        rrp_learn_clear($oid, $pid);
        rrp_learn_set($oid, $pid, 'verify_' . $kind, 'done');
        return $b === RRP_LEARN_YES ? $reply('Lovely, thank you 💙 I will keep using that.')
            : $reply("Thanks for telling me. I will follow {$name}'s new pattern from the logs, so keep tapping or telling me as it happens.", 'thinking');
    }
    if ($pend['type'] === 'slip' && in_array($b, [RRP_SLIP_FINE, RRP_SLIP_FORGOT, RRP_SLIP_OFF], true)) {
        rrp_learn_clear($oid, $pid);
        if ($b === RRP_SLIP_FINE) {
            return $reply('Good to hear 💙 Thank you for checking.');
        }
        if ($b === RRP_SLIP_FORGOT) {
            $ctx['links'][] = ['url' => 'routines.php?pet=' . $pid, 'label' => 'Open the daily routines'];
            return $reply('No worries! You can tap it in the daily routines, or just tell me when it happens.');
        }
        return $reply("Thank you for telling me. If {$name} seems in pain, is struggling, or it has been more than a day, please call your vet. Tell me what you noticed and I will help you note it down.", 'concerned');
    }
    if ($pend['type'] === 'rem' && isset(RRP_REM_TOPICS[$kind]) && in_array($b, [RRP_REM_YES, RRP_REM_LESS, RRP_REM_MORE, RRP_REM_NO], true)) {
        rrp_learn_clear($oid, $pid);
        if ($b === RRP_REM_NO) {
            rrp_learn_set($oid, $pid, 'rem_' . $kind, 'declined');
            return $reply('No problem at all. If you change your mind, just ask me.');
        }
        $days = rrp_rem_interval($owner, $pet, $kind);
        $days = $b === RRP_REM_LESS ? $days * 2 : ($b === RRP_REM_MORE ? max(1, intdiv($days, 2)) : $days);
        $hour = rrp_sipo_preferred_hours($oid)[0] ?? 9;
        $start = rrp_sipo_local_now($owner)->modify('+' . $days . ' days')->format('Y-m-d') . sprintf(' %02d:00', $hour);
        $key = rrp_care_species($pet) . '_' . RRP_REM_TOPICS[$kind][0];
        $r = rrp_care_plan($owner, $pet, $key, $days, $start);
        if (empty($r['ok'])) {
            return $reply('I could not set that one up just now. Tell me and I will try again.', 'thinking');
        }
        rrp_learn_set($oid, $pid, 'rem_' . $kind, 'done');
        $ctx['events'][] = 'reminder_set';
        return $reply('Done ⏰ I will remind you every ' . rrp_days_phrase($days) . ", first on {$r['next_due']} around " . sprintf('%02d:00', $hour) . '. I will adjust the time to when you usually tick things off.');
    }
    return null;
}

// ---- cron -----------------------------------------------------------------------------------------

/**
 * Every 15 minutes: at most one question per owner, never inside the 3-hour gap, quiet hours or
 * daily cap (the same rules as every SIPO check-in), and never while an earlier one waits.
 * @return array{checked:int, sent:int}
 */
function rrp_learn_tick(int $max = 40): array
{
    $out = ['checked' => 0, 'sent' => 0];
    $owners = rrp_db()->query(
        "SELECT DISTINCT o.* FROM owners o JOIN chat_threads t ON t.owner_id = o.id AND t.thread_kind = 'si' AND t.pet_id IS NOT NULL
         WHERE o.status = 'active' AND o.onboarded_at IS NOT NULL ORDER BY o.id"
    )->fetchAll();
    foreach ($owners as $owner) {
        if ($out['sent'] >= $max) {
            break;
        }
        $out['checked']++;
        if (!rrp_sipo_nudge_allowed($owner, rrp_sipo_local_now($owner))['ok']) {
            continue;
        }
        $pick = rrp_sipo_pick_nudge((int) $owner['id']);
        $pet = $pick ? rrp_pet_by_id((int) $pick['thread']['pet_id'], (int) $owner['id']) : null;
        if (!$pet) {
            continue;
        }
        $pid = (int) $pet['id'];
        $pend = rrp_learn_pending($pid);
        if ($pend) {
            if ($pend['at'] > time() - 86400) {
                continue; // still waiting for an answer: do not pile up
            }
            rrp_learn_set((int) $owner['id'], $pid, ($pend['type'] === 'slip' ? 'slip_' : ($pend['type'] === 'rem' ? 'rem_' : ($pend['type'] === 'verify' ? 'verify_' : 'learn_'))) . $pend['topic'], 'skipped');
            rrp_learn_clear((int) $owner['id'], $pid);
        }
        $q = rrp_learn_next($owner, $pet);
        if (!$q) {
            continue;
        }
        $prefix = ['slip' => 'slip_', 'rem' => 'rem_', 'verify' => 'verify_', 'learn' => 'learn_'][$q['type']];
        rrp_learn_set((int) $owner['id'], $pid, $prefix . $q['topic'], 'asked');
        rrp_learn_set((int) $owner['id'], $pid, 'learn_pending', $q['type'] . ':' . $q['topic']);
        $msg = rrp_sipo_insert_message((int) $pick['thread']['id'], 'assistant', $q['text'], 0, ['mascot' => $q['mascot'], 'proactive' => 'learn', 'choices' => $q['choices']]);
        rrp_try_web_push((int) $owner['id'], 'SIPO · ' . $pet['name'], mb_substr($q['text'], 0, 140), rrp_app_url('si-chat.php?pet=' . $pid));
        rrp_sipo_log_nudge($owner, $pid, (int) $pick['thread']['id'], (int) ($msg['id'] ?? 0), 'learn', true);
        $out['sent']++;
    }
    return $out;
}

/** A small emoji for a reminder title, so the health list is easy to scan. */
function rrp_routine_icon(string $title): string
{
    $t = mb_strtolower($title);
    foreach ([
        'vaccin' => '💉', 'rabies' => '💉', 'nail' => '✂️', 'bath' => '🛁', 'teeth' => '🪥', 'tooth' => '🪥', 'flea' => '🐜', 'tick' => '🐜',
        'worm' => '🪱', 'heartworm' => '🪱', 'blood' => '🩸', 'urine' => '🩸', 'check' => '🩺', 'vet' => '🩺', 'ear' => '👂', 'coat' => '🪮',
        'brush' => '🪮', 'walk' => '🦮', 'food' => '🍽️', 'meal' => '🍽️', 'med' => '💊', 'pill' => '💊', 'drop' => '💧', 'water' => '💧',
    ] as $needle => $icon) {
        if (str_contains($t, $needle)) {
            return $icon;
        }
    }
    return '⏰';
}
