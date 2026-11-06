<?php
declare(strict_types=1);

/**
 * Weekly health report: what happened for each pet in the last 7 days, what is coming up,
 * and one caring note from SIPO. Everything comes from the database, nothing is invented.
 * Rendered as an email by lib/email.php; sent by cron/weekly-report.php.
 */

/** Save one dated weight (once per day: a second weigh-in the same day replaces the first). */
function rrp_weight_log(int $petId, float $kg, string $source = 'sipo'): void
{
    if ($petId <= 0 || $kg <= 0 || $kg >= 200) {
        return;
    }
    try {
        $db = rrp_db();
        $day = gmdate('Y-m-d');
        $db->prepare('DELETE FROM pet_weight_logs WHERE pet_id = ? AND logged_on = ?')->execute([$petId, $day]);
        $db->prepare('INSERT INTO pet_weight_logs (pet_id, weight_kg, logged_on, source) VALUES (?, ?, ?, ?)')
            ->execute([$petId, round($kg, 2), $day, $source]);
    } catch (Throwable $e) {
        error_log('rrp_weight_log: ' . $e->getMessage());
    }
}

/**
 * Newest weights last, at most $limit, as [['date' => 'Y-m-d', 'kg' => float], ...].
 *
 * @return list<array{date:string,kg:float}>
 */
function rrp_weight_series(int $petId, int $limit = 8): array
{
    try {
        $st = rrp_db()->prepare('SELECT logged_on, weight_kg FROM pet_weight_logs WHERE pet_id = ? ORDER BY logged_on DESC, id DESC LIMIT ' . max(1, $limit));
        $st->execute([$petId]);
        $rows = array_reverse($st->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static fn($r) => ['date' => (string) $r['logged_on'], 'kg' => (float) $r['weight_kg']], $rows);
}

/** Weight change over the series: 'steady', 'up' or 'down' (more than 3% from first to last). */
function rrp_weight_trend(array $series): string
{
    if (count($series) < 2 || $series[0]['kg'] <= 0) {
        return 'steady';
    }
    $chg = ($series[count($series) - 1]['kg'] - $series[0]['kg']) / $series[0]['kg'];
    return $chg > 0.03 ? 'up' : ($chg < -0.03 ? 'down' : 'steady');
}

/**
 * @return array{owner_name:string, from:string, to:string, pets:list<array<string,mixed>>, spend:array<string,float>, has_activity:bool}
 */
function rrp_report_build(array $owner, ?DateTimeImmutable $now = null): array
{
    $tz = function_exists('rrp_sipo_owner_tz') ? rrp_sipo_owner_tz($owner) : new DateTimeZone('UTC');
    $now = ($now ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);
    $today = $now->format('Y-m-d');
    $from = $now->modify('-6 days')->format('Y-m-d');
    $soon = $now->modify('+30 days')->format('Y-m-d');
    $db = rrp_db();

    $st = $db->prepare('SELECT * FROM pets WHERE owner_id = ? AND is_active = 1 ORDER BY id ASC');
    $st->execute([(int) $owner['id']]);
    $pets = [];
    $spend = [];
    $any = false;

    $one = static function (string $sql, array $args) use ($db) {
        try {
            $q = $db->prepare($sql);
            $q->execute($args);
            return $q;
        } catch (Throwable $e) {
            return null;
        }
    };

    foreach ($st->fetchAll() as $pet) {
        $pid = (int) $pet['id'];
        $q = $one('SELECT COUNT(*) AS n, COALESCE(SUM(minutes),0) AS m FROM pet_walks WHERE pet_id = ? AND local_day >= ?', [$pid, $from]);
        $w = $q ? $q->fetch() : ['n' => 0, 'm' => 0];
        $q = $one('SELECT COUNT(*) FROM pet_bathroom_logs WHERE pet_id = ? AND local_day >= ?', [$pid, $from]);
        $bath = $q ? (int) $q->fetchColumn() : 0;
        $q = $one('SELECT COUNT(*) FROM wellness_daily_logs WHERE pet_id = ? AND log_date >= ?', [$pid, $from]);
        $checkins = $q ? (int) $q->fetchColumn() : 0;

        $series = rrp_weight_series($pid, 8);

        $due = [];
        $q = $one('SELECT name, next_due FROM vaccination_records WHERE pet_id = ? AND next_due IS NOT NULL AND next_due <= ? ORDER BY next_due ASC LIMIT 6', [$pid, $soon]);
        foreach ($q ? $q->fetchAll() : [] as $r) {
            $days = (int) floor((strtotime((string) $r['next_due'] . ' UTC') - strtotime($today . ' UTC')) / 86400);
            $due[] = ['label' => (string) $r['name'], 'date' => (string) $r['next_due'], 'days' => $days, 'status' => $days < 0 ? 'overdue' : 'soon'];
        }

        // Courses (drops, pills) that are about to finish: SIPO asks if the course is completed.
        $meds = [];
        $q = $one('SELECT name, kind, ends_on FROM medication_schedules WHERE pet_id = ? AND active = 1 AND (ends_on IS NULL OR ends_on >= ?) ORDER BY ends_on IS NULL, ends_on ASC LIMIT 6', [$pid, $today]);
        foreach ($q ? $q->fetchAll() : [] as $r) {
            $left = $r['ends_on'] ? (int) floor((strtotime((string) $r['ends_on'] . ' UTC') - strtotime($today . ' UTC')) / 86400) : null;
            $meds[] = ['name' => (string) $r['name'], 'kind' => (string) $r['kind'], 'ends_on' => $r['ends_on'], 'days_left' => $left];
        }

        $labs = [];
        $q = $one(
            "SELECT r.analyte_label, r.value_num, r.value_text, r.unit, r.ref_low, r.ref_high, r.flag, e.examined_on
             FROM health_lab_results r JOIN health_examinations e ON e.id = r.examination_id
             WHERE e.pet_id = ? AND r.flag IN ('low','high') AND e.examined_on >= ?
             ORDER BY e.examined_on DESC, r.id ASC LIMIT 6",
            [$pid, $now->modify('-60 days')->format('Y-m-d')]
        );
        foreach ($q ? $q->fetchAll() : [] as $r) {
            $labs[] = [
                'label' => (string) $r['analyte_label'],
                'value' => $r['value_num'] !== null ? rtrim(rtrim(number_format((float) $r['value_num'], 2, '.', ''), '0'), '.') : (string) $r['value_text'],
                'unit' => (string) $r['unit'],
                'range' => ($r['ref_low'] !== null && $r['ref_high'] !== null) ? ((float) $r['ref_low'] . ' to ' . (float) $r['ref_high']) : '',
                'flag' => (string) $r['flag'],
                'date' => (string) $r['examined_on'],
            ];
        }

        $q = $one('SELECT currency, SUM(amount) AS t FROM pet_expenses WHERE pet_id = ? AND spent_on >= ? GROUP BY currency', [$pid, $from]);
        $petSpend = [];
        foreach ($q ? $q->fetchAll() : [] as $r) {
            $cur = (string) ($r['currency'] ?: 'CAD');
            $petSpend[$cur] = (float) $r['t'];
            $spend[$cur] = ($spend[$cur] ?? 0) + (float) $r['t'];
        }

        $row = [
            'id' => $pid,
            'name' => (string) $pet['name'],
            'type' => (string) $pet['type'],
            'walks' => (int) $w['n'],
            'walk_minutes' => (int) $w['m'],
            'bathroom' => $bath,
            'checkins' => $checkins,
            'weight' => $series,
            'weight_trend' => rrp_weight_trend($series),
            'due' => $due,
            'meds' => $meds,
            'labs' => $labs,
            'spend' => $petSpend,
        ];
        $row['note'] = rrp_report_note($row);
        $any = $any || $row['walks'] > 0 || $bath > 0 || $checkins > 0 || $due || $labs || $meds || $petSpend;
        $pets[] = $row;
    }

    return [
        'owner_name' => trim((string) ($owner['name'] ?? '')) ?: 'there',
        'from' => $from,
        'to' => $today,
        'pets' => $pets,
        'spend' => $spend,
        'has_activity' => $any,
        'units' => rrp_units($owner),
    ];
}

/** One caring sentence for a pet, chosen from what the week actually showed (worries first). */
function rrp_report_note(array $p): string
{
    $n = $p['name'];
    foreach ($p['due'] as $d) {
        if ($d['status'] === 'overdue') {
            return "{$n}'s {$d['label']} is overdue. A call to the vet this week would put my mind at ease.";
        }
    }
    if ($p['labs']) {
        return "A few of {$n}'s lab values are outside the usual range. They are worth showing the vet; I can help you share them.";
    }
    if ($p['weight_trend'] === 'down') {
        return "{$n}'s weight has been drifting down. If this is not planned, let us keep an eye on it together and tell the vet.";
    }
    if ($p['weight_trend'] === 'up') {
        return "{$n}'s weight is creeping up. Smaller portions or an extra walk can help; I can work out the food for you.";
    }
    foreach ($p['meds'] as $m) {
        if ($m['days_left'] !== null && $m['days_left'] <= 3) {
            return "{$n}'s {$m['name']} course is nearly finished. I will ask you to confirm it was completed.";
        }
    }
    if ($p['due']) {
        return "Something is coming up for {$n}. I have listed it below so nothing slips by.";
    }
    if ($p['walks'] + $p['bathroom'] + $p['checkins'] === 0) {
        return "It was a quiet week for {$n} in SIPAW. Tell me how {$n} is doing and I will keep the record up to date.";
    }
    return "{$n} had a steady, healthy-looking week. Thank you for taking such good care 💙";
}

/** Send one owner their report now (used by the weekly job and the "send me a sample" button). */
function rrp_report_send(array $owner): array
{
    $email = trim((string) ($owner['email'] ?? ''));
    if ($email === '') {
        return ['ok' => false, 'error' => 'No email on file.'];
    }
    $report = rrp_report_build($owner);
    $mail = rrp_email_weekly_report($report, rrp_app_url('settings'));
    return rrp_email_send($email, $mail);
}

/**
 * Cron: every owner who wants it gets the report on Sunday from 09:00 their time, once a week.
 * Skips accounts younger than 3 days and owners without a pet.
 *
 * @return array{checked:int, sent:int, failed:int}
 */
function rrp_report_tick(int $max = 20): array
{
    $out = ['checked' => 0, 'sent' => 0, 'failed' => 0];
    $st = rrp_db()->prepare(
        "SELECT * FROM owners
         WHERE status = 'active' AND report_weekly = 1 AND email <> ''
           AND created_at < (UTC_TIMESTAMP() - INTERVAL 3 DAY)
           AND (report_last_sent_at IS NULL OR report_last_sent_at < (UTC_TIMESTAMP() - INTERVAL 5 DAY))
           AND EXISTS (SELECT 1 FROM pets p WHERE p.owner_id = owners.id AND p.is_active = 1)
         ORDER BY report_last_sent_at IS NULL DESC, report_last_sent_at ASC LIMIT 400"
    );
    $st->execute();
    foreach ($st->fetchAll() as $owner) {
        $local = new DateTimeImmutable('now', rrp_sipo_owner_tz($owner));
        if ((int) $local->format('w') !== 0 || (int) $local->format('G') < 9) {
            continue;
        }
        if ($out['checked'] >= $max) {
            break;
        }
        $out['checked']++;
        $res = rrp_report_send($owner);
        if (!empty($res['ok'])) {
            $out['sent']++;
            rrp_db()->prepare('UPDATE owners SET report_last_sent_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $owner['id']]);
        } else {
            $out['failed']++;
            error_log('rrp_report_tick: owner ' . (int) $owner['id'] . ': ' . ($res['error'] ?? 'send failed'));
            // Do not retry every 5 minutes all day: try again tomorrow's tick window only after 20 hours.
            rrp_db()->prepare('UPDATE owners SET report_last_sent_at = UTC_TIMESTAMP() - INTERVAL 4 DAY - INTERVAL 4 HOUR WHERE id = ?')->execute([(int) $owner['id']]);
        }
    }
    return $out;
}
