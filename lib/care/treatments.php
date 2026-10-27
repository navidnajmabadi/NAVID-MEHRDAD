<?php
declare(strict_types=1);

/**
 * Pets under vet treatment (Navid 2026-10-28).
 * SIPO records the vet's instructions verbatim and never invents or changes a dose.
 */

/** @return list<array> active first, newest first */
function rrp_treatments_for_pet(int $petId, bool $activeOnly = false): array
{
    try {
        $sql = 'SELECT * FROM vet_treatments WHERE pet_id = ?' . ($activeOnly ? " AND status = 'active'" : '')
            . " ORDER BY status = 'active' DESC, started_on DESC LIMIT 20";
        $stmt = rrp_db()->prepare($sql);
        $stmt->execute([$petId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    foreach ($rows as &$t) {
        $t['meds'] = rrp_treatment_meds((int) $t['id']);
        $t['logs'] = rrp_treatment_logs((int) $t['id'], 14);
        $t['rechecks'] = rrp_treatment_rechecks((int) $t['id']);
    }
    return $rows;
}

function rrp_treatment_meds(int $treatmentId): array
{
    $stmt = rrp_db()->prepare('SELECT id, name, dose_note, schedule_time, ends_on, active FROM medication_schedules WHERE treatment_id = ? ORDER BY schedule_time');
    $stmt->execute([$treatmentId]);
    return $stmt->fetchAll();
}

function rrp_treatment_logs(int $treatmentId, int $days): array
{
    $stmt = rrp_db()->prepare('SELECT log_date, trend, med_given, note FROM treatment_logs WHERE treatment_id = ? ORDER BY log_date DESC LIMIT ' . max(1, $days));
    $stmt->execute([$treatmentId]);
    return array_reverse($stmt->fetchAll());
}

function rrp_treatment_rechecks(int $treatmentId): array
{
    $stmt = rrp_db()->prepare('SELECT id, title, clinic_name, appointment_at FROM vet_appointments WHERE treatment_id = ? ORDER BY appointment_at');
    $stmt->execute([$treatmentId]);
    return $stmt->fetchAll();
}

/** @return array{ok:bool, error?:string, id?:int} */
function rrp_treatment_start(array $owner, array $pet, array $d): array
{
    $condition = trim((string) ($d['condition'] ?? ''));
    if ($condition === '') {
        return ['ok' => false, 'error' => 'What is being treated?'];
    }
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['started_on'] ?? '')) ? $d['started_on'] : $today;
    $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['planned_end_on'] ?? '')) ? $d['planned_end_on'] : null;
    $stmt = rrp_db()->prepare(
        'INSERT INTO vet_treatments (pet_id, owner_id, condition_name, vet_name, started_on, planned_end_on, instructions)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $pet['id'], (int) $owner['id'], mb_substr($condition, 0, 160),
        trim((string) ($d['vet_name'] ?? '')) ?: null, $start, $end,
        trim((string) ($d['instructions'] ?? '')) ?: null,
    ]);
    $id = (int) rrp_db()->lastInsertId();
    // Keep the pet card's condition list in sync.
    $existing = trim((string) ($pet['medical_conditions'] ?? ''));
    if (stripos($existing, $condition) === false) {
        rrp_db()->prepare('UPDATE pets SET medical_conditions = ? WHERE id = ?')
            ->execute([ltrim($existing . ', ' . $condition, ', '), (int) $pet['id']]);
    }
    return ['ok' => true, 'id' => $id];
}

function rrp_treatment_get(array $pet, int $id): ?array
{
    $stmt = rrp_db()->prepare('SELECT * FROM vet_treatments WHERE id = ? AND pet_id = ? LIMIT 1');
    $stmt->execute([$id, (int) $pet['id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Add a prescribed medicine. dose_note must be the vet's own words.
 * @return array{ok:bool, error?:string, id?:int}
 */
function rrp_treatment_add_med(array $owner, array $pet, int $treatmentId, array $d): array
{
    if (!rrp_treatment_get($pet, $treatmentId)) {
        return ['ok' => false, 'error' => 'Treatment not found.'];
    }
    $times = array_filter(array_map('trim', (array) ($d['times'] ?? [])));
    if (!$times) {
        return ['ok' => false, 'error' => 'At least one time (HH:MM) is needed.'];
    }
    $ids = [];
    foreach (array_slice($times, 0, 4) as $t) {
        $res = rrp_med_add((int) $pet['id'], [
            'name' => (string) ($d['name'] ?? ''),
            'dose_note' => (string) ($d['dose_as_prescribed'] ?? ''),
            'schedule_time' => $t,
            'ends_on' => $d['ends_on'] ?? null,
            'treatment_id' => $treatmentId,
            'kind' => 'medicine',
        ]);
        if (empty($res['ok'])) {
            return $res;
        }
        $ids[] = $res['id'];
    }
    return ['ok' => true, 'ids' => $ids];
}

/** @return array{ok:bool, error?:string} */
function rrp_treatment_log(array $owner, array $pet, int $treatmentId, string $trend, ?bool $medGiven, ?string $note): array
{
    if (!in_array($trend, ['better', 'same', 'worse'], true)) {
        return ['ok' => false, 'error' => 'trend must be better, same or worse.'];
    }
    if (!rrp_treatment_get($pet, $treatmentId)) {
        return ['ok' => false, 'error' => 'Treatment not found.'];
    }
    $day = rrp_sipo_local_now($owner)->format('Y-m-d');
    rrp_db()->prepare(
        'INSERT INTO treatment_logs (treatment_id, log_date, trend, med_given, note) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE trend = VALUES(trend), med_given = COALESCE(VALUES(med_given), med_given),
                                 note = COALESCE(VALUES(note), note)'
    )->execute([$treatmentId, $day, $trend, $medGiven === null ? null : ($medGiven ? 1 : 0), $note ? mb_substr($note, 0, 500) : null]);
    return ['ok' => true, 'worse_streak' => rrp_treatment_worse_streak($treatmentId)];
}

/** Consecutive "worse" days ending today — SIPO suggests calling the vet at 2+. */
function rrp_treatment_worse_streak(int $treatmentId): int
{
    $logs = array_reverse(rrp_treatment_logs($treatmentId, 7));
    $n = 0;
    foreach ($logs as $l) {
        if ($l['trend'] !== 'worse') {
            break;
        }
        $n++;
    }
    return $n;
}

/** @return array{ok:bool, error?:string} */
function rrp_treatment_finish(array $owner, array $pet, int $treatmentId, ?string $note = null): array
{
    if (!rrp_treatment_get($pet, $treatmentId)) {
        return ['ok' => false, 'error' => 'Treatment not found.'];
    }
    $day = rrp_sipo_local_now($owner)->format('Y-m-d');
    rrp_db()->prepare("UPDATE vet_treatments SET status = 'finished', finished_on = ?, instructions = CONCAT(COALESCE(instructions,''), ?) WHERE id = ?")
        ->execute([$day, $note ? "\nFinished: " . $note : '', $treatmentId]);
    rrp_db()->prepare('UPDATE medication_schedules SET active = 0 WHERE treatment_id = ?')->execute([$treatmentId]);
    return ['ok' => true];
}

/** @return array{ok:bool, error?:string, id?:int} */
function rrp_treatment_add_recheck(array $owner, array $pet, int $treatmentId, string $when, ?string $clinic): array
{
    $t = rrp_treatment_get($pet, $treatmentId);
    if (!$t) {
        return ['ok' => false, 'error' => 'Treatment not found.'];
    }
    $utc = rrp_sipo_local_to_utc($when, $owner);
    if ($utc === null) {
        return ['ok' => false, 'error' => 'Use YYYY-MM-DD or YYYY-MM-DD HH:MM.'];
    }
    rrp_db()->prepare('INSERT INTO vet_appointments (pet_id, treatment_id, title, clinic_name, appointment_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int) $pet['id'], $treatmentId, 'Recheck: ' . $t['condition_name'], $clinic ?: $t['vet_name'], $utc]);
    return ['ok' => true, 'id' => (int) rrp_db()->lastInsertId()];
}

/** Compact text for the SIPO prompt. */
function rrp_treatments_prompt_block(int $petId): ?string
{
    $rows = rrp_treatments_for_pet($petId, true);
    if (!$rows) {
        return null;
    }
    $out = [];
    foreach ($rows as $t) {
        $line = "- [treatment_id {$t['id']}] {$t['condition_name']} since {$t['started_on']}"
            . ($t['planned_end_on'] ? " until {$t['planned_end_on']}" : '')
            . ($t['vet_name'] ? " · vet: {$t['vet_name']}" : '');
        if ($t['instructions']) {
            $line .= "\n  Vet instructions (verbatim): " . mb_substr((string) $t['instructions'], 0, 400);
        }
        foreach ($t['meds'] as $m) {
            if ((int) $m['active'] === 1) {
                $line .= "\n  Medicine: {$m['name']} at " . substr((string) $m['schedule_time'], 0, 5)
                    . ($m['dose_note'] ? " — as prescribed: {$m['dose_note']}" : '')
                    . ($m['ends_on'] ? " (until {$m['ends_on']})" : '');
            }
        }
        $last = end($t['logs']) ?: null;
        if ($last) {
            $line .= "\n  Last progress {$last['log_date']}: {$last['trend']}" . ($last['note'] ? " ({$last['note']})" : '');
        }
        $streak = rrp_treatment_worse_streak((int) $t['id']);
        if ($streak >= 2) {
            $line .= "\n  ⚠ Worse {$streak} days in a row — suggest calling the vet.";
        }
        $out[] = $line;
    }
    return implode("\n", $out);
}
