<?php
declare(strict_types=1);

/**
 * Food (brief §B): daily calories, foods from label photos, meals with
 * "what / how much / liked?", calories today, and supply run-out.
 *
 * Energy: RER = 70 × kg^0.75; MER multipliers from the Merck Veterinary Manual
 * (nutritional requirements of small animals). A number from the vet always wins.
 */

const RRP_FOOD_SUPPLY_WARN_DAYS = 3;

/** Danger words SIPO flags on any label (always "check with your vet"). */
const RRP_FOOD_DANGER = [
    'xylitol' => 'Xylitol is toxic to dogs.',
    'chocolate' => 'Chocolate is toxic to dogs and cats.',
    'cocoa' => 'Cocoa is toxic to dogs and cats.',
    'grape' => 'Grapes and raisins can cause kidney failure in dogs.',
    'raisin' => 'Grapes and raisins can cause kidney failure in dogs.',
    'onion' => 'Onion damages red blood cells in dogs and cats.',
    'garlic' => 'Garlic damages red blood cells in dogs and cats.',
    'macadamia' => 'Macadamia nuts are toxic to dogs.',
    'caffeine' => 'Caffeine is toxic to pets.',
    'alcohol' => 'Alcohol is toxic to pets.',
];

/**
 * @return array{kcal:?int, source:string, factor:?float, rer:?int, note:string}
 */
function rrp_food_daily_kcal(array $pet): array
{
    if (!empty($pet['vet_kcal_per_day'])) {
        return ['kcal' => (int) $pet['vet_kcal_per_day'], 'source' => 'vet', 'factor' => null, 'rer' => null,
            'note' => 'Daily target set by your vet.'];
    }
    $kg = (float) ($pet['weight_kg'] ?? 0);
    $type = (string) ($pet['type'] ?? '');
    if ($kg <= 0 || !in_array($type, ['dog', 'cat'], true)) {
        return ['kcal' => null, 'source' => 'unknown', 'factor' => null, 'rer' => null,
            'note' => $kg <= 0 ? 'Weight needed to estimate calories.' : 'No standard formula for this kind of pet — ask your vet.'];
    }
    $rer = 70 * ($kg ** 0.75);
    $months = rrp_food_age_months($pet);
    $neutered = isset($pet['neutered']) && $pet['neutered'] !== null ? (int) $pet['neutered'] === 1 : null;
    $lowActivity = ($pet['activity'] ?? 'normal') === 'low';

    if ($type === 'dog') {
        $factor = match (true) {
            $months !== null && $months < 4 => 3.0,
            $months !== null && $months < 12 => 2.0,
            $lowActivity => 1.4,
            $neutered === false => 1.8,
            default => 1.6,
        };
    } else {
        $factor = match (true) {
            $months !== null && $months < 12 => 2.5,
            $lowActivity => 1.0,
            $neutered === false => 1.4,
            default => 1.2,
        };
    }
    return [
        'kcal' => (int) round($rer * $factor),
        'source' => 'estimate',
        'factor' => $factor,
        'rer' => (int) round($rer),
        'note' => 'Estimate (resting energy × ' . $factor . ', Merck Veterinary Manual). A starting point — your vet may adjust it.',
    ];
}

function rrp_food_age_months(array $pet): ?int
{
    if (!empty($pet['birth_date'])) {
        try {
            $d = (new DateTimeImmutable((string) $pet['birth_date']))->diff(new DateTimeImmutable('today'));
            return $d->y * 12 + $d->m;
        } catch (Throwable $e) {
        }
    }
    if (isset($pet['age_years']) && $pet['age_years'] !== null && $pet['age_years'] !== '') {
        return (int) round((float) $pet['age_years'] * 12 + (int) ($pet['age_months'] ?? 0));
    }
    return null;
}

/** Ingredient warnings from label text. @return list<string> */
function rrp_food_warnings(string $text): array
{
    $t = mb_strtolower($text);
    $out = [];
    foreach (RRP_FOOD_DANGER as $word => $msg) {
        if (str_contains($t, $word)) {
            $out[$msg] = $msg;
        }
    }
    return array_values($out);
}

/** @return list<array> */
function rrp_foods_for_pet(int $petId, bool $currentOnly = true): array
{
    try {
        $stmt = rrp_db()->prepare('SELECT * FROM pet_foods WHERE pet_id = ?' . ($currentOnly ? ' AND is_current = 1' : '') . ' ORDER BY food_kind, id DESC');
        $stmt->execute([$petId]);
        return array_map('rrp_food_decorate', $stmt->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/** Adds kcal_per_g, supply estimate. */
function rrp_food_decorate(array $f): array
{
    $f['warnings'] = $f['warnings_json'] ? (json_decode((string) $f['warnings_json'], true) ?: []) : [];
    $f['kcal_per_g'] = null;
    if (!empty($f['kcal_per_kg'])) {
        $f['kcal_per_g'] = (float) $f['kcal_per_kg'] / 1000;
    } elseif (!empty($f['kcal_per_cup']) && !empty($f['grams_per_cup'])) {
        $f['kcal_per_g'] = (float) $f['kcal_per_cup'] / (float) $f['grams_per_cup'];
    }
    $f['runs_out_on'] = null;
    $f['days_left'] = null;
    $daily = (float) ($f['daily_grams'] ?? 0);
    if ($daily > 0) {
        $base = null;
        $from = null;
        if (!empty($f['remaining_grams']) && !empty($f['remaining_set_on'])) {
            $base = (float) $f['remaining_grams'];
            $from = (string) $f['remaining_set_on'];
        } elseif (!empty($f['package_grams']) && !empty($f['opened_on'])) {
            $base = (float) $f['package_grams'];
            $from = (string) $f['opened_on'];
        }
        if ($base !== null) {
            $days = (int) floor($base / $daily);
            $out = (new DateTimeImmutable($from))->modify('+' . $days . ' days');
            $f['runs_out_on'] = $out->format('Y-m-d');
            $f['days_left'] = (int) (new DateTimeImmutable('today'))->diff($out)->format('%r%a');
        }
    }
    return $f;
}

/**
 * Save a food (from a label photo or typed). Matches an existing current food by name.
 * @return array{ok:bool, id?:int, warnings?:list<string>, grams_per_day?:?int, error?:string}
 */
function rrp_food_save(array $owner, array $pet, array $d): array
{
    $name = trim((string) ($d['name'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'error' => 'Food name needed.'];
    }
    $kind = in_array($d['kind'] ?? '', ['dry', 'wet', 'treat', 'raw', 'homemade', 'other'], true) ? $d['kind'] : 'dry';
    $warnings = array_values(array_unique(array_merge(
        rrp_food_warnings((string) ($d['ingredients'] ?? '')),
        array_map('strval', (array) ($d['warnings'] ?? []))
    )));
    $num = static fn($v, $max) => is_numeric($v) && $v > 0 && $v < $max ? (float) $v : null;
    $row = [
        'kcal_per_kg' => $num($d['kcal_per_kg'] ?? null, 10000),
        'kcal_per_cup' => $num($d['kcal_per_cup'] ?? null, 2000),
        'grams_per_cup' => $num($d['grams_per_cup'] ?? null, 500),
        'package_grams' => $num($d['package_grams'] ?? null, 100000),
    ];
    $stmt = rrp_db()->prepare('SELECT id FROM pet_foods WHERE pet_id = ? AND is_current = 1 AND LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([(int) $pet['id'], $name]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    if ($id) {
        $sets = ['food_kind = ?', 'warnings_json = ?'];
        $vals = [$kind, json_encode($warnings)];
        foreach ($row as $k => $v) {
            if ($v !== null) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
        }
        $vals[] = $id;
        rrp_db()->prepare('UPDATE pet_foods SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    } else {
        rrp_db()->prepare(
            'INSERT INTO pet_foods (pet_id, name, food_kind, kcal_per_kg, kcal_per_cup, grams_per_cup, package_grams, opened_on, warnings_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([(int) $pet['id'], mb_substr($name, 0, 160), $kind, $row['kcal_per_kg'], $row['kcal_per_cup'],
            $row['grams_per_cup'], $row['package_grams'], !empty($d['just_opened']) ? $today : null, json_encode($warnings)]);
        $id = (int) rrp_db()->lastInsertId();
    }
    $food = rrp_food_get((int) $pet['id'], $id);
    $grams = rrp_food_grams_per_day($pet, $food);
    if ($grams !== null && $kind !== 'treat') {
        rrp_db()->prepare('UPDATE pet_foods SET daily_grams = COALESCE(daily_grams, ?) WHERE id = ?')->execute([$grams, $id]);
    }
    $coins = !empty($d['kcal_per_kg']) || !empty($d['kcal_per_cup'])
        ? rrp_coin_award((int) $owner['id'], 'food_label', 'Food details for ' . $pet['name'], 'pet_food', $id, (int) $pet['id']) : 0;
    return ['ok' => true, 'id' => $id, 'warnings' => $warnings, 'grams_per_day' => $grams, 'coins' => $coins];
}

function rrp_food_get(int $petId, int $id): ?array
{
    $stmt = rrp_db()->prepare('SELECT * FROM pet_foods WHERE id = ? AND pet_id = ? LIMIT 1');
    $stmt->execute([$id, $petId]);
    $r = $stmt->fetch();
    return $r ? rrp_food_decorate($r) : null;
}

/** Grams/day of this food to meet the daily target (if it were the only food). */
function rrp_food_grams_per_day(array $pet, ?array $food): ?int
{
    $need = rrp_food_daily_kcal($pet)['kcal'];
    if (!$food || !$need || empty($food['kcal_per_g'])) {
        return null;
    }
    return (int) round($need / (float) $food['kcal_per_g']);
}

/**
 * Log a meal or treat. amount in grams, or cups (converted with grams_per_cup / kcal_per_cup).
 * @return array{ok:bool, kcal?:?float, today?:array, error?:string}
 */
function rrp_food_log_meal(array $owner, array $pet, array $d): array
{
    $foodId = (int) ($d['food_id'] ?? 0);
    $food = $foodId ? rrp_food_get((int) $pet['id'], $foodId) : null;
    if (!$food && !empty($d['food_name'])) {
        foreach (rrp_foods_for_pet((int) $pet['id']) as $f) {
            if (mb_strtolower($f['name']) === mb_strtolower((string) $d['food_name'])) {
                $food = $f;
            }
        }
    }
    $grams = is_numeric($d['grams'] ?? null) ? (float) $d['grams'] : null;
    $kcal = is_numeric($d['kcal'] ?? null) ? (float) $d['kcal'] : null;
    if ($grams === null && is_numeric($d['cups'] ?? null) && $food) {
        if (!empty($food['grams_per_cup'])) {
            $grams = (float) $d['cups'] * (float) $food['grams_per_cup'];
        } elseif (!empty($food['kcal_per_cup']) && $kcal === null) {
            $kcal = (float) $d['cups'] * (float) $food['kcal_per_cup'];
        }
    }
    if ($kcal === null && $grams !== null && $food && $food['kcal_per_g']) {
        $kcal = round($grams * (float) $food['kcal_per_g'], 1);
    }
    $liked = in_array($d['liked'] ?? null, ['loved', 'ok', 'refused'], true) ? $d['liked'] : null;
    $now = rrp_sipo_local_now($owner);
    rrp_db()->prepare(
        'INSERT INTO feeding_logs (pet_id, food_id, fed_on, grams, kcal, liked, note, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([(int) $pet['id'], $food['id'] ?? null, $now->format('Y-m-d'), $grams, $kcal, $liked,
        isset($d['note']) ? mb_substr((string) $d['note'], 0, 255) : (empty($food) && !empty($d['food_name']) ? mb_substr((string) $d['food_name'], 0, 255) : null)]);
    $coins = rrp_coin_award((int) $owner['id'], 'meal_log', 'Meal logged for ' . $pet['name'], null, null, (int) $pet['id']);
    return ['ok' => true, 'kcal' => $kcal, 'today' => rrp_food_today($owner, $pet), 'coins' => $coins];
}

/** @return array{eaten_kcal:float, target_kcal:?int, meals:int, unknown_meals:int, percent:?int} */
function rrp_food_today(array $owner, array $pet): array
{
    $day = rrp_sipo_local_now($owner)->format('Y-m-d');
    $stmt = rrp_db()->prepare(
        "SELECT COUNT(*) AS meals, COALESCE(SUM(kcal),0) AS kcal, SUM(kcal IS NULL AND (liked IS NULL OR liked <> 'refused')) AS unknown
         FROM feeding_logs WHERE pet_id = ? AND fed_on = ? AND logged_at IS NOT NULL"
    );
    $stmt->execute([(int) $pet['id'], $day]);
    $r = $stmt->fetch() ?: ['meals' => 0, 'kcal' => 0, 'unknown' => 0];
    $target = rrp_food_daily_kcal($pet)['kcal'];
    return [
        'eaten_kcal' => round((float) $r['kcal']),
        'target_kcal' => $target,
        'meals' => (int) $r['meals'],
        'unknown_meals' => (int) $r['unknown'],
        'percent' => $target ? (int) round(100 * (float) $r['kcal'] / $target) : null,
    ];
}

/** Liked / refused counts per food over 30 days. @return list<array{name:string, loved:int, ok:int, refused:int}> */
function rrp_food_preferences(int $petId): array
{
    $stmt = rrp_db()->prepare(
        "SELECT COALESCE(f.name, l.note, 'Other') AS name,
                SUM(l.liked = 'loved') AS loved, SUM(l.liked = 'ok') AS ok, SUM(l.liked = 'refused') AS refused
         FROM feeding_logs l LEFT JOIN pet_foods f ON f.id = l.food_id
         WHERE l.pet_id = ? AND l.liked IS NOT NULL AND l.fed_on >= (CURDATE() - INTERVAL 30 DAY)
         GROUP BY name ORDER BY loved DESC, refused ASC LIMIT 8"
    );
    $stmt->execute([$petId]);
    return array_map(static fn($r) => ['name' => $r['name'], 'loved' => (int) $r['loved'], 'ok' => (int) $r['ok'], 'refused' => (int) $r['refused']], $stmt->fetchAll());
}

/** Owner says how much is left (or that a new bag was opened). */
function rrp_food_update_supply(array $owner, array $pet, int $foodId, ?float $remainingGrams, bool $newPackage = false, ?float $dailyGrams = null): array
{
    $food = rrp_food_get((int) $pet['id'], $foodId);
    if (!$food) {
        return ['ok' => false, 'error' => 'Food not found.'];
    }
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    if ($newPackage) {
        rrp_db()->prepare('UPDATE pet_foods SET opened_on = ?, remaining_grams = NULL, remaining_set_on = NULL WHERE id = ?')->execute([$today, $foodId]);
    } elseif ($remainingGrams !== null && $remainingGrams >= 0) {
        rrp_db()->prepare('UPDATE pet_foods SET remaining_grams = ?, remaining_set_on = ? WHERE id = ?')->execute([(int) round($remainingGrams), $today, $foodId]);
    }
    if ($dailyGrams !== null && $dailyGrams > 0 && $dailyGrams < 5000) {
        rrp_db()->prepare('UPDATE pet_foods SET daily_grams = ? WHERE id = ?')->execute([$dailyGrams, $foodId]);
    }
    $f = rrp_food_get((int) $pet['id'], $foodId);
    return ['ok' => true, 'runs_out_on' => $f['runs_out_on'] ?? null, 'days_left' => $f['days_left'] ?? null];
}

/** Food prompt block for SIPO. */
function rrp_food_prompt_block(array $owner, array $pet): string
{
    $need = rrp_food_daily_kcal($pet);
    $today = rrp_food_today($owner, $pet);
    $lines = ['Daily target: ' . ($need['kcal'] ? $need['kcal'] . ' kcal (' . $need['source'] . ')' : 'unknown — ' . $need['note'])];
    if ($need['kcal']) {
        // QA v3 B31: hand SIPO the grams so it gives them instead of refusing.
        $current = rrp_foods_for_pet((int) $pet['id'])[0] ?? null;
        $g = $current ? rrp_food_grams_per_day($pet, $current) : null;
        $lines[] = $g
            ? "Daily amount: about {$g} g/day of {$current['name']} (split over the meals)."
            : 'Daily amount: about ' . (int) round($need['kcal'] / 3.6) . ' g/day of a typical dry food (≈3,600 kcal/kg); the exact grams depend on the bag\'s kcal/kg, so ask for it to fine-tune.';
    }
    $lines[] = 'Eaten today: ' . $today['eaten_kcal'] . ' kcal in ' . $today['meals'] . ' logged meals'
        . ($today['unknown_meals'] ? " ({$today['unknown_meals']} without calories)" : '');
    foreach (rrp_foods_for_pet((int) $pet['id']) as $f) {
        $l = "- [food_id {$f['id']}] {$f['name']} ({$f['food_kind']})";
        if ($f['kcal_per_kg']) {
            $l .= ", {$f['kcal_per_kg']} kcal/kg";
        } elseif ($f['kcal_per_cup']) {
            $l .= ", {$f['kcal_per_cup']} kcal/cup";
        }
        if ($f['daily_grams']) {
            $l .= ", ~{$f['daily_grams']} g/day";
        }
        if ($f['runs_out_on']) {
            $l .= ", runs out ~{$f['runs_out_on']} ({$f['days_left']} days)";
        }
        if ($f['warnings']) {
            $l .= ' ⚠ ' . implode(' ', $f['warnings']);
        }
        $lines[] = $l;
    }
    $prefs = rrp_food_preferences((int) $pet['id']);
    if ($prefs) {
        $lines[] = 'Likes (30 days): ' . implode('; ', array_map(static fn($p) => "{$p['name']} loved {$p['loved']}/refused {$p['refused']}", $prefs));
    }
    return implode("\n", $lines);
}

/** Cron: warn RRP_FOOD_SUPPLY_WARN_DAYS before a bag runs out (once per bag). */
function rrp_food_supply_tick(): array
{
    $stats = ['warned' => 0];
    $rows = rrp_db()->query(
        "SELECT f.*, p.owner_id, p.name AS pet_name FROM pet_foods f JOIN pets p ON p.id = f.pet_id AND p.is_active = 1
         WHERE f.is_current = 1 AND f.daily_grams > 0 AND f.food_kind <> 'treat'"
    )->fetchAll();
    foreach ($rows as $row) {
        $f = rrp_food_decorate($row);
        if ($f['days_left'] === null || $f['days_left'] > RRP_FOOD_SUPPLY_WARN_DAYS) {
            continue;
        }
        $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . (int) $row['owner_id'])->fetch();
        if (!$owner || rrp_sipo_is_quiet_hour((int) rrp_sipo_local_now($owner)->format('G'))) {
            continue;
        }
        if ((string) ($row['supply_warned_for'] ?? '') === (string) $f['runs_out_on']) {
            continue;
        }
        $text = $f['days_left'] <= 0
            ? "By my count {$row['pet_name']}'s {$f['name']} has run out. Do you still have some? Tell me roughly how much is left so I can keep track."
            : "{$row['pet_name']}'s {$f['name']} should last about {$f['days_left']} more day" . ($f['days_left'] === 1 ? '' : 's') . ". Time to pick up a new bag? If you still have plenty, tell me how much is left.";
        $thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $row['pet_id']);
        if ($thread) {
            rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'listening', 'proactive' => 'supply']);
        }
        rrp_try_web_push((int) $owner['id'], 'SIPO · ' . $row['pet_name'], $text, rrp_app_url('si-chat.php?pet=' . (int) $row['pet_id']));
        rrp_db()->prepare('UPDATE pet_foods SET supply_warned_for = ? WHERE id = ?')->execute([$f['runs_out_on'], (int) $f['id']]);
        $stats['warned']++;
    }
    return $stats;
}

/** Replace the meal routine with these local times. */
function rrp_food_set_meal_times(array $pet, array $times, ?string $portion): array
{
    $clean = [];
    foreach (array_slice($times, 0, 6) as $t) {
        $n = rrp_normalize_time_input((string) $t);
        if ($n !== null) {
            $clean[] = $n;
        }
    }
    if (!$clean) {
        return ['ok' => false, 'error' => 'Times must be HH:MM.'];
    }
    sort($clean);
    $pdo = rrp_db();
    $pdo->prepare('UPDATE feeding_schedules SET active = 0 WHERE pet_id = ?')->execute([(int) $pet['id']]);
    $ins = $pdo->prepare('INSERT INTO feeding_schedules (pet_id, label, feed_time, portion_note, active, sort_order) VALUES (?, ?, ?, ?, 1, ?)');
    foreach ($clean as $i => $t) {
        $h = (int) substr($t, 0, 2);
        $label = $h < 11 ? 'Breakfast' : ($h < 16 ? 'Lunch' : 'Dinner');
        $ins->execute([(int) $pet['id'], $label, $t, $portion ? mb_substr($portion, 0, 255) : null, $i]);
    }
    return ['ok' => true, 'meals' => count($clean)];
}

/**
 * Cron: ~30 min after each meal time SIPO asks what/how much/liked (once per meal per day),
 * unless a meal was already logged in that window.
 */
function rrp_food_meal_tick(): array
{
    $stats = ['asked' => 0];
    $rows = rrp_db()->query(
        "SELECT s.*, p.owner_id, p.name AS pet_name FROM feeding_schedules s JOIN pets p ON p.id = s.pet_id AND p.is_active = 1 WHERE s.active = 1"
    )->fetchAll();
    foreach ($rows as $s) {
        $msg = null;
        $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . (int) $s['owner_id'])->fetch();
        if (!$owner || $owner['status'] !== 'active') {
            continue;
        }
        $now = rrp_sipo_local_now($owner);
        [$h, $m] = array_map('intval', explode(':', (string) $s['feed_time']));
        $mealAt = $now->setTime($h, $m);
        $mins = (int) floor(($now->getTimestamp() - $mealAt->getTimestamp()) / 60);
        if ($mins < 25 || $mins > 120 || rrp_sipo_is_quiet_hour((int) $now->format('G'))) {
            continue;
        }
        $kind = 'meal_' . (int) $s['id'];
        $asked = rrp_db()->prepare('SELECT COUNT(*) FROM sipo_nudges WHERE pet_id = ? AND kind = ? AND local_day = ?');
        $asked->execute([(int) $s['pet_id'], $kind, $now->format('Y-m-d')]);
        if ((int) $asked->fetchColumn() > 0) {
            continue;
        }
        $logged = rrp_db()->prepare('SELECT COUNT(*) FROM feeding_logs WHERE pet_id = ? AND logged_at >= ?');
        $logged->execute([(int) $s['pet_id'], $mealAt->modify('-60 minutes')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
        $thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $s['pet_id']);
        if (!$thread) {
            continue;
        }
        if ((int) $logged->fetchColumn() === 0) {
            $text = "How did {$s['pet_name']}'s " . strtolower((string) $s['label']) . " go? What did they eat, roughly how much, and did they like it?";
            $msg = rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'listening', 'proactive' => 'meal']);
            rrp_try_web_push((int) $owner['id'], 'SIPO · ' . $s['pet_name'], $text, rrp_app_url('si-chat.php?pet=' . (int) $s['pet_id']));
            $stats['asked']++;
        }
        rrp_sipo_log_nudge($owner, (int) $s['pet_id'], (int) $thread['id'], (int) ($msg['id'] ?? 0), $kind, true);
    }
    return $stats;
}
