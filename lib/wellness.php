<?php
declare(strict_types=1);

function rrp_wellness_symptom_defs(): array
{
    try {
        $stmt = rrp_db()->query('SELECT * FROM wellness_symptom_defs WHERE active = 1 ORDER BY sort_order ASC');
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [
            ['symptom_key' => 'itchy', 'label_en' => 'Itchy'],
            ['symptom_key' => 'paw_licking', 'label_en' => 'Paw licking'],
            ['symptom_key' => 'gi', 'label_en' => 'GI / stool'],
            ['symptom_key' => 'energy', 'label_en' => 'Energy'],
            ['symptom_key' => 'other', 'label_en' => 'Other'],
        ];
    }
}

/**
 * @param array<string,int> $scores symptom_key => severity 0-5
 * @return array{ok:bool, error?:string, log_id?:int}
 */
function rrp_wellness_save_day(int $ownerId, int $petId, string $date, array $scores, ?string $note = null, ?array $triggers = null): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['ok' => false, 'error' => 'Invalid date.'];
    }
    $note = $note !== null ? trim($note) : null;
    if ($note === '') {
        $note = null;
    }
    $triggersJson = $triggers ? json_encode(array_values($triggers)) : null;

    try {
        $pdo = rrp_db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO wellness_daily_logs (pet_id, owner_id, log_date, overall_note, triggers_json)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE overall_note = VALUES(overall_note), triggers_json = VALUES(triggers_json), id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([$petId, $ownerId, $date, $note, $triggersJson]);
        $logId = (int) $pdo->lastInsertId();
        if ($logId <= 0) {
            $q = $pdo->prepare('SELECT id FROM wellness_daily_logs WHERE pet_id = ? AND log_date = ?');
            $q->execute([$petId, $date]);
            $logId = (int) $q->fetchColumn();
        }
        $pdo->prepare('DELETE FROM wellness_daily_scores WHERE daily_log_id = ?')->execute([$logId]);
        $ins = $pdo->prepare(
            'INSERT INTO wellness_daily_scores (daily_log_id, symptom_key, severity) VALUES (?, ?, ?)'
        );
        foreach ($scores as $key => $sev) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?: '';
            $sev = max(0, min(5, (int) $sev));
            if ($key === '') {
                continue;
            }
            $ins->execute([$logId, $key, $sev]);
        }
        $pdo->commit();
        return ['ok' => true, 'log_id' => $logId];
    } catch (Throwable $e) {
        if (rrp_db()->inTransaction()) {
            rrp_db()->rollBack();
        }
        error_log('rrp_wellness_save_day: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save wellness log. Import sql/007_t03_t15_deltas.sql if needed.'];
    }
}

function rrp_wellness_history(int $ownerId, int $petId, int $days = 30): array
{
    $days = max(7, min(365, $days));
    try {
        $stmt = rrp_db()->prepare(
            "SELECT l.log_date, l.overall_note, s.symptom_key, s.severity
             FROM wellness_daily_logs l
             LEFT JOIN wellness_daily_scores s ON s.daily_log_id = l.id
             WHERE l.owner_id = ? AND l.pet_id = ? AND l.log_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
             ORDER BY l.log_date ASC"
        );
        $stmt->execute([$ownerId, $petId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
