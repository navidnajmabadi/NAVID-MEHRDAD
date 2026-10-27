<?php
declare(strict_types=1);

/**
 * Daily life (brief §C): bathroom pattern vs this pet's own history, walks with
 * goal / streak / route, and the best walk window from the forecast.
 * There is no universal "normal" number of pees or poops — we compare the pet to itself.
 */

const RRP_BATH_KINDS = ['pee', 'poop', 'accident', 'vomit'];
const RRP_BATH_LEARN_DAYS = 4; // logging days needed before comparing
const RRP_WALK_MAX_POINTS = 400;
const RRP_HOT_C = 28;
const RRP_COLD_C = -5;

// -----------------------------------------------------------------------------
// Bathroom
// -----------------------------------------------------------------------------

/** @return array{ok:bool, error?:string, summary?:array} */
function rrp_bath_log(array $owner, array $pet, string $kind, ?string $stool = null, ?string $note = null, string $source = 'chat', ?string $whenLocal = null): array
{
    if (!in_array($kind, RRP_BATH_KINDS, true)) {
        return ['ok' => false, 'error' => 'kind must be pee, poop, accident or vomit.'];
    }
    $stool = in_array($stool, ['normal', 'soft', 'diarrhea', 'hard', 'blood', 'mucus'], true) ? $stool : null;
    $tz = rrp_sipo_owner_tz($owner);
    $at = new DateTimeImmutable('now', $tz);
    if ($whenLocal && preg_match('/^\d{1,2}:\d{2}$/', $whenLocal)) {
        [$h, $m] = array_map('intval', explode(':', $whenLocal));
        $at = $at->setTime($h, $m);
        if ($at > new DateTimeImmutable('now', $tz)) {
            $at = $at->modify('-1 day'); // "at 23:00" said after midnight
        }
    }
    rrp_db()->prepare(
        'INSERT INTO pet_bathroom_logs (pet_id, kind, stool, note, source, logged_at, local_day) VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        (int) $pet['id'], $kind, $stool, $note ? mb_substr($note, 0, 255) : null,
        in_array($source, ['chat', 'button', 'manual'], true) ? $source : 'chat',
        $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $at->format('Y-m-d'),
    ]);
    $coins = rrp_coin_award((int) $owner['id'], 'bathroom_log', 'Bathroom log for ' . $pet['name'], null, null, (int) $pet['id']);
    return ['ok' => true, 'summary' => rrp_bath_summary($owner, $pet, false), 'coins' => $coins];
}

function rrp_median(array $xs): float
{
    if (!$xs) {
        return 0.0;
    }
    sort($xs);
    $n = count($xs);
    return $n % 2 ? (float) $xs[intdiv($n, 2)] : ($xs[$n / 2 - 1] + $xs[$n / 2]) / 2;
}

/**
 * @return array{days:list<array>, today:array, usual:array, learning:bool, signals:list<array{key:string,level:string,text:string}>, last_poop_hours:?int}
 */
function rrp_bath_summary(array $owner, array $pet, bool $withWeather = true): array
{
    $tz = rrp_sipo_owner_tz($owner);
    $today = new DateTimeImmutable('today', $tz);
    $from = $today->modify('-14 days')->format('Y-m-d');
    $stmt = rrp_db()->prepare(
        "SELECT local_day, kind, stool, logged_at FROM pet_bathroom_logs WHERE pet_id = ? AND local_day >= ? ORDER BY logged_at"
    );
    $stmt->execute([(int) $pet['id'], $from]);
    $rows = $stmt->fetchAll();

    $days = [];
    for ($i = 14; $i >= 0; $i--) {
        $d = $today->modify("-{$i} days")->format('Y-m-d');
        $days[$d] = ['date' => $d, 'pee' => 0, 'poop' => 0, 'accident' => 0, 'vomit' => 0, 'diarrhea' => 0, 'blood' => 0];
    }
    foreach ($rows as $r) {
        $d = (string) $r['local_day'];
        if (!isset($days[$d])) {
            continue;
        }
        $days[$d][$r['kind']]++;
        if (in_array($r['stool'], ['diarrhea', 'soft'], true) && $r['stool'] === 'diarrhea') {
            $days[$d]['diarrhea']++;
        }
        if ($r['stool'] === 'blood') {
            $days[$d]['blood']++;
        }
    }
    $todayKey = $today->format('Y-m-d');
    $past = array_filter($days, static fn($d) => $d['date'] !== $todayKey && ($d['pee'] + $d['poop'] + $d['accident']) > 0);
    $learning = count($past) < RRP_BATH_LEARN_DAYS;
    $usual = [
        'pee' => rrp_median(array_column($past, 'pee')),
        'poop' => rrp_median(array_column($past, 'poop')),
        'logging_days' => count($past),
    ];
    $t = $days[$todayKey];

    // Last poop (any day) for the "none in 3 days" signal.
    $lp = rrp_db()->prepare("SELECT MAX(logged_at) FROM pet_bathroom_logs WHERE pet_id = ? AND kind = 'poop'");
    $lp->execute([(int) $pet['id']]);
    $lastPoop = $lp->fetchColumn();
    $lastPoopHours = $lastPoop ? (int) floor((time() - strtotime((string) $lastPoop . ' UTC')) / 3600) : null;

    $signals = [];
    $hour = (int) (new DateTimeImmutable('now', $tz))->format('G');
    $hot = null;
    if ($withWeather) {
        $c = rrp_sipo_owner_coords($owner);
        $w = $c['lat'] !== null ? rrp_sipo_weather($c['lat'], $c['lng']) : null;
        $hot = $w ? ($w['max_c'] ?? $w['temp_c']) >= RRP_HOT_C : null;
    }
    if (!$learning && $usual['pee'] > 0 && $hour >= 12 && $t['pee'] >= max(3, 2 * $usual['pee'])) {
        $signals[] = ['key' => 'more_pee', 'level' => 'watch', 'text' => 'Peeing much more than usual today'
            . ($hot ? ' — it is hot, so more drinking is expected; keep water out and watch.' : '. Watch thirst; if it continues, mention it to the vet.')];
    }
    if (!$learning && $usual['pee'] >= 2 && $hour >= 18 && $t['pee'] === 0 && $past) {
        $signals[] = ['key' => 'no_pee_logged', 'level' => 'watch', 'text' => 'No pee logged today yet.'];
    }
    $last3 = array_slice(array_values($days), -3);
    if (count(array_filter($last3, static fn($d) => $d['diarrhea'] > 0)) >= 2) {
        $signals[] = ['key' => 'diarrhea', 'level' => 'vet', 'text' => 'Diarrhoea on 2 of the last 3 days — worth calling the vet.'];
    }
    if (array_sum(array_column(array_slice(array_values($days), -2), 'blood')) > 0) {
        $signals[] = ['key' => 'blood', 'level' => 'vet', 'text' => 'Blood seen in the last 2 days — call the vet.'];
    }
    if ($usual['poop'] >= 1 && $lastPoopHours !== null && $lastPoopHours >= 72) {
        $signals[] = ['key' => 'no_poop', 'level' => 'vet', 'text' => 'No poop logged for 3+ days.'];
    }
    if ($t['accident'] >= 2) {
        $signals[] = ['key' => 'accidents', 'level' => 'watch', 'text' => 'Two or more accidents today — stress, a change at home, or a urinary problem can cause this.'];
    }
    if ($t['vomit'] >= 2) {
        $signals[] = ['key' => 'vomit', 'level' => 'vet', 'text' => 'Vomited more than once today — call the vet if it continues or the pet is lethargic.'];
    }

    return [
        'days' => array_values($days),
        'today' => $t,
        'usual' => $usual,
        'learning' => $learning,
        'signals' => $signals,
        'last_poop_hours' => $lastPoopHours,
        'hot' => $hot,
    ];
}

// -----------------------------------------------------------------------------
// Walks
// -----------------------------------------------------------------------------

/**
 * @param list<array{0:float,1:float}>|null $route
 * @return array{ok:bool, error?:string, id?:int, summary?:array}
 */
function rrp_walk_log(array $owner, array $pet, int $minutes, ?int $distanceM = null, ?array $route = null, string $source = 'chat', ?string $startedAtUtc = null, ?string $note = null): array
{
    if ($minutes < 1 || $minutes > 600) {
        return ['ok' => false, 'error' => 'Minutes must be between 1 and 600.'];
    }
    if ($distanceM !== null && ($distanceM < 0 || $distanceM > 60000)) {
        $distanceM = null;
    }
    $route = $route ? rrp_walk_clean_route($route) : null;
    if ($route && $distanceM === null) {
        $distanceM = rrp_walk_route_meters($route);
    }
    $tz = rrp_sipo_owner_tz($owner);
    $start = $startedAtUtc && strtotime($startedAtUtc . ' UTC')
        ? new DateTimeImmutable($startedAtUtc, new DateTimeZone('UTC'))
        : (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify("-{$minutes} minutes");
    $c = rrp_sipo_owner_coords($owner);
    $w = $c['lat'] !== null ? rrp_sipo_weather($c['lat'], $c['lng']) : null;
    rrp_db()->prepare(
        'INSERT INTO pet_walks (pet_id, started_at, local_day, minutes, distance_m, route_json, source, weather_text, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        (int) $pet['id'], $start->format('Y-m-d H:i:s'), $start->setTimezone($tz)->format('Y-m-d'), $minutes, $distanceM,
        $route ? json_encode($route) : null, in_array($source, ['gps', 'chat', 'manual'], true) ? $source : 'chat',
        $w['text'] ?? null, $note ? mb_substr($note, 0, 255) : null,
    ]);
    $id = (int) rrp_db()->lastInsertId();
    $coins = $minutes >= 10 ? rrp_coin_award((int) $owner['id'], 'walk', 'Walk with ' . $pet['name'], 'walk', $id, (int) $pet['id']) : 0;
    return ['ok' => true, 'id' => $id, 'summary' => rrp_walk_summary($owner, $pet), 'coins' => $coins];
}

/** Keep valid points, cap count by uniform sampling (always keeps start/end). */
function rrp_walk_clean_route(array $route): array
{
    $pts = [];
    foreach ($route as $p) {
        if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])
            && abs((float) $p[0]) <= 90 && abs((float) $p[1]) <= 180) {
            $pts[] = [round((float) $p[0], 5), round((float) $p[1], 5)];
        }
    }
    $n = count($pts);
    if ($n <= RRP_WALK_MAX_POINTS) {
        return $pts;
    }
    $out = [];
    $step = ($n - 1) / (RRP_WALK_MAX_POINTS - 1);
    for ($i = 0; $i < RRP_WALK_MAX_POINTS; $i++) {
        $out[] = $pts[(int) round($i * $step)];
    }
    return $out;
}

function rrp_walk_route_meters(array $route): int
{
    $m = 0.0;
    for ($i = 1, $n = count($route); $i < $n; $i++) {
        $m += rrp_haversine_km($route[$i - 1][0], $route[$i - 1][1], $route[$i][0], $route[$i][1]) * 1000;
    }
    return (int) round($m);
}

/**
 * @return array{goal:?int, today_minutes:int, today_km:float, week:list<array>, week_minutes:int, week_km:float, streak:int, last:?array}
 */
function rrp_walk_summary(array $owner, array $pet): array
{
    $tz = rrp_sipo_owner_tz($owner);
    $today = new DateTimeImmutable('today', $tz);
    $stmt = rrp_db()->prepare(
        'SELECT local_day, SUM(minutes) AS minutes, SUM(COALESCE(distance_m,0)) AS meters, COUNT(*) AS walks
         FROM pet_walks WHERE pet_id = ? AND local_day >= ? GROUP BY local_day'
    );
    $stmt->execute([(int) $pet['id'], $today->modify('-30 days')->format('Y-m-d')]);
    $by = [];
    foreach ($stmt->fetchAll() as $r) {
        $by[(string) $r['local_day']] = $r;
    }
    $goal = !empty($pet['walk_goal_minutes']) ? (int) $pet['walk_goal_minutes'] : null;
    $week = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = $today->modify("-{$i} days");
        $r = $by[$d->format('Y-m-d')] ?? null;
        $week[] = ['date' => $d->format('Y-m-d'), 'dow' => $d->format('D'), 'minutes' => (int) ($r['minutes'] ?? 0), 'km' => round((int) ($r['meters'] ?? 0) / 1000, 1)];
    }
    // Streak: consecutive days (ending today, or yesterday if nothing yet today) meeting the goal (or any walk).
    $streak = 0;
    $start = isset($by[$today->format('Y-m-d')]) ? 0 : 1;
    for ($i = $start; $i <= 30; $i++) {
        $r = $by[$today->modify("-{$i} days")->format('Y-m-d')] ?? null;
        if (!$r || ($goal && (int) $r['minutes'] < $goal)) {
            break;
        }
        $streak++;
    }
    $last = rrp_db()->prepare('SELECT id, started_at, minutes, distance_m, route_json, source FROM pet_walks WHERE pet_id = ? ORDER BY started_at DESC LIMIT 1');
    $last->execute([(int) $pet['id']]);
    $l = $last->fetch() ?: null;
    if ($l) {
        $l['route'] = $l['route_json'] ? (json_decode((string) $l['route_json'], true) ?: null) : null;
        unset($l['route_json']);
    }
    $t = $by[$today->format('Y-m-d')] ?? null;
    return [
        'goal' => $goal,
        'today_minutes' => (int) ($t['minutes'] ?? 0),
        'today_km' => round((int) ($t['meters'] ?? 0) / 1000, 1),
        'week' => $week,
        'week_minutes' => array_sum(array_column($week, 'minutes')),
        'week_km' => round(array_sum(array_column($week, 'km')), 1),
        'streak' => $streak,
        'last' => $l,
    ];
}

/**
 * Best walk slot left today from the hourly forecast (dry and comfortable, 07–21 local).
 * @return array{from:string, to:string, temp_c:int, text:string, rain_later:bool}|null
 */
function rrp_walk_window(array $owner): ?array
{
    $c = rrp_sipo_owner_coords($owner);
    if ($c['lat'] === null) {
        return null;
    }
    $tz = rrp_sipo_owner_tz($owner);
    $key = sprintf('wxh:%.2f,%.2f:%s', $c['lat'], $c['lng'], $tz->getName());
    $data = rrp_sipo_cache_get($key, 1800);
    if (!is_array($data)) {
        $data = rrp_sipo_http_json('https://api.open-meteo.com/v1/forecast?' . http_build_query([
            'latitude' => round($c['lat'], 2), 'longitude' => round($c['lng'], 2),
            'hourly' => 'temperature_2m,precipitation_probability,weather_code',
            'forecast_days' => 1, 'timezone' => $tz->getName(),
        ]), 6);
        if (!is_array($data) || empty($data['hourly']['time'])) {
            return null;
        }
        rrp_sipo_cache_set($key, $data);
    }
    return rrp_walk_window_from_hourly($data['hourly'], (int) (new DateTimeImmutable('now', $tz))->format('G'));
}

/** Pure part (testable): pick the longest dry, comfortable run of hours from now. */
function rrp_walk_window_from_hourly(array $h, int $nowHour): ?array
{
    $ok = [];
    $rainLater = false;
    foreach ($h['time'] as $i => $t) {
        $hour = (int) substr((string) $t, 11, 2);
        if ($hour < max(7, $nowHour) || $hour > 21) {
            continue;
        }
        $p = (int) ($h['precipitation_probability'][$i] ?? 0);
        $temp = (float) ($h['temperature_2m'][$i] ?? 15);
        $good = $p < 35 && $temp > RRP_COLD_C && $temp < RRP_HOT_C;
        if ($p >= 50) {
            $rainLater = true;
        }
        $ok[$hour] = ['good' => $good, 'temp' => $temp, 'p' => $p];
    }
    if (!$ok) {
        return null;
    }
    $best = null;
    $run = null;
    foreach ($ok as $hour => $o) {
        if ($o['good']) {
            $run = $run ?? ['from' => $hour, 'to' => $hour, 'temps' => []];
            $run['to'] = $hour;
            $run['temps'][] = $o['temp'];
            if (!$best || ($run['to'] - $run['from']) > ($best['to'] - $best['from'])) {
                $best = $run;
            }
        } else {
            $run = null;
        }
    }
    if (!$best) {
        $hot = max(array_column($ok, 'temp')) >= RRP_HOT_C;
        return ['from' => '', 'to' => '', 'temp_c' => (int) round(array_sum(array_column($ok, 'temp')) / count($ok)), 'rain_later' => $rainLater,
            'text' => $hot ? 'Hot all day — short walks in the shade, early or late, with water.' : 'Wet or very cold all day — short walks, dry paws after.'];
    }
    $from = sprintf('%02d:00', $best['from']);
    $to = sprintf('%02d:00', min(22, $best['to'] + 1));
    $t = (int) round(array_sum($best['temps']) / count($best['temps']));
    $soon = $best['from'] <= $nowHour + 1;
    return ['from' => $from, 'to' => $to, 'temp_c' => $t, 'rain_later' => $rainLater,
        'text' => "Best walk window today: {$from}–{$to} (dry, ~{$t}°C)" . ($rainLater && $soon ? ' — rain or snow later, so earlier is better.' : '.')];
}

function rrp_walk_set_goal(array $pet, int $minutes): array
{
    if ($minutes < 5 || $minutes > 300) {
        return ['ok' => false, 'error' => 'Goal must be 5–300 minutes a day.'];
    }
    rrp_db()->prepare('UPDATE pets SET walk_goal_minutes = ? WHERE id = ?')->execute([$minutes, (int) $pet['id']]);
    return ['ok' => true];
}

/** Daily-life block for the SIPO prompt. */
function rrp_daily_prompt_block(array $owner, array $pet): string
{
    $b = rrp_bath_summary($owner, $pet);
    $lines = [];
    $t = $b['today'];
    $lines[] = "Bathroom today: pee {$t['pee']}, poop {$t['poop']}" . ($t['accident'] ? ", accidents {$t['accident']}" : '') . ($t['vomit'] ? ", vomit {$t['vomit']}" : '')
        . ($b['learning'] ? " · still learning the usual pattern ({$b['usual']['logging_days']} days logged)"
            : " · usual per day: pee {$b['usual']['pee']}, poop {$b['usual']['poop']}");
    foreach ($b['signals'] as $s) {
        $lines[] = "⚠ {$s['text']}";
    }
    if (($pet['type'] ?? '') === 'dog') {
        $w = rrp_walk_summary($owner, $pet);
        $lines[] = "Walks today: {$w['today_minutes']} min, {$w['today_km']} km"
            . ($w['goal'] ? " (goal {$w['goal']} min)" : ' (no goal set — ask the owner or their vet)')
            . " · this week {$w['week_minutes']} min / {$w['week_km']} km · streak {$w['streak']} days";
        $win = rrp_walk_window($owner);
        if ($win) {
            $lines[] = $win['text'];
        }
    }
    return implode("\n", $lines);
}
