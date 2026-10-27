<?php
declare(strict_types=1);

/**
 * Care routines for one pet: region → catalogue → status (last done, next due),
 * recording what happened, and follow-ups that chase an outcome (brief §A1).
 * One care_reminders row per due cycle (kind='routine', routine_key=catalogue key).
 */

const RRP_CARE_DUE_SOON_DAYS = 14;

/**
 * Owner's country as ISO-2, from: owner country (map/geocode) → Cloudflare header →
 * the country of their browser timezone. Null when nothing is known.
 */
function rrp_care_owner_country(array $owner): ?string
{
    $c = trim((string) ($owner['country'] ?? ''));
    if (preg_match('/^[A-Za-z]{2}$/', $c)) {
        return strtoupper($c);
    }
    if ($c !== '') {
        $byName = [
            'canada' => 'CA', 'united states' => 'US', 'united states of america' => 'US', 'usa' => 'US',
            'united kingdom' => 'GB', 'uk' => 'GB', 'ireland' => 'IE', 'españa' => 'ES', 'spain' => 'ES',
            'france' => 'FR', 'deutschland' => 'DE', 'germany' => 'DE', 'italia' => 'IT', 'italy' => 'IT',
            'nederland' => 'NL', 'netherlands' => 'NL', 'portugal' => 'PT', 'sverige' => 'SE', 'sweden' => 'SE',
            'norge' => 'NO', 'norway' => 'NO', 'danmark' => 'DK', 'denmark' => 'DK', 'schweiz' => 'CH',
            'switzerland' => 'CH', 'österreich' => 'AT', 'austria' => 'AT', 'belgique' => 'BE', 'belgium' => 'BE',
            'polska' => 'PL', 'poland' => 'PL', 'ελλάς' => 'GR', 'greece' => 'GR', 'iran' => 'IR', 'australia' => 'AU',
        ];
        $hit = $byName[mb_strtolower($c)] ?? null;
        if ($hit) {
            return $hit;
        }
    }
    $cf = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
        return $cf;
    }
    try {
        $loc = rrp_sipo_owner_tz($owner)->getLocation();
        $cc = strtoupper((string) ($loc['country_code'] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $cc) && $cc !== '??') {
            return $cc;
        }
    } catch (Throwable $e) {
    }
    return null;
}

function rrp_care_region(array $owner, array $pet): string
{
    if (!empty($pet['care_region'])) {
        return (string) $pet['care_region'];
    }
    $region = rrp_care_region_for_country(rrp_care_owner_country($owner));
    try {
        rrp_db()->prepare('UPDATE pets SET care_region = ? WHERE id = ?')->execute([$region, (int) $pet['id']]);
    } catch (Throwable $e) {
    }
    return $region;
}

function rrp_care_species(array $pet): string
{
    $t = (string) ($pet['type'] ?? '');
    return in_array($t, ['dog', 'cat'], true) ? $t : 'other';
}

/**
 * Routine table for the pet with what we know.
 * @return list<array{key:string,title:string,group:string,when:string,note:string,ask:string,interval_days:int,
 *   last_done:?string,next_due:?string,state:string,reminder_id:?int,sources:list<array>}>
 */
function rrp_care_status(array $owner, array $pet): array
{
    $region = rrp_care_region($owner, $pet);
    $catalog = rrp_care_catalog(rrp_care_species($pet), $region, $pet['lifestyle'] ?? null);
    $rows = [];
    try {
        $stmt = rrp_db()->prepare(
            "SELECT id, routine_key, status, due_at, completed_at FROM care_reminders
             WHERE pet_id = ? AND kind = 'routine' ORDER BY id DESC"
        );
        $stmt->execute([(int) $pet['id']]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[(string) $r['routine_key']][] = $r;
        }
    } catch (Throwable $e) {
    }

    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    $soon = rrp_sipo_local_now($owner)->modify('+' . RRP_CARE_DUE_SOON_DAYS . ' days')->format('Y-m-d');
    $out = [];
    foreach ($catalog as $item) {
        $hist = $rows[$item['key']] ?? [];
        $lastDone = null;
        $open = null;
        foreach ($hist as $h) {
            if ($h['status'] === 'completed' && $h['completed_at'] && ($lastDone === null || $h['completed_at'] > $lastDone)) {
                $lastDone = substr((string) $h['completed_at'], 0, 10);
            }
            if (in_array($h['status'], ['scheduled', 'sent', 'snoozed', 'pending_approval'], true) && $open === null) {
                $open = $h;
            }
        }
        $next = $open ? substr((string) $open['due_at'], 0, 10) : null;
        $state = 'unknown';
        if ($next !== null) {
            $state = $next < $today ? 'overdue' : ($next <= $soon ? 'due_soon' : 'ok');
        } elseif ($lastDone !== null) {
            $state = 'ok';
        }
        $out[] = $item + [
            'last_done' => $lastDone,
            'next_due' => $next,
            'state' => $state,
            'reminder_id' => $open ? (int) $open['id'] : null,
            'sources' => rrp_care_sources($item['sources']),
        ];
    }
    return $out;
}

/**
 * Owner says a routine was done on a date (today or in the past).
 * Closes the open cycle, opens the next one, mirrors vaccines into vaccination_records.
 * @return array{ok:bool, error?:string, next_due?:string, title?:string}
 */
function rrp_care_record_done(array $owner, array $pet, string $key, string $date, ?string $note = null, ?int $intervalDays = null): array
{
    $item = null;
    foreach (rrp_care_catalog(rrp_care_species($pet), rrp_care_region($owner, $pet), $pet['lifestyle'] ?? null) as $c) {
        if ($c['key'] === $key) {
            $item = $c;
        }
    }
    if (!$item) {
        return ['ok' => false, 'error' => 'Unknown routine ' . $key . '. Use a key from the care routines list.'];
    }
    if (!preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $date)) {
        return ['ok' => false, 'error' => 'Date must be YYYY-MM-DD (YYYY-MM if the day is unknown).'];
    }
    if (strlen($date) === 7) {
        $date .= '-01';
    }
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    if ($date > $today) {
        return ['ok' => false, 'error' => 'That date is in the future — use set_reminder for upcoming visits.'];
    }
    if ($intervalDays === null) {
        foreach (rrp_sipo_facts((int) $pet['id']) as $f) {
            if ($f['fact_key'] === 'interval_' . $key && ctype_digit($f['fact_value'])) {
                $intervalDays = (int) $f['fact_value'];
            }
        }
    }
    $interval = $intervalDays !== null && $intervalDays >= 1 && $intervalDays <= 1200 ? $intervalDays : $item['interval_days'];
    $next = (new DateTimeImmutable($date))->modify('+' . $interval . ' days');
    $nextUtc = rrp_sipo_local_to_utc($next->format('Y-m-d'), $owner) ?? $next->format('Y-m-d 09:00:00');
    $petId = (int) $pet['id'];

    try {
        $pdo = rrp_db();
        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE care_reminders SET status = 'completed', completed_at = ?, outcome = 'done', outcome_note = ?
             WHERE pet_id = ? AND kind = 'routine' AND routine_key = ? AND status IN ('scheduled','sent','snoozed','pending_approval')"
        )->execute([$date . ' 12:00:00', $note, $petId, $key]);
        // Past event with no open cycle → keep a completed row as history.
        $pdo->prepare(
            "INSERT INTO care_reminders (pet_id, kind, routine_key, title, due_at, status, completed_at, outcome, outcome_note, merge_group)
             SELECT ?, 'routine', ?, ?, ?, 'completed', ?, 'done', ?, ? FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM care_reminders WHERE pet_id = ? AND routine_key = ? AND status = 'completed' AND DATE(completed_at) = ?)"
        )->execute([$petId, $key, $item['title'], $date . ' 12:00:00', $date . ' 12:00:00', $note, $item['group'], $petId, $key, $date]);
        $pdo->prepare(
            "INSERT INTO care_reminders (pet_id, kind, routine_key, title, due_at, status, merge_group, approved_at)
             VALUES (?, 'routine', ?, ?, ?, 'scheduled', ?, UTC_TIMESTAMP())"
        )->execute([$petId, $key, $item['title'], $nextUtc, $item['group']]);
        if ($item['group'] === 'vaccine') {
            $pdo->prepare('INSERT INTO vaccination_records (pet_id, name, given_on, next_due, notes) VALUES (?, ?, ?, ?, ?)')
                ->execute([$petId, $item['title'], $date, $next->format('Y-m-d'), $note]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if (rrp_db()->inTransaction()) {
            rrp_db()->rollBack();
        }
        error_log('rrp_care_record_done: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save.'];
    }
    $coinKey = ['vaccine' => 'routine_vaccine', 'parasite' => 'routine_parasite', 'checkup' => 'routine_checkup', 'hygiene' => 'routine_hygiene'][$item['group']] ?? null;
    $coins = $coinKey ? rrp_coin_award((int) $owner['id'], $coinKey, $item['title'] . ' for ' . $pet['name'], 'routine:' . $key, (int) str_replace('-', '', $date), $petId) : 0;
    return ['ok' => true, 'title' => $item['title'], 'next_due' => $next->format('Y-m-d'), 'coins' => $coins];
}

/**
 * Start a routine from a date (no "last done" needed), e.g. "brush teeth every 2 days from today".
 * @return array{ok:bool, error?:string, next_due?:string}
 */
function rrp_care_plan(array $owner, array $pet, string $key, int $intervalDays, ?string $startLocal = null): array
{
    $item = null;
    foreach (rrp_care_catalog(rrp_care_species($pet), rrp_care_region($owner, $pet), $pet['lifestyle'] ?? null) as $c) {
        if ($c['key'] === $key) {
            $item = $c;
        }
    }
    if (!$item) {
        return ['ok' => false, 'error' => 'Unknown routine ' . $key . '.'];
    }
    if ($intervalDays < 1 || $intervalDays > 1200) {
        return ['ok' => false, 'error' => 'Interval must be 1–1200 days.'];
    }
    $start = $startLocal && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $startLocal) ? $startLocal : rrp_sipo_local_now($owner)->format('Y-m-d');
    $due = rrp_sipo_local_to_utc($start, $owner);
    if ($due === null) {
        return ['ok' => false, 'error' => 'Bad start date.'];
    }
    rrp_db()->prepare(
        "UPDATE care_reminders SET status = 'dismissed', outcome = 'rescheduled'
         WHERE pet_id = ? AND kind = 'routine' AND routine_key = ? AND status IN ('scheduled','sent','snoozed','pending_approval')"
    )->execute([(int) $pet['id'], $key]);
    rrp_db()->prepare(
        "INSERT INTO care_reminders (pet_id, kind, routine_key, title, due_at, status, merge_group, approved_at, outcome_note)
         VALUES (?, 'routine', ?, ?, ?, 'scheduled', ?, UTC_TIMESTAMP(), ?)"
    )->execute([(int) $pet['id'], $key, $item['title'], $due, $item['group'], 'every ' . $intervalDays . ' days']);
    // Remember the owner's rhythm so "done" books the next one at the same interval.
    rrp_sipo_save_fact((int) $owner['id'], (int) $pet['id'], 'interval_' . $key, (string) $intervalDays, 'routine');
    return ['ok' => true, 'next_due' => substr($start, 0, 10)];
}

/**
 * Close any reminder (routine or custom) with an outcome the owner reported.
 * @return array{ok:bool, error?:string, next_due?:string}
 */
function rrp_care_reminder_outcome(array $owner, array $pet, int $reminderId, string $outcome, ?string $newDate = null, ?string $note = null): array
{
    $stmt = rrp_db()->prepare('SELECT * FROM care_reminders WHERE id = ? AND pet_id = ? LIMIT 1');
    $stmt->execute([$reminderId, (int) $pet['id']]);
    $r = $stmt->fetch();
    if (!$r) {
        return ['ok' => false, 'error' => 'Reminder not found for this pet.'];
    }
    if ($outcome === 'done' && $r['kind'] === 'routine' && $r['routine_key']) {
        $date = $newDate ?: rrp_sipo_local_now($owner)->format('Y-m-d');
        return rrp_care_record_done($owner, $pet, (string) $r['routine_key'], $date, $note);
    }
    if (in_array($outcome, ['rescheduled', 'vet_booked'], true)) {
        $due = $newDate ? rrp_sipo_local_to_utc($newDate, $owner) : null;
        if ($due === null) {
            return ['ok' => false, 'error' => 'New date needed (YYYY-MM-DD or YYYY-MM-DD HH:MM).'];
        }
        rrp_db()->prepare(
            "UPDATE care_reminders SET due_at = ?, status = 'scheduled', followup_stage = 0, last_followup_at = NULL,
             outcome = ?, outcome_note = ? WHERE id = ?"
        )->execute([$due, $outcome, $note, $reminderId]);
        return ['ok' => true, 'next_due' => substr($due, 0, 10)];
    }
    $status = $outcome === 'done' ? 'completed' : 'dismissed';
    rrp_db()->prepare(
        'UPDATE care_reminders SET status = ?, completed_at = IF(? = \'completed\', UTC_TIMESTAMP(), completed_at), outcome = ?, outcome_note = ? WHERE id = ?'
    )->execute([$status, $status, in_array($outcome, ['done', 'skipped'], true) ? $outcome : 'skipped', $note, $reminderId]);
    return ['ok' => true];
}

/** Open reminders for SIPO (id + title + due) so it can close them by id. @return list<array> */
function rrp_care_open_reminders(int $petId, int $limit = 8): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT id, title, kind, routine_key, due_at, followup_stage FROM care_reminders
             WHERE pet_id = ? AND status IN ('scheduled','sent','snoozed','pending_approval')
             ORDER BY due_at ASC LIMIT {$limit}"
        );
        $stmt->execute([$petId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// -----------------------------------------------------------------------------
// Follow-ups: tomorrow → today → "did you go?" (cron, quiet hours respected)
// -----------------------------------------------------------------------------

/**
 * @return array{text:string, stage:int}|null  next follow-up for this reminder now, if any
 */
function rrp_care_followup_due(array $r, array $owner, array $pet, int $now): ?array
{
    $due = strtotime((string) $r['due_at'] . ' UTC');
    $stage = (int) $r['followup_stage'];
    $last = $r['last_followup_at'] ? strtotime((string) $r['last_followup_at'] . ' UTC') : 0;
    $name = (string) $pet['name'];
    $title = (string) $r['title'];
    $localDue = (new DateTimeImmutable('@' . $due))->setTimezone(rrp_sipo_owner_tz($owner));
    $when = $localDue->format('G') !== '9' || $localDue->format('i') !== '00' ? ' at ' . $localDue->format('H:i') : '';

    // Owner's own reminders ("remind me in 10 min to walk Violet"): one ping at the time, then done.
    if (($r['kind'] ?? '') === 'custom' && ($r['merge_group'] ?? '') === '') {
        if ($stage < 2 && $now >= $due - 90 && $now - $due < 6 * 3600) {
            return ['stage' => 5, 'text' => "⏰ Reminder: {$title}"];
        }
        if ($stage < 2 && $now - $due >= 6 * 3600) {
            return ['stage' => 5, 'text' => "⏰ Earlier reminder I couldn't deliver on time: {$title}"];
        }
        return null;
    }
    // Grooming / teeth / baths: one gentle nudge on the day, one check the next day, then stop.
    if (($r['merge_group'] ?? '') === 'hygiene') {
        if ($stage < 2 && $due - $now <= 3 * 3600 && $now - $due < 18 * 3600) {
            return ['stage' => 2, 'text' => "Little care moment: {$title} for {$name} today?"];
        }
        if ($stage < 5 && $now - $due >= 20 * 3600) {
            return ['stage' => 5, 'text' => "Did {$name}'s {$title} happen? Just say \"done\" — or tell me a better rhythm and I'll adjust."];
        }
        return null;
    }
    if ($stage === 0 && $due - $now <= 30 * 3600 && $due - $now > 6 * 3600) {
        return ['stage' => 1, 'text' => "Heads up: {$name}'s {$title} is tomorrow{$when}. Is it booked? I can find a vet nearby if you need one."];
    }
    if ($stage <= 1 && $due - $now <= 6 * 3600 && $now - $due < 18 * 3600) {
        return ['stage' => 2, 'text' => "Today is {$name}'s {$title}{$when}. Tell me once it's done and I'll set the next one 💙"];
    }
    $after = [3 => 20 * 3600, 4 => 3 * 86400, 5 => 6 * 86400];
    foreach ($after as $s => $delay) {
        if ($stage < $s && $now - $due >= $delay && $now - $last >= 18 * 3600) {
            $text = $s === 3
                ? "Did you manage to do {$name}'s {$title}? Just say \"done\", give me a new date, or ask me to find a vet."
                : "Still on my list: {$name}'s {$title}. Has it happened? If it's hard to fit in, I can help you pick a date.";
            return ['stage' => $s, 'text' => $text];
        }
    }
    return null;
}

/** Cron worker: push + chat message per due follow-up. */
function rrp_care_followup_tick(int $limit = 60): array
{
    $stats = ['checked' => 0, 'sent' => 0, 'quiet' => 0];
    $rows = rrp_db()->query(
        "SELECT r.*, p.owner_id FROM care_reminders r JOIN pets p ON p.id = r.pet_id AND p.is_active = 1
         WHERE r.status IN ('scheduled','sent') AND r.followup_stage < 5
           AND r.due_at <= (UTC_TIMESTAMP() + INTERVAL 30 HOUR)
         ORDER BY r.due_at ASC LIMIT " . (int) $limit
    )->fetchAll();
    $now = time();
    foreach ($rows as $r) {
        $stats['checked']++;
        $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . (int) $r['owner_id'])->fetch();
        $pet = $owner ? rrp_pet_by_id((int) $r['pet_id'], (int) $owner['id']) : null;
        if (!$owner || !$pet || $owner['status'] !== 'active') {
            continue;
        }
        $f = rrp_care_followup_due($r, $owner, $pet, $now);
        if (!$f) {
            continue;
        }
        if (rrp_sipo_is_quiet_hour((int) rrp_sipo_local_now($owner)->format('G'))) {
            $stats['quiet']++;
            continue;
        }
        $thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $pet['id']);
        if ($thread) {
            rrp_sipo_insert_message((int) $thread['id'], 'assistant', $f['text'], 0, ['mascot' => 'listening', 'proactive' => 'followup', 'reminder_id' => (int) $r['id']]);
        }
        rrp_try_web_push((int) $owner['id'], 'SIPO · ' . $pet['name'], $f['text'], rrp_app_url('si-chat.php?pet=' . (int) $pet['id']));
        rrp_db()->prepare(
            "UPDATE care_reminders SET followup_stage = ?, last_followup_at = UTC_TIMESTAMP(),
             status = IF(? >= 2, 'sent', status) WHERE id = ?"
        )->execute([$f['stage'], $f['stage'], (int) $r['id']]);
        $stats['sent']++;
    }
    return $stats;
}
