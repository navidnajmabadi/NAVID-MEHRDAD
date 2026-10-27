<?php
declare(strict_types=1);

/**
 * Admin insights (brief §4): business KPIs, AI cost, users map, species mix, de-identified health
 * aggregates and a CSV report factory. No human identities: owners appear only as counts.
 * Groups smaller than the minimum size are shown as "<N" (config insights.min_group, default 5),
 * so a single pet can't be recognised from an export.
 */

function rrp_ins_min_group(): int
{
    return max(1, (int) (rrp_config_ready() ? (rrp_load_config()['insights']['min_group'] ?? 5) : 5));
}

function rrp_ins_q(string $sql, array $args = []): array
{
    try {
        $st = rrp_db()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    } catch (Throwable $e) {
        error_log('insights: ' . $e->getMessage());
        return [];
    }
}

function rrp_ins_one(string $sql, array $args = []): mixed
{
    $r = rrp_ins_q($sql, $args);
    return $r ? array_values($r[0])[0] : null;
}

/** USD per 1M tokens (blended estimate; set openai.usd_per_1m_tokens for your real price). */
function rrp_ins_token_price(): float
{
    return (float) (rrp_config_ready() ? (rrp_load_config()['openai']['usd_per_1m_tokens'] ?? 2.0) : 2.0);
}

/** @return array<string,mixed> */
function rrp_ins_business(): array
{
    $active = static fn(int $days) => (int) rrp_ins_one(
        "SELECT COUNT(DISTINCT owner_id) FROM si_usage_daily WHERE message_count > 0 AND usage_day > (CURDATE() - INTERVAL {$days} DAY)"
    );
    $tokens30 = (int) rrp_ins_one('SELECT COALESCE(SUM(token_count),0) FROM si_usage_daily WHERE usage_day > (CURDATE() - INTERVAL 30 DAY)');
    $rev = rrp_ins_q(
        "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, SUM(amount_cents) AS cents, COUNT(*) AS orders,
                SUM(plan_code = 'plus_monthly') AS monthly, SUM(plan_code = 'plus_yearly') AS yearly, SUM(plan_code = 'free_month') AS free_months
         FROM billing_orders WHERE status = 'paid' AND paid_at > (UTC_TIMESTAMP() - INTERVAL 12 MONTH) GROUP BY month ORDER BY month"
    );
    $daily = rrp_ins_q(
        "SELECT usage_day AS day, SUM(message_count) AS messages, SUM(token_count) AS tokens, COUNT(DISTINCT owner_id) AS owners
         FROM si_usage_daily WHERE usage_day > (CURDATE() - INTERVAL 30 DAY) GROUP BY usage_day ORDER BY usage_day"
    );
    return [
        'owners_total' => (int) rrp_ins_one("SELECT COUNT(*) FROM owners WHERE status = 'active'"),
        'owners_new_7' => (int) rrp_ins_one('SELECT COUNT(*) FROM owners WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 7 DAY)'),
        'owners_new_30' => (int) rrp_ins_one('SELECT COUNT(*) FROM owners WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)'),
        'dau' => $active(1),
        'wau' => $active(7),
        'mau' => $active(30),
        'plus_now' => (int) rrp_ins_one("SELECT COUNT(*) FROM owners WHERE plan = 'plus' AND (plan_expires_at IS NULL OR plan_expires_at > UTC_TIMESTAMP())"),
        'pets_total' => (int) rrp_ins_one('SELECT COUNT(*) FROM pets WHERE is_active = 1'),
        'revenue_30_cents' => (int) rrp_ins_one("SELECT COALESCE(SUM(amount_cents),0) FROM billing_orders WHERE status = 'paid' AND paid_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)"),
        'revenue_months' => $rev,
        'tokens_30' => $tokens30,
        'ai_cost_30_usd' => round($tokens30 / 1e6 * rrp_ins_token_price(), 2),
        'token_price' => rrp_ins_token_price(),
        'daily' => $daily,
        'referrals' => rrp_ins_q("SELECT status, COUNT(*) AS n FROM referrals GROUP BY status"),
        'coins_out' => (int) rrp_ins_one('SELECT COALESCE(SUM(amount),0) FROM sip_ledger WHERE amount > 0'),
    ];
}

/** Owners per country (country from map/address, else phone timezone). */
function rrp_ins_countries(): array
{
    $by = [];
    foreach (rrp_ins_q("SELECT id, country, timezone FROM owners WHERE status = 'active'") as $o) {
        $cc = rrp_care_owner_country($o + ['home_lat' => null]) ?? '??';
        $by[$cc] = ($by[$cc] ?? 0) + 1;
    }
    arsort($by);
    return $by;
}

/** Map points rounded to ~10 km cells with counts (no exact homes). */
function rrp_ins_map_points(): array
{
    return rrp_ins_q(
        "SELECT ROUND(COALESCE(home_lat, lat), 1) AS lat, ROUND(COALESCE(home_lng, lng), 1) AS lng, COUNT(*) AS n
         FROM owners WHERE status = 'active' AND COALESCE(home_lat, lat) IS NOT NULL GROUP BY 1, 2"
    );
}

function rrp_ins_species(): array
{
    return [
        'type' => rrp_ins_q("SELECT type AS k, COUNT(*) AS n FROM pets WHERE is_active = 1 GROUP BY type ORDER BY n DESC"),
        'sex' => rrp_ins_q("SELECT sex AS k, COUNT(*) AS n FROM pets WHERE is_active = 1 GROUP BY sex ORDER BY n DESC"),
        'breeds' => rrp_ins_q("SELECT type, breed AS k, COUNT(*) AS n FROM pets WHERE is_active = 1 AND breed IS NOT NULL AND breed <> '' GROUP BY type, breed ORDER BY n DESC LIMIT 15"),
    ];
}

/**
 * Conditions by city (e.g. "% of dogs in Toronto with diabetes"). Sources: pet conditions + vet treatments.
 * @return list<array{city:string, species:string, condition:string, pets:int, of:int, pct:?float}>
 */
function rrp_ins_conditions_by_city(): array
{
    $rows = rrp_ins_q(
        "SELECT p.id, p.type, COALESCE(NULLIF(o.city,''), NULLIF(o.home_area_label,''), 'Unknown') AS city, p.medical_conditions,
                (SELECT GROUP_CONCAT(t.condition_name SEPARATOR ',') FROM vet_treatments t WHERE t.pet_id = p.id) AS treated
         FROM pets p JOIN owners o ON o.id = p.owner_id AND o.status = 'active' WHERE p.is_active = 1"
    );
    $totals = [];
    $counts = [];
    foreach ($rows as $r) {
        $city = mb_convert_case(trim((string) $r['city']), MB_CASE_TITLE);
        $sp = (string) $r['type'];
        $totals[$city][$sp] = ($totals[$city][$sp] ?? 0) + 1;
        $conds = array_unique(array_filter(array_map(
            static fn($c) => mb_strtolower(trim($c)),
            preg_split('/[,;\n]+/', (string) $r['medical_conditions'] . ',' . (string) $r['treated']) ?: []
        ), static fn($c) => mb_strlen($c) >= 3));
        foreach ($conds as $c) {
            $counts[$city][$sp][$c] = ($counts[$city][$sp][$c] ?? 0) + 1;
        }
    }
    $min = rrp_ins_min_group();
    $out = [];
    foreach ($counts as $city => $bySp) {
        foreach ($bySp as $sp => $byC) {
            foreach ($byC as $c => $n) {
                $of = $totals[$city][$sp];
                $out[] = ['city' => $city, 'species' => $sp, 'condition' => $c, 'pets' => $n, 'of' => $of,
                    'pct' => $of >= $min ? round(100 * $n / $of, 1) : null];
            }
        }
    }
    usort($out, static fn($a, $b) => [$b['of'], $b['pets']] <=> [$a['of'], $a['pets']]);
    return $out;
}

/** How often each lab value is out of range, by species. */
function rrp_ins_lab_rates(): array
{
    return rrp_ins_q(
        "SELECT p.type AS species, r.analyte_label AS analyte, COUNT(*) AS results,
                SUM(r.flag IN ('low','high')) AS abnormal, ROUND(100 * SUM(r.flag IN ('low','high')) / COUNT(*), 1) AS pct
         FROM health_lab_results r JOIN health_examinations e ON e.id = r.examination_id JOIN pets p ON p.id = e.pet_id
         GROUP BY p.type, r.analyte_key, r.analyte_label HAVING results >= 1 ORDER BY results DESC LIMIT 30"
    );
}

/** Boards: volume, moderation, and most-used words (admin only). */
function rrp_ins_social(): array
{
    $words = [];
    $stop = array_flip(explode(' ', 'the a an and or but to of in on for with is are was were be my your our his her its it this that at as so we i you he she they them me just very really can do does did have has had not no yes from up out about what when how why who will would there here all any more most some'));
    foreach (rrp_ins_q("SELECT body FROM forum_posts WHERE moderation_status = 'visible' AND created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY) LIMIT 2000") as $r) {
        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower((string) $r['body'])) ?: [] as $w) {
            if (mb_strlen($w) >= 4 && !isset($stop[$w])) {
                $words[$w] = ($words[$w] ?? 0) + 1;
            }
        }
    }
    arsort($words);
    return [
        'posts_30' => (int) rrp_ins_one("SELECT COUNT(*) FROM forum_posts WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)"),
        'comments_30' => (int) rrp_ins_one("SELECT COUNT(*) FROM forum_comments WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)"),
        'removed_30' => (int) rrp_ins_one("SELECT COUNT(*) FROM moderation_log WHERE action = 'removed' AND created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)"),
        'by_source' => rrp_ins_q("SELECT source, COUNT(*) AS n FROM moderation_log WHERE action = 'removed' GROUP BY source"),
        'top_words' => array_slice($words, 0, 20, true),
    ];
}

/** Report factory: dataset key → [title, rows]. */
function rrp_ins_reports(): array
{
    return [
        'species_by_country' => 'Pets by species and country',
        'conditions_by_city' => 'Conditions by city and species (% of pets)',
        'lab_abnormal_rates' => 'Lab values out of range, by species',
        'activity_daily' => 'SIPO messages, tokens and active owners per day (30 days)',
        'revenue_monthly' => 'Revenue and orders per month',
        'care_routines' => 'Care routines done per week (all owners)',
    ];
}

function rrp_ins_report_rows(string $key): array
{
    $min = rrp_ins_min_group();
    switch ($key) {
        case 'species_by_country':
            $by = [];
            foreach (rrp_ins_q("SELECT p.type, o.country, o.timezone FROM pets p JOIN owners o ON o.id = p.owner_id WHERE p.is_active = 1") as $r) {
                $cc = rrp_care_owner_country(['country' => $r['country'], 'timezone' => $r['timezone']]) ?? '??';
                $by[$cc . '|' . $r['type']] = ($by[$cc . '|' . $r['type']] ?? 0) + 1;
            }
            $rows = [];
            foreach ($by as $k => $n) {
                [$cc, $t] = explode('|', $k);
                $rows[] = ['country' => $cc, 'species' => $t, 'pets' => $n >= $min ? $n : '<' . $min];
            }
            return $rows;
        case 'conditions_by_city':
            return array_map(static fn($r) => ['city' => $r['city'], 'species' => $r['species'], 'condition' => $r['condition'],
                'pets_with' => $r['of'] >= $min ? $r['pets'] : '<' . $min, 'pets_total' => $r['of'] >= $min ? $r['of'] : '<' . $min,
                'pct' => $r['pct'] ?? ''], rrp_ins_conditions_by_city());
        case 'lab_abnormal_rates':
            return rrp_ins_lab_rates();
        case 'activity_daily':
            $p = rrp_ins_token_price();
            return array_map(static fn($r) => $r + ['est_cost_usd' => round((int) $r['tokens'] / 1e6 * $p, 4)], rrp_ins_business()['daily']);
        case 'revenue_monthly':
            return array_map(static fn($r) => $r + ['usd' => round((int) $r['cents'] / 100, 2)], rrp_ins_business()['revenue_months']);
        case 'care_routines':
            return rrp_ins_q(
                "SELECT YEARWEEK(completed_at, 3) AS iso_week, routine_key, COUNT(*) AS done FROM care_reminders
                 WHERE status = 'completed' AND kind = 'routine' AND completed_at > (UTC_TIMESTAMP() - INTERVAL 26 WEEK)
                 GROUP BY iso_week, routine_key ORDER BY iso_week, done DESC"
            );
    }
    return [];
}

/** Stream a report as CSV. */
function rrp_ins_csv(string $key): void
{
    $rows = rrp_ins_report_rows($key);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sipaw-' . preg_replace('/[^a-z_]/', '', $key) . '-' . gmdate('Ymd') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
    if ($rows) {
        fputcsv($out, array_keys($rows[0]), ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($out, array_values($r), ',', '"', '');
        }
    } else {
        fputcsv($out, ['no data yet'], ',', '"', '');
    }
    fclose($out);
}
