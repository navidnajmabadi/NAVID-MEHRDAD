<?php
declare(strict_types=1);

/** Assert pet belongs to owner; returns pet or null. */
function rrp_health_pet_owned(int $petId, int $ownerId): ?array
{
    return rrp_pet_by_id($petId, $ownerId);
}

function rrp_health_next_remind_from_time(string $timeHhMmSs): string
{
    // Interpret schedule_time as UTC wall clock for MVP.
    $parts = explode(':', $timeHhMmSs);
    $h = (int) ($parts[0] ?? 0);
    $m = (int) ($parts[1] ?? 0);
    $s = (int) ($parts[2] ?? 0);
    $now = time();
    $today = gmmktime($h, $m, $s, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
    if ($today <= $now) {
        $today += 86400;
    }
    return gmdate('Y-m-d H:i:s', $today);
}

function rrp_normalize_time_input(string $raw): ?string
{
    $raw = trim($raw);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
        $h = (int) $m[1];
        $min = (int) $m[2];
        $s = isset($m[3]) ? (int) $m[3] : 0;
        if ($h > 23 || $min > 59 || $s > 59) {
            return null;
        }
        return sprintf('%02d:%02d:%02d', $h, $min, $s);
    }
    return null;
}

// —— Vaccines ——

function rrp_vax_list(int $petId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM vaccination_records WHERE pet_id = ? ORDER BY COALESCE(next_due, given_on) DESC, id DESC'
    );
    $stmt->execute([$petId]);
    return $stmt->fetchAll();
}

function rrp_vax_add(int $petId, array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'error' => 'Vaccine name is required.'];
    }
    $given = trim((string) ($data['given_on'] ?? ''));
    $due = trim((string) ($data['next_due'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $stmt = rrp_db()->prepare(
        'INSERT INTO vaccination_records (pet_id, name, given_on, next_due, notes)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $petId,
        mb_substr($name, 0, 120),
        $given !== '' ? $given : null,
        $due !== '' ? $due : null,
        $notes !== '' ? mb_substr($notes, 0, 500) : null,
    ]);
    return ['ok' => true, 'id' => (int) rrp_db()->lastInsertId()];
}

function rrp_vax_delete(int $petId, int $id): array
{
    $stmt = rrp_db()->prepare('DELETE FROM vaccination_records WHERE id = ? AND pet_id = ?');
    $stmt->execute([$id, $petId]);
    return ['ok' => $stmt->rowCount() > 0];
}

// —— Meds ——

function rrp_meds_list(int $petId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM medication_schedules WHERE pet_id = ? ORDER BY active DESC, schedule_time ASC, id ASC'
    );
    $stmt->execute([$petId]);
    return $stmt->fetchAll();
}

function rrp_med_add(int $petId, array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $time = rrp_normalize_time_input((string) ($data['schedule_time'] ?? ''));
    if ($name === '' || $time === null) {
        return ['ok' => false, 'error' => 'Name and time (HH:MM) are required.'];
    }
    $dose = trim((string) ($data['dose_note'] ?? ''));
    $next = rrp_health_next_remind_from_time($time);
    $stmt = rrp_db()->prepare(
        'INSERT INTO medication_schedules (pet_id, name, dose_note, schedule_time, active, next_remind_at)
         VALUES (?, ?, ?, ?, 1, ?)'
    );
    $stmt->execute([
        $petId,
        mb_substr($name, 0, 120),
        $dose !== '' ? mb_substr($dose, 0, 255) : null,
        $time,
        $next,
    ]);
    return ['ok' => true, 'id' => (int) rrp_db()->lastInsertId()];
}

function rrp_med_delete(int $petId, int $id): array
{
    $stmt = rrp_db()->prepare('DELETE FROM medication_schedules WHERE id = ? AND pet_id = ?');
    $stmt->execute([$id, $petId]);
    return ['ok' => $stmt->rowCount() > 0];
}

function rrp_med_toggle(int $petId, int $id, bool $active): array
{
    $stmt = rrp_db()->prepare(
        'UPDATE medication_schedules SET active = ? WHERE id = ? AND pet_id = ?'
    );
    $stmt->execute([$active ? 1 : 0, $id, $petId]);
    return ['ok' => $stmt->rowCount() > 0];
}

// —— Feeding ——

function rrp_feed_list(int $petId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM feeding_schedules WHERE pet_id = ? ORDER BY sort_order ASC, feed_time ASC, id ASC'
    );
    $stmt->execute([$petId]);
    return $stmt->fetchAll();
}

function rrp_feed_add(int $petId, array $data): array
{
    $label = trim((string) ($data['label'] ?? ''));
    $time = rrp_normalize_time_input((string) ($data['feed_time'] ?? ''));
    if ($label === '' || $time === null) {
        return ['ok' => false, 'error' => 'Meal name and time are required.'];
    }
    $portion = trim((string) ($data['portion_note'] ?? ''));
    $stmt = rrp_db()->prepare(
        'INSERT INTO feeding_schedules (pet_id, label, feed_time, portion_note, active)
         VALUES (?, ?, ?, ?, 1)'
    );
    $stmt->execute([
        $petId,
        mb_substr($label, 0, 80),
        $time,
        $portion !== '' ? mb_substr($portion, 0, 255) : null,
    ]);
    return ['ok' => true, 'id' => (int) rrp_db()->lastInsertId()];
}

function rrp_feed_delete(int $petId, int $id): array
{
    $stmt = rrp_db()->prepare('DELETE FROM feeding_schedules WHERE id = ? AND pet_id = ?');
    $stmt->execute([$id, $petId]);
    return ['ok' => $stmt->rowCount() > 0];
}

function rrp_feed_mark(int $petId, int $scheduleId, ?string $day = null): array
{
    $day = $day ?: gmdate('Y-m-d');
    $check = rrp_db()->prepare(
        'SELECT id FROM feeding_schedules WHERE id = ? AND pet_id = ? LIMIT 1'
    );
    $check->execute([$scheduleId, $petId]);
    if (!$check->fetch()) {
        return ['ok' => false, 'error' => 'Meal not found.'];
    }
    try {
        $ins = rrp_db()->prepare(
            'INSERT INTO feeding_logs (feeding_schedule_id, pet_id, fed_on) VALUES (?, ?, ?)'
        );
        $ins->execute([$scheduleId, $petId, $day]);
    } catch (PDOException $e) {
        // Already marked today — treat as ok.
        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
            return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not mark fed')];
        }
    }
    return ['ok' => true];
}

function rrp_feed_unmark(int $petId, int $scheduleId, ?string $day = null): array
{
    $day = $day ?: gmdate('Y-m-d');
    $stmt = rrp_db()->prepare(
        'DELETE FROM feeding_logs WHERE feeding_schedule_id = ? AND pet_id = ? AND fed_on = ?'
    );
    $stmt->execute([$scheduleId, $petId, $day]);
    return ['ok' => true];
}

/** @return array<int,true> schedule_id => true if fed today */
function rrp_feed_today_map(int $petId): array
{
    $day = gmdate('Y-m-d');
    $stmt = rrp_db()->prepare(
        'SELECT feeding_schedule_id FROM feeding_logs WHERE pet_id = ? AND fed_on = ?'
    );
    $stmt->execute([$petId, $day]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['feeding_schedule_id']] = true;
    }
    return $map;
}

// —— Vet appointments ——

function rrp_vet_list(int $petId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM vet_appointments WHERE pet_id = ? ORDER BY appointment_at ASC, id ASC'
    );
    $stmt->execute([$petId]);
    return $stmt->fetchAll();
}

function rrp_vet_add(int $petId, array $data): array
{
    $title = trim((string) ($data['title'] ?? ''));
    $when = trim((string) ($data['appointment_at'] ?? ''));
    if ($title === '' || $when === '') {
        return ['ok' => false, 'error' => 'Title and date/time are required.'];
    }
    // Accept "YYYY-MM-DDTHH:MM" or "YYYY-MM-DD HH:MM:SS"
    $when = str_replace('T', ' ', $when);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $when)) {
        $when .= ':00';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $when)) {
        return ['ok' => false, 'error' => 'Invalid appointment time.'];
    }
    $clinic = trim((string) ($data['clinic_name'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $stmt = rrp_db()->prepare(
        'INSERT INTO vet_appointments (pet_id, title, clinic_name, appointment_at, notes)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $petId,
        mb_substr($title, 0, 160),
        $clinic !== '' ? mb_substr($clinic, 0, 160) : null,
        $when,
        $notes !== '' ? mb_substr($notes, 0, 500) : null,
    ]);
    return ['ok' => true, 'id' => (int) rrp_db()->lastInsertId()];
}

function rrp_vet_delete(int $petId, int $id): array
{
    $stmt = rrp_db()->prepare('DELETE FROM vet_appointments WHERE id = ? AND pet_id = ?');
    $stmt->execute([$id, $petId]);
    return ['ok' => $stmt->rowCount() > 0];
}

/**
 * Cron: vaccine due (within 3 days), med times, vet appts (within 24h).
 * @return array{vax:int,meds:int,vet:int}
 */
function rrp_health_cron_tick(int $limit = 50): array
{
    $stats = ['vax' => 0, 'meds' => 0, 'vet' => 0];

    // Vaccines due in next 3 days (not yet reminded for this due date)
    $vax = rrp_db()->prepare(
        "SELECT v.*, p.name AS pet_name, p.owner_id
         FROM vaccination_records v
         INNER JOIN pets p ON p.id = v.pet_id
         INNER JOIN owners o ON o.id = p.owner_id AND o.status = 'active'
         WHERE v.next_due IS NOT NULL
           AND v.next_due <= DATE_ADD(UTC_DATE(), INTERVAL 3 DAY)
           AND v.next_due >= UTC_DATE()
           AND (v.reminded_due_at IS NULL OR DATE(v.reminded_due_at) < UTC_DATE())
         ORDER BY v.next_due ASC
         LIMIT ?"
    );
    $vax->bindValue(1, $limit, PDO::PARAM_INT);
    $vax->execute();
    foreach ($vax->fetchAll() as $row) {
        $title = "Vaccine due soon: {$row['name']}";
        $body = "{$row['pet_name']}'s {$row['name']} is due {$row['next_due']} UTC.\n\n"
            . rrp_app_url('health-care.php?pet=' . (int) $row['pet_id'] . '&tab=vax');
        rrp_notify_owner_inbox((int) $row['owner_id'], 'health', $title, $body);
        rrp_try_web_push((int) $row['owner_id'], $title, $body, rrp_app_url('health-care.php?pet=' . (int) $row['pet_id'] . '&tab=vax'));
        $email = rrp_owner_email((int) $row['owner_id']);
        if ($email) {
            rrp_send_mail($email, $title, $body);
        }
        $upd = rrp_db()->prepare('UPDATE vaccination_records SET reminded_due_at = UTC_TIMESTAMP() WHERE id = ?');
        $upd->execute([(int) $row['id']]);
        $stats['vax']++;
    }

    // Medication reminders
    $meds = rrp_db()->prepare(
        "SELECT m.*, p.name AS pet_name, p.owner_id
         FROM medication_schedules m
         INNER JOIN pets p ON p.id = m.pet_id
         INNER JOIN owners o ON o.id = p.owner_id AND o.status = 'active'
         WHERE m.active = 1
           AND m.next_remind_at IS NOT NULL
           AND m.next_remind_at <= UTC_TIMESTAMP()
         ORDER BY m.next_remind_at ASC
         LIMIT ?"
    );
    $meds->bindValue(1, $limit, PDO::PARAM_INT);
    $meds->execute();
    foreach ($meds->fetchAll() as $row) {
        $dose = $row['dose_note'] ? " ({$row['dose_note']})" : '';
        $title = "Med time: {$row['name']}";
        $body = "Time for {$row['pet_name']}'s {$row['name']}{$dose}.\n\n"
            . rrp_app_url('health-care.php?pet=' . (int) $row['pet_id'] . '&tab=meds');
        rrp_notify_owner_inbox((int) $row['owner_id'], 'health', $title, $body);
        rrp_try_web_push((int) $row['owner_id'], $title, "Time for {$row['pet_name']}'s {$row['name']}", rrp_app_url('health-care.php?pet=' . (int) $row['pet_id'] . '&tab=meds'));
        $email = rrp_owner_email((int) $row['owner_id']);
        if ($email) {
            rrp_send_mail($email, $title, $body);
        }
        $next = rrp_health_next_remind_from_time((string) $row['schedule_time']);
        // If still in the past (clock skew), push another day
        if (strtotime($next . ' UTC') <= time()) {
            $next = gmdate('Y-m-d H:i:s', strtotime($next . ' UTC') + 86400);
        }
        $upd = rrp_db()->prepare(
            'UPDATE medication_schedules SET last_reminded_at = UTC_TIMESTAMP(), next_remind_at = ? WHERE id = ?'
        );
        $upd->execute([$next, (int) $row['id']]);
        $stats['meds']++;
    }

    // Vet appointments within 24h
    $vet = rrp_db()->prepare(
        "SELECT a.*, p.name AS pet_name, p.owner_id
         FROM vet_appointments a
         INNER JOIN pets p ON p.id = a.pet_id
         INNER JOIN owners o ON o.id = p.owner_id AND o.status = 'active'
         WHERE a.appointment_at BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
           AND a.reminded_at IS NULL
         ORDER BY a.appointment_at ASC
         LIMIT ?"
    );
    $vet->bindValue(1, $limit, PDO::PARAM_INT);
    $vet->execute();
    foreach ($vet->fetchAll() as $row) {
        $title = "Vet soon: {$row['title']}";
        $clinic = $row['clinic_name'] ? " at {$row['clinic_name']}" : '';
        $body = "{$row['pet_name']}: {$row['title']}{$clinic} at {$row['appointment_at']} UTC.\n\n"
            . rrp_app_url('health-care.php?pet=' . (int) $row['pet_id'] . '&tab=vet');
        rrp_notify_owner_inbox((int) $row['owner_id'], 'health', $title, $body);
        rrp_try_web_push((int) $row['owner_id'], $title, $body, rrp_app_url('health-care.php?pet=' . (int) $row['pet_id'] . '&tab=vet'));
        $email = rrp_owner_email((int) $row['owner_id']);
        if ($email) {
            rrp_send_mail($email, $title, $body);
        }
        $upd = rrp_db()->prepare('UPDATE vet_appointments SET reminded_at = UTC_TIMESTAMP() WHERE id = ?');
        $upd->execute([(int) $row['id']]);
        $stats['vet']++;
    }

    return $stats;
}

function rrp_owner_email(int $ownerId): ?string
{
    $st = rrp_db()->prepare('SELECT email FROM owners WHERE id = ? LIMIT 1');
    $st->execute([$ownerId]);
    $email = $st->fetchColumn();
    return $email ? (string) $email : null;
}
