<?php
declare(strict_types=1);

/**
 * Lab reports (brief §A2): numbers are kept, files are not (Navid 2026-10-28).
 * Each analyte is flagged against the report's own reference range; trends compare
 * the same analyte across reports.
 */

/** Common names → one key, so "SGPT" and "ALT" land on the same chart. */
const RRP_LAB_SYNONYMS = [
    'alt' => ['alt', 'sgpt', 'alanineaminotransferase'],
    'ast' => ['ast', 'sgot', 'aspartateaminotransferase'],
    'alp' => ['alp', 'alkp', 'alkalinephosphatase'],
    'ggt' => ['ggt', 'gammaglutamyltransferase'],
    'bun' => ['bun', 'urea', 'ureanitrogen', 'bloodureanitrogen'],
    'creatinine' => ['creatinine', 'crea', 'creat'],
    'sdma' => ['sdma'],
    'glucose' => ['glucose', 'glu', 'bloodglucose'],
    'total_protein' => ['totalprotein', 'tp', 'tprotein'],
    'albumin' => ['albumin', 'alb'],
    'globulin' => ['globulin', 'glob'],
    'bilirubin' => ['totalbilirubin', 'tbil', 'bilirubin'],
    'cholesterol' => ['cholesterol', 'chol'],
    'phosphorus' => ['phosphorus', 'phos', 'phosphate'],
    'calcium' => ['calcium', 'ca'],
    'sodium' => ['sodium', 'na'],
    'potassium' => ['potassium', 'k'],
    'chloride' => ['chloride', 'cl'],
    't4' => ['t4', 'tt4', 'totalt4', 'thyroxine'],
    'wbc' => ['wbc', 'whitebloodcells', 'leukocytes'],
    'rbc' => ['rbc', 'redbloodcells', 'erythrocytes'],
    'hgb' => ['hgb', 'hb', 'hemoglobin', 'haemoglobin'],
    'hct' => ['hct', 'hematocrit', 'haematocrit', 'pcv'],
    'plt' => ['plt', 'platelets', 'thrombocytes'],
    'usg' => ['usg', 'urinespecificgravity', 'specificgravity'],
];

function rrp_lab_key(string $labelOrKey): string
{
    $k = preg_replace('/[^a-z0-9]/', '', mb_strtolower($labelOrKey)) ?? '';
    foreach (RRP_LAB_SYNONYMS as $key => $names) {
        if (in_array($k, $names, true)) {
            return $key;
        }
    }
    return mb_substr($k !== '' ? $k : 'value', 0, 80);
}

function rrp_lab_flag(?float $v, ?float $lo, ?float $hi): string
{
    if ($v === null || ($lo === null && $hi === null)) {
        return 'unknown';
    }
    if ($lo !== null && $v < $lo) {
        return 'low';
    }
    if ($hi !== null && $v > $hi) {
        return 'high';
    }
    return 'normal';
}

/**
 * Save one parsed report.
 * @param array{examined_on?:?string, clinic?:?string, exam_type?:?string, title?:?string, results:list<array>} $lab
 * @return array{ok:bool, error?:string, exam_id?:int, counts?:array, abnormal?:list<array>}
 */
function rrp_labs_save(array $owner, array $pet, array $lab, string $source = 'upload'): array
{
    $results = array_values(array_filter((array) ($lab['results'] ?? []), 'is_array'));
    if (!$results) {
        return ['ok' => false, 'error' => 'No results found in the report.'];
    }
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($lab['examined_on'] ?? '')) ? $lab['examined_on']
        : rrp_sipo_local_now($owner)->format('Y-m-d');
    $type = in_array($lab['exam_type'] ?? '', ['blood', 'urine', 'imaging', 'physical', 'allergy_panel', 'other'], true) ? $lab['exam_type'] : 'blood';
    $num = static fn($v) => is_numeric($v) ? (float) $v : null;

    $pdo = rrp_db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO health_examinations (pet_id, owner_id, exam_type, title, clinic_name, examined_on, summary, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $pet['id'], (int) $owner['id'], $type,
            mb_substr((string) ($lab['title'] ?? ucfirst($type) . ' test'), 0, 160),
            isset($lab['clinic']) ? mb_substr((string) $lab['clinic'], 0, 160) : null,
            $date, isset($lab['summary']) ? mb_substr((string) $lab['summary'], 0, 2000) : null, $source,
        ]);
        $examId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO health_lab_results (examination_id, analyte_key, analyte_label, value_num, value_text, unit, ref_low, ref_high, flag)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $counts = ['low' => 0, 'normal' => 0, 'high' => 0, 'unknown' => 0];
        $abnormal = [];
        foreach (array_slice($results, 0, 80) as $r) {
            $label = trim((string) ($r['label'] ?? $r['key'] ?? ''));
            if ($label === '') {
                continue;
            }
            $v = $num($r['value'] ?? null);
            $lo = $num($r['ref_low'] ?? null);
            $hi = $num($r['ref_high'] ?? null);
            $flag = rrp_lab_flag($v, $lo, $hi);
            $counts[$flag]++;
            $ins->execute([
                $examId, rrp_lab_key((string) ($r['key'] ?? $label)), mb_substr($label, 0, 120), $v,
                isset($r['value_text']) && $v === null ? mb_substr((string) $r['value_text'], 0, 120) : null,
                isset($r['unit']) ? mb_substr((string) $r['unit'], 0, 40) : null, $lo, $hi, $flag,
            ]);
            if ($flag === 'low' || $flag === 'high') {
                $abnormal[] = ['label' => $label, 'value' => $v, 'unit' => $r['unit'] ?? '', 'ref_low' => $lo, 'ref_high' => $hi, 'flag' => $flag];
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('rrp_labs_save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save the results.'];
    }
    // Same report sent twice (same date + same values) earns once.
    $fp = $date;
    foreach ($results as $r) {
        $fp .= '|' . rrp_lab_key((string) ($r['key'] ?? $r['label'] ?? '')) . '=' . ($r['value'] ?? '');
    }
    $coins = rrp_coin_award((int) $owner['id'], 'lab_report', 'Lab report for ' . $pet['name'], 'lab:' . (int) $pet['id'], (int) sprintf('%u', crc32($fp)) % 4000000000, (int) $pet['id']);
    return ['ok' => true, 'exam_id' => $examId, 'date' => $date, 'counts' => $counts, 'abnormal' => $abnormal, 'coins' => $coins];
}

/**
 * Per analyte, all numeric points over time (oldest first), latest flag and direction.
 * @return list<array{key:string, label:string, unit:?string, points:list<array>, latest:array, direction:string}>
 */
function rrp_labs_trends(int $petId): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT r.analyte_key, r.analyte_label, r.value_num, r.unit, r.ref_low, r.ref_high, r.flag, e.examined_on, e.id AS exam_id
             FROM health_lab_results r JOIN health_examinations e ON e.id = r.examination_id
             WHERE e.pet_id = ? AND r.value_num IS NOT NULL
             ORDER BY e.examined_on ASC, e.id ASC"
        );
        $stmt->execute([$petId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $by = [];
    foreach ($rows as $r) {
        $k = (string) $r['analyte_key'];
        $by[$k] ??= ['key' => $k, 'label' => (string) $r['analyte_label'], 'unit' => $r['unit'], 'points' => []];
        $by[$k]['points'][] = [
            'date' => (string) $r['examined_on'],
            'value' => (float) $r['value_num'],
            'ref_low' => $r['ref_low'] !== null ? (float) $r['ref_low'] : null,
            'ref_high' => $r['ref_high'] !== null ? (float) $r['ref_high'] : null,
            'flag' => (string) $r['flag'],
        ];
    }
    foreach ($by as &$a) {
        $pts = $a['points'];
        $a['latest'] = end($pts);
        $a['direction'] = 'flat';
        if (count($pts) >= 2) {
            $prev = $pts[count($pts) - 2]['value'];
            $last = $a['latest']['value'];
            $span = ($a['latest']['ref_high'] ?? 0) - ($a['latest']['ref_low'] ?? 0);
            $tol = $span > 0 ? $span * 0.05 : abs($prev) * 0.05;
            $a['direction'] = $last > $prev + $tol ? 'up' : ($last < $prev - $tol ? 'down' : 'flat');
        }
        // "Heading out of range": still normal but moving toward a limit fast.
        $a['watch'] = false;
        if ($a['latest']['flag'] === 'normal' && count($pts) >= 2 && $a['latest']['ref_high'] !== null && $a['latest']['ref_low'] !== null) {
            $span = $a['latest']['ref_high'] - $a['latest']['ref_low'];
            $v = $a['latest']['value'];
            $a['watch'] = $span > 0 && (
                ($a['direction'] === 'up' && $v > $a['latest']['ref_high'] - 0.1 * $span) ||
                ($a['direction'] === 'down' && $v < $a['latest']['ref_low'] + 0.1 * $span)
            );
        }
    }
    unset($a);
    $list = array_values($by);
    // Abnormal first, then watch, then the rest alphabetically.
    usort($list, static function ($x, $y) {
        $rank = static fn($a) => in_array($a['latest']['flag'], ['low', 'high'], true) ? 0 : ($a['watch'] ? 1 : 2);
        return [$rank($x), $x['label']] <=> [$rank($y), $y['label']];
    });
    return $list;
}

function rrp_labs_prompt_block(int $petId): ?string
{
    $t = rrp_labs_trends($petId);
    if (!$t) {
        return null;
    }
    $lines = [];
    foreach (array_slice($t, 0, 14) as $a) {
        $l = $a['latest'];
        $ref = ($l['ref_low'] !== null || $l['ref_high'] !== null) ? " (ref {$l['ref_low']}–{$l['ref_high']})" : '';
        $lines[] = "- {$a['label']}: {$l['value']} {$a['unit']}{$ref} on {$l['date']} · {$l['flag']}"
            . (count($a['points']) > 1 ? " · trend {$a['direction']} over " . count($a['points']) . ' reports' : '')
            . ($a['watch'] ? ' · close to the limit' : '');
    }
    return implode("\n", $lines);
}
