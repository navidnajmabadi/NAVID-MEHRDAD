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

/** Persian (۰-۹) and Arabic-Indic (٠-٩) digits and separators → plain 0-9, '.' and '/'. */
function rrp_digits_en(string $s): string
{
    return strtr($s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '٫' => '.', '٬' => ',', '/' => '/',
    ]);
}

/** Jalali (Shamsi) → Gregorian [year, month, day]. */
function rrp_jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * intdiv($days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) {
        $gd -= $sal[$gm];
    }
    return [$gy, $gm, $gd];
}

/**
 * Any report date → Gregorian 'Y-m-d', or null. Reads Persian digits, 1403/05/12 or 1403-05-12
 * (Jalali years 1300-1500 are converted), and normal 2024-08-02.
 */
function rrp_lab_normalize_date(?string $raw): ?string
{
    $s = trim(rrp_digits_en((string) $raw));
    if (!preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $s, $m)) {
        return null;
    }
    [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
        return null;
    }
    if ($y >= 1300 && $y <= 1500) {
        [$y, $mo, $d] = rrp_jalali_to_gregorian($y, $mo, $d);
    }
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

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
    rrp_db_keepalive(); // the connection may have idled while the file was being read
    $results = array_values(array_filter((array) ($lab['results'] ?? []), 'is_array'));
    if (!$results) {
        return ['ok' => false, 'error' => 'No results found in the report.'];
    }
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    $date = rrp_lab_normalize_date((string) ($lab['examined_on'] ?? ''));
    if ($date === null || $date > $today) {
        $date = $today;
    }
    $type = in_array($lab['exam_type'] ?? '', ['blood', 'urine', 'imaging', 'physical', 'allergy_panel', 'other'], true) ? $lab['exam_type'] : 'blood';
    $num = static function ($v) {
        $v = is_string($v) ? str_replace(',', '.', rrp_digits_en($v)) : $v;
        return is_numeric($v) ? (float) $v : null;
    };

    // The same report sent again (same date and same numbers) is skipped, not saved twice.
    $fpNew = [];
    foreach ($results as $r) {
        $fpNew[] = rrp_lab_key((string) ($r['key'] ?? $r['label'] ?? '')) . '=' . (($v = $num($r['value'] ?? null)) !== null ? round($v, 4) : '');
    }
    sort($fpNew);
    try {
        $q = rrp_db()->prepare('SELECT id FROM health_examinations WHERE pet_id = ? AND examined_on = ?');
        $q->execute([(int) $pet['id'], $date]);
        $rq = rrp_db()->prepare('SELECT analyte_key, value_num FROM health_lab_results WHERE examination_id = ?');
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $exId) {
            $rq->execute([(int) $exId]);
            $fpOld = array_map(static fn($x) => $x['analyte_key'] . '=' . ($x['value_num'] !== null ? round((float) $x['value_num'], 4) : ''), $rq->fetchAll());
            sort($fpOld);
            if ($fpOld === $fpNew) {
                return ['ok' => true, 'duplicate' => true, 'date' => $date, 'counts' => ['low' => 0, 'normal' => 0, 'high' => 0, 'unknown' => 0], 'abnormal' => []];
            }
        }
    } catch (Throwable $e) {
    }

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
        // Change vs the previous report, and a sharp swing: 25% of the old value, or 30% of the normal span.
        $a['change_pct'] = null;
        $a['sharp'] = false;
        if (count($pts) >= 2) {
            $prev = $pts[count($pts) - 2]['value'];
            $d = $a['latest']['value'] - $prev;
            $a['change_pct'] = abs($prev) > 1e-9 ? round(100 * $d / abs($prev), 1) : null;
            $rl = $a['latest']['ref_low'];
            $rh = $a['latest']['ref_high'];
            $sp = ($rl !== null && $rh !== null) ? $rh - $rl : 0;
            $a['sharp'] = (abs($prev) > 1e-9 && abs($d) >= 0.25 * abs($prev)) || ($sp > 0 && abs($d) >= 0.3 * $sp);
        }
        // red: outside the normal range · amber: inside it but moving sharply or right at the edge · ok
        $a['alert'] = in_array($a['latest']['flag'], ['low', 'high'], true) ? 'red' : 'ok';
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
    foreach ($by as &$a) {
        if ($a['alert'] === 'ok' && ($a['sharp'] || $a['watch'])) {
            $a['alert'] = 'amber';
        }
    }
    unset($a);
    $list = array_values($by);
    // Abnormal first, then watch, then the rest alphabetically.
    usort($list, static function ($x, $y) {
        $rank = static fn($a) => ['red' => 0, 'amber' => 1, 'ok' => 2][$a['alert']];
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

/** One quiet line under each indicator: what it measures, in plain words (never a diagnosis). */
const RRP_LAB_INFO = [
    'alt' => 'A liver enzyme. It rises when liver cells are irritated.',
    'ast' => 'An enzyme from the liver and muscles. It rises with liver or muscle strain.',
    'alp' => 'An enzyme from the liver and bones. Young, growing pets run higher.',
    'ggt' => 'A liver and bile duct enzyme. It rises when bile flow is slowed.',
    'bun' => 'Waste from protein. It shows how well the kidneys clear the blood.',
    'creatinine' => 'A muscle waste product the kidneys filter. A key kidney marker.',
    'sdma' => 'An early kidney marker that rises before creatinine does.',
    'glucose' => 'Blood sugar. Stress, especially in cats, can push it up for a while.',
    'total_protein' => 'All the proteins in the blood. It reflects hydration, liver and immune health.',
    'albumin' => 'A blood protein made by the liver. It keeps fluid inside the vessels.',
    'globulin' => 'Blood proteins tied to the immune system.',
    'bilirubin' => 'A yellow pigment from old red blood cells, cleared by the liver.',
    'cholesterol' => 'A blood fat. It can rise with diet, thyroid or hormone changes.',
    'phosphorus' => 'A mineral for bones. The kidneys keep it in balance.',
    'calcium' => 'A mineral for bones, nerves and muscles.',
    'sodium' => 'A salt that balances water in the body.',
    'potassium' => 'A salt that keeps the heart and muscles working.',
    'chloride' => 'A salt that works with sodium to balance fluids.',
    't4' => 'A thyroid hormone. High in many older cats, low in some dogs.',
    'wbc' => 'White blood cells, the body’s defenders. They rise with infection or stress.',
    'rbc' => 'Red blood cells, which carry oxygen.',
    'hgb' => 'The protein in red blood cells that carries oxygen.',
    'hct' => 'The share of blood made of red cells. Low means anaemia, high can mean dehydration.',
    'plt' => 'Platelets, which help the blood clot.',
    'usg' => 'How concentrated the urine is. It shows how well the kidneys save water.',
];

function rrp_lab_info(string $key): ?string
{
    return RRP_LAB_INFO[$key] ?? null;
}

/** Test kinds an owner can enter by hand, with the indicators we suggest while typing. */
const RRP_LAB_KINDS = [
    'blood' => ['Blood test', ['ALT', 'AST', 'ALP', 'GGT', 'BUN', 'Creatinine', 'SDMA', 'Glucose', 'Total protein', 'Albumin', 'Globulin', 'Bilirubin', 'Cholesterol', 'Phosphorus', 'Calcium', 'Sodium', 'Potassium', 'Chloride', 'T4', 'WBC', 'RBC', 'HGB', 'HCT', 'PLT']],
    'stool' => ['Stool test', ['Giardia', 'Coccidia', 'Roundworm', 'Hookworm', 'Whipworm', 'Occult blood', 'pH']],
    'urine' => ['Urine test', ['USG', 'pH', 'Protein', 'Glucose', 'Ketones', 'Bilirubin', 'Blood', 'WBC', 'RBC', 'UPC ratio']],
];

/** "10-100", "10 to 100", "<5", ">3", "10" → [low, high] (either may be null). */
function rrp_lab_parse_range(string $s): array
{
    $s = trim(str_replace(['–', '—', '−', ','], ['-', '-', '-', '.'], rrp_digits_en($s)));
    if ($s === '') {
        return [null, null];
    }
    if (preg_match('/^(?:<|≤|up to|max)\s*(-?\d+(?:\.\d+)?)$/i', $s, $m)) {
        return [null, (float) $m[1]];
    }
    if (preg_match('/^(?:>|≥|min)\s*(-?\d+(?:\.\d+)?)$/i', $s, $m)) {
        return [(float) $m[1], null];
    }
    if (preg_match('/^(-?\d+(?:\.\d+)?)\s*(?:-|to)\s*(-?\d+(?:\.\d+)?)$/i', $s, $m)) {
        $a = (float) $m[1];
        $b = (float) $m[2];
        return [min($a, $b), max($a, $b)];
    }
    return [null, null];
}

/**
 * Manual entry: one date, one kind, several rows of [indicator, value, range].
 * @param list<array{label?:string,value?:string,range?:string,unit?:string}> $rows
 * @return array{ok:bool,error?:string,saved?:int}
 */
function rrp_labs_manual_add(array $owner, array $pet, string $kind, string $date, array $rows): array
{
    if (!isset(RRP_LAB_KINDS[$kind])) {
        return ['ok' => false, 'error' => 'Choose blood, stool or urine.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false || $date > rrp_sipo_local_now($owner)->format('Y-m-d')) {
        return ['ok' => false, 'error' => 'Pick the date of the test (not in the future).'];
    }
    $results = [];
    foreach ($rows as $r) {
        $label = trim((string) ($r['label'] ?? ''));
        $raw = str_replace(',', '.', trim(rrp_digits_en((string) ($r['value'] ?? ''))));
        if ($label === '' && $raw === '') {
            continue;
        }
        if ($label === '' || $raw === '') {
            return ['ok' => false, 'error' => 'Each row needs an indicator and a value.'];
        }
        [$lo, $hi] = rrp_lab_parse_range((string) ($r['range'] ?? ''));
        $row = ['label' => $label, 'unit' => trim((string) ($r['unit'] ?? '')), 'ref_low' => $lo, 'ref_high' => $hi];
        if (is_numeric($raw)) {
            $row['value'] = (float) $raw;
        } else {
            $row['value'] = null;
            $row['value_text'] = $raw; // e.g. "Positive", "Negative"
        }
        $results[] = $row;
    }
    if (!$results) {
        return ['ok' => false, 'error' => 'Add at least one indicator with its value.'];
    }
    $save = rrp_labs_save($owner, $pet, [
        'examined_on' => $date,
        'exam_type' => $kind === 'blood' ? 'blood' : ($kind === 'urine' ? 'urine' : 'other'),
        'title' => RRP_LAB_KINDS[$kind][0],
        'results' => $results,
    ], 'manual');
    return !empty($save['ok']) ? ['ok' => true, 'saved' => count($results)] : $save;
}

/** Saved tests for the manual list, newest first. */
function rrp_labs_exam_list(int $petId, int $limit = 30): array
{
    try {
        $st = rrp_db()->prepare('SELECT id, title, examined_on, source FROM health_examinations WHERE pet_id = ? ORDER BY examined_on DESC, id DESC LIMIT ' . max(1, $limit));
        $st->execute([$petId]);
        $exams = $st->fetchAll();
        $q = rrp_db()->prepare('SELECT analyte_label, value_num, value_text, unit, flag FROM health_lab_results WHERE examination_id = ? ORDER BY id ASC');
        foreach ($exams as &$e) {
            $q->execute([(int) $e['id']]);
            $e['results'] = $q->fetchAll();
        }
        return $exams;
    } catch (Throwable $e) {
        return [];
    }
}

function rrp_labs_exam_delete(int $petId, int $examId): bool
{
    $st = rrp_db()->prepare('DELETE FROM health_examinations WHERE id = ? AND pet_id = ?');
    $st->execute([$examId, $petId]);
    return $st->rowCount() > 0;
}

const RRP_LAB_SHOW = 'Show me the dashboard';
const RRP_LAB_ANALYSE = 'Analyse these results';

/** After lab files were read: ask whether to open the dashboard (always the same offer). */
function rrp_labs_offer(array &$ctx, array $reply, string $body): array
{
    if (!preg_match('/^📎 Lab report/mu', $body) || !empty($reply['choices'])) {
        return $reply;
    }
    $reply['text'] = rtrim((string) $reply['text']) . "\n\nWant to see the lab dashboard?";
    $reply['choices'] = [RRP_LAB_SHOW, RRP_LAB_ANALYSE];
    return $reply;
}

/** The dashboard button is answered by code (a link); "Analyse" goes to the model as a normal message. */
function rrp_labs_intercept(array &$ctx, string $body): ?array
{
    if (strcasecmp(trim($body), RRP_LAB_SHOW) !== 0 || empty($ctx['pet'])) {
        return null;
    }
    $ctx['links'][] = ['url' => 'care.php?pet=' . (int) $ctx['pet']['id'] . '#sec-labs', 'label' => 'Open the lab dashboard'];
    return ['text' => 'Here it is 💙 Tap the button to see every indicator and its trend. I am here if anything looks confusing.', 'tokens' => 0, 'mascot' => 'happy', 'tools' => [], 'model' => null];
}
