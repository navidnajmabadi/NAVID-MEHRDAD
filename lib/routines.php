<?php
declare(strict_types=1);

function rrp_routine_templates(?string $speciesKey = null): array
{
    try {
        if ($speciesKey) {
            $stmt = rrp_db()->prepare(
                'SELECT * FROM routine_templates WHERE is_active = 1 AND (species_key = ? OR species_key = \'other\') ORDER BY pack_key, sort_order, title'
            );
            $stmt->execute([$speciesKey]);
        } else {
            $stmt = rrp_db()->query('SELECT * FROM routine_templates WHERE is_active = 1 ORDER BY pack_key, sort_order, title');
        }
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{ok:bool, error?:string, reminder_id?:int}
 */
function rrp_care_reminder_create(int $petId, array $data): array
{
    $title = trim((string) ($data['title'] ?? ''));
    $dueAt = trim((string) ($data['due_at'] ?? ''));
    $status = (string) ($data['status'] ?? 'pending_approval');
    if ($title === '') {
        return ['ok' => false, 'error' => 'Title required.'];
    }
    if ($dueAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $dueAt)) {
        return ['ok' => false, 'error' => 'due_at required (YYYY-MM-DD or datetime).'];
    }
    $allowed = ['pending_approval', 'scheduled', 'sent', 'completed', 'dismissed', 'snoozed'];
    if (!in_array($status, $allowed, true)) {
        $status = 'pending_approval';
    }
    $due = strlen($dueAt) === 10 ? $dueAt . ' 09:00:00' : $dueAt;
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO care_reminders (pet_id, title, due_at, status, merge_group)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $petId,
            mb_substr($title, 0, 160),
            $due,
            $status,
            $data['merge_group'] ?? null,
        ]);
        return ['ok' => true, 'reminder_id' => (int) rrp_db()->lastInsertId()];
    } catch (Throwable $e) {
        error_log('rrp_care_reminder_create: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not create reminder. Import sql/006_v1_product.sql.'];
    }
}

function rrp_care_reminders_for_pet(int $petId): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT * FROM care_reminders WHERE pet_id = ? AND status IN ('pending_approval','scheduled','snoozed') ORDER BY due_at ASC"
        );
        $stmt->execute([$petId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_care_reminder_set_status(int $ownerId, int $reminderId, string $status): array
{
    $allowed = ['pending_approval', 'scheduled', 'sent', 'completed', 'dismissed', 'snoozed'];
    if (!in_array($status, $allowed, true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }
    try {
        $stmt = rrp_db()->prepare(
            'SELECT r.* FROM care_reminders r JOIN pets p ON p.id = r.pet_id WHERE r.id = ? AND p.owner_id = ? LIMIT 1'
        );
        $stmt->execute([$reminderId, $ownerId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => 'Reminder not found.'];
        }
        if ($status === 'scheduled') {
            rrp_db()->prepare('UPDATE care_reminders SET status = ?, approved_at = NOW() WHERE id = ?')
                ->execute([$status, $reminderId]);
        } elseif ($status === 'completed') {
            rrp_db()->prepare('UPDATE care_reminders SET status = ?, completed_at = NOW() WHERE id = ?')
                ->execute([$status, $reminderId]);
        } else {
            rrp_db()->prepare('UPDATE care_reminders SET status = ? WHERE id = ?')->execute([$status, $reminderId]);
        }
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not update reminder.'];
    }
}

/** Build a simple .ics calendar for scheduled reminders (T10). */
function rrp_care_reminders_ics(int $ownerId, int $petId): string
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    $name = $pet['name'] ?? 'Pet';
    $rows = [];
    try {
        $stmt = rrp_db()->prepare(
            "SELECT * FROM care_reminders WHERE pet_id = ? AND status = 'scheduled' ORDER BY due_at ASC"
        );
        $stmt->execute([$petId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//SIPAW//Care Reminders//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];
    foreach ($rows as $r) {
        $uid = 'sipaw-reminder-' . (int) $r['id'] . '@sipaw.nakilab.com';
        $dt = preg_replace('/[^0-9]/', '', substr((string) $r['due_at'], 0, 19)) ?: gmdate('YmdHis');
        $dtStart = strlen($dt) >= 14
            ? substr($dt, 0, 8) . 'T' . substr($dt, 8, 6)
            : gmdate('Ymd') . 'T090000';
        $summary = str_replace([',', ';', "\n"], ['\\,', '\\;', ' '], (string) $r['title']);
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $dtStart;
        $lines[] = 'SUMMARY:' . $name . ' — ' . $summary;
        $lines[] = 'DESCRIPTION:SIPAW care reminder (.ics only — no Google Calendar OAuth)';
        $lines[] = 'END:VEVENT';
        try {
            rrp_db()->prepare('UPDATE care_reminders SET ics_uid = ? WHERE id = ?')->execute([$uid, (int) $r['id']]);
        } catch (Throwable $e) {
        }
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}

/**
 * Apply a template pack onto a pet as pending_approval reminders (T09–T11).
 * @return array{ok:bool, error?:string, created?:int}
 */
function rrp_apply_routine_pack(int $ownerId, int $petId, string $packKey): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $species = $pet['species_key'] ?? $pet['type'] ?? 'other';
    try {
        $stmt = rrp_db()->prepare(
            'SELECT * FROM routine_templates WHERE is_active = 1 AND pack_key = ? AND species_key IN (?, \'other\')'
        );
        $stmt->execute([$packKey, $species]);
        $tpls = $stmt->fetchAll();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Templates unavailable. Import 006/007 SQL.'];
    }
    if (!$tpls) {
        return ['ok' => false, 'error' => 'No templates for that pack.'];
    }
    $created = 0;
    foreach ($tpls as $t) {
        $days = max(1, (int) ($t['interval_days'] ?? 30));
        $due = (new DateTimeImmutable('today'))->modify("+{$days} days")->format('Y-m-d');
        $res = rrp_care_reminder_create($petId, [
            'title' => $t['title'],
            'due_at' => $due . ' 09:00:00',
            'status' => 'pending_approval',
            'merge_group' => $t['merge_group'] ?? null,
        ]);
        if (!empty($res['ok'])) {
            $created++;
        }
    }
    return ['ok' => true, 'created' => $created];
}
