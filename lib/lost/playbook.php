<?php
declare(strict_types=1);

/**
 * Lost Mode search plan (brief §D). Personal to the pet: species, indoor/outdoor, age, temperament
 * facts, weather now, and the places it walks most. Evidence used:
 * - Cats: 75% of found cats were within 500 m of where they escaped; indoor-only cats stay much
 *   closer; a physical search raises the chance of finding them alive (Huang et al. 2018, Animals).
 * - Dogs: most are found within about 1.5 km; temperament, weather, terrain and density change
 *   how far they go (Lord et al. 2007; shelter guidance).
 */

const RRP_LOST_SOURCES = [
    ['Huang et al. 2018 — Search methods used to locate missing cats', 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC5789300/'],
];

/** Search radius in metres for helpers' map and the owner plan. */
function rrp_lost_search_radius_m(array $pet): int
{
    $type = (string) ($pet['type'] ?? '');
    if ($type === 'cat') {
        return ($pet['lifestyle'] ?? '') === 'indoor' ? 200 : 500;
    }
    return $type === 'dog' ? 1500 : 500;
}

/**
 * Places the pet walks most (from GPS walks, last 90 days), as ~150 m cells.
 * @return list<array{lat:float, lng:float, walks:int, km_from_home:?float, label:?string}>
 */
function rrp_lost_walk_spots(array $owner, array $pet, int $limit = 5): array
{
    try {
        $stmt = rrp_db()->prepare(
            "SELECT id, route_json FROM pet_walks WHERE pet_id = ? AND route_json IS NOT NULL AND started_at > (UTC_TIMESTAMP() - INTERVAL 90 DAY)"
        );
        $stmt->execute([(int) $pet['id']]);
        $walks = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $cells = [];
    foreach ($walks as $w) {
        $seen = [];
        foreach ((array) json_decode((string) $w['route_json'], true) as $p) {
            if (!is_array($p) || !isset($p[0], $p[1])) {
                continue;
            }
            $key = round((float) $p[0] / 0.0015) . ':' . round((float) $p[1] / 0.0015);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $cells[$key] ??= ['lat' => 0.0, 'lng' => 0.0, 'n' => 0, 'walks' => 0];
            $cells[$key]['lat'] += (float) $p[0];
            $cells[$key]['lng'] += (float) $p[1];
            $cells[$key]['n']++;
            $cells[$key]['walks']++;
        }
    }
    $home = rrp_sipo_owner_coords($owner);
    $out = [];
    foreach ($cells as $c) {
        $lat = $c['lat'] / $c['n'];
        $lng = $c['lng'] / $c['n'];
        $km = $home['lat'] !== null ? rrp_haversine_km($home['lat'], $home['lng'], $lat, $lng) : null;
        if ($km !== null && $km < 0.15) {
            continue; // the front door is not a "spot"
        }
        $out[] = ['lat' => round($lat, 4), 'lng' => round($lng, 4), 'walks' => $c['walks'], 'km_from_home' => $km !== null ? round($km, 1) : null, 'label' => null];
    }
    usort($out, static fn($a, $b) => $b['walks'] <=> $a['walks']);
    $out = array_slice($out, 0, $limit);
    foreach ($out as $i => $s) {
        if ($i < 3) {
            $out[$i]['label'] = rrp_lost_place_label($s['lat'], $s['lng']);
        }
    }
    return $out;
}

/** Nearby park/street name from OpenStreetMap (cached a month). */
function rrp_lost_place_label(float $lat, float $lng): ?string
{
    $key = sprintf('place:%.4f,%.4f', $lat, $lng);
    $hit = rrp_sipo_cache_get($key, 30 * 86400);
    if (is_string($hit)) {
        return $hit !== '' ? $hit : null;
    }
    $d = rrp_sipo_http_json('https://nominatim.openstreetmap.org/reverse?' . http_build_query([
        'lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 17,
    ]), 6);
    $a = is_array($d['address'] ?? null) ? $d['address'] : [];
    $label = (string) ($d['name'] ?? '') ?: (string) ($a['park'] ?? $a['road'] ?? $a['neighbourhood'] ?? '');
    rrp_sipo_cache_set($key, $label);
    return $label !== '' ? $label : null;
}

/** Tips from the weather right now. @return list<string> */
function rrp_lost_weather_tips(array $owner, array $pet): array
{
    $c = rrp_sipo_owner_coords($owner);
    $w = $c['lat'] !== null ? rrp_sipo_weather($c['lat'], $c['lng']) : null;
    if (!$w) {
        return [];
    }
    $tips = [];
    $name = (string) $pet['name'];
    if ($w['temp_c'] <= 2) {
        $tips[] = "It's cold ({$w['text']}). {$name} will look for warmth: under porches, in garages, sheds, under parked cars.";
    } elseif ($w['temp_c'] >= RRP_HOT_C) {
        $tips[] = "It's hot ({$w['text']}). Check shade and water: under decks, bushes, near streams or garden hoses.";
    }
    if (($w['rain_chance'] ?? 0) >= 50 || $w['precip_mm'] > 0) {
        $tips[] = "Rain makes pets hide and stay put. Look in dry, covered spots close by, and search again when it stops.";
    }
    return $tips;
}

/**
 * The plan. Steps are grouped by time so the owner always knows what to do next.
 * @return array{radius_m:int, intro:string, steps:list<array{when:string, items:list<string>}>, spots:list<array>, weather:list<string>, helper:list<string>, sources:array}
 */
function rrp_lost_playbook(array $owner, array $pet, ?array $alert = null): array
{
    $name = (string) $pet['name'];
    $type = (string) ($pet['type'] ?? '');
    $indoor = ($pet['lifestyle'] ?? '') === 'indoor';
    $months = rrp_food_age_months($pet);
    $senior = $months !== null && $months >= 96;
    $young = $months !== null && $months < 12;
    $radius = rrp_lost_search_radius_m($pet);
    $facts = [];
    foreach (rrp_sipo_facts((int) $pet['id']) as $f) {
        $facts[$f['fact_key']] = $f['fact_value'];
    }
    $shy = (bool) preg_match('/\b(shy|scared|fear|nervous|anxious|timid)\b/i', implode(' ', $facts));
    $magic = trim((string) ($pet['magic_word'] ?? ''));
    $call = $magic !== '' ? "call \"{$magic}\" softly" : 'call their name softly';
    $spots = $type === 'dog' ? rrp_lost_walk_spots($owner, $pet) : [];

    if ($type === 'cat') {
        $intro = $indoor
            ? "Indoor cats usually hide very close to home, often within a few houses, and stay silent. A slow, careful search nearby finds most of them."
            : "Most cats are found within about 500 m of where they got out. They hide and stay quiet, so look slowly rather than far.";
        $steps = [
            ['when' => 'Right now', 'items' => [
                "Search inside first: closets, behind appliances, inside sofas and boxes. Scared cats hide indoors too.",
                "Open the door you think {$name} used and leave it ajar with bedding and food just inside.",
            ]],
            ['when' => 'First hour', 'items' => [
                "Walk slowly within " . ($indoor ? 'a few houses' : 'about 500 m') . " of home. Check under decks, porches, sheds, bushes and parked cars with a torch (eyes reflect).",
                "Stop every few metres, {$call} and shake the treat bag, then wait quietly and listen.",
                "Ask neighbours to check garages and sheds today. Cats get shut in by accident.",
            ]],
            ['when' => 'Tonight', 'items' => [
                "Search again after dark and at dawn, when it's quiet. Cats come out when the street is calm.",
                "Leave water and a worn T-shirt of yours outside the door overnight.",
            ]],
            ['when' => 'Next days', 'items' => [
                "Call local shelters, vets and animal control and report {$name} lost. Visit shelters in person if you can.",
                "Check the microchip details are up to date with the registry.",
                "Print the Lost poster (Lost tab) and put it up within about 500 m.",
                "Keep searching nearby. Many cats are found after several days, very close to home.",
            ]],
        ];
        $helper = [
            "Look slowly in hiding spots near where {$name} was last seen: under decks, sheds, cars and bushes.",
            "Don't chase or grab. Note where you saw {$name} and tap \"I saw this pet\".",
            "If you can, gently check your garage or shed.",
        ];
    } else {
        $intro = $type === 'dog'
            ? "Most lost dogs are found within about 1.5 km. Friendly dogs often go to people; shy or scared dogs hide and may not come even to you."
            : "Search close to home first and look in sheltered, quiet places.";
        $now = [
            "Go to where {$name} was last seen" . ($alert && !empty($alert['last_seen_note']) ? " ({$alert['last_seen_note']})" : '') . " and search outward from there.",
            "Leave someone at home with the door open or a gate secured. Many dogs come back home on their own.",
        ];
        $hour = [
            "Walk the usual walk routes first" . ($spots ? ': ' . implode(', ', array_filter(array_map(static fn($s) => $s['label'] ?: null, $spots))) : '') . ".",
            $shy
                ? "{$name} may be in \"survival mode\". Don't chase or call loudly. Sit or lie down, look away, toss treats and let them come to you."
                : "Bring treats, a squeaky toy and the person {$name} loves most. Call happily; don't run after them.",
            "Ask people in parks and dog walkers. Show the photo on your phone.",
        ];
        if ($senior) {
            $hour[] = "{$name} is older and may be confused or tired. Check quiet spots close by, ditches and under bushes.";
        }
        if ($young) {
            $hour[] = "Young dogs often run toward people, other dogs and play. Check busy parks and playgrounds.";
        }
        $steps = [
            ['when' => 'Right now', 'items' => $now],
            ['when' => 'First hour', 'items' => $hour],
            ['when' => 'Today', 'items' => [
                "Call local shelters, vets and animal control. Check the microchip registry has your number.",
                "Put the Lost poster (Lost tab) up along the routes, at parks, vets and pet shops.",
                "Search again at dawn and dusk, when streets are quiet and dogs move.",
            ]],
            ['when' => 'Next days', 'items' => [
                "Widen the search step by step beyond about 1.5 km, following parks, rivers and green paths.",
                "Visit shelters in person every 2 days. Descriptions on the phone are often wrong.",
                "Put {$name}'s bed and your worn clothes outside your home.",
            ]],
        ];
        $helper = [
            "Don't chase {$name}. Crouch down, look away and talk softly; toss a treat if you have one.",
            "Note exactly where and when you saw {$name}, take a photo if you can, and tap \"I saw this pet\".",
            "If {$name} lets you, check the collar tag or call the owner through SIPAW.",
        ];
    }

    return [
        'radius_m' => $radius,
        'intro' => $intro,
        'steps' => $steps,
        'spots' => $spots,
        'weather' => rrp_lost_weather_tips($owner, $pet),
        'helper' => $helper,
        'sources' => RRP_LOST_SOURCES,
    ];
}

/** Short text block for SIPO when an alert is open. */
function rrp_lost_prompt_block(array $owner, array $pet, array $alert): string
{
    $pb = rrp_lost_playbook($owner, $pet, $alert);
    $started = (new DateTimeImmutable((string) $alert['started_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner));
    $lines = ["{$pet['name']} is LOST since " . $started->format('D H:i') . ". Alert {$alert['status']}, pings sent: {$alert['ping_count']}.",
        'Be calm, practical and kind. Walk the owner through the plan one step at a time, ask what they have done, and remind them to tap Found the moment the pet is home.',
        'Plan: ' . $pb['intro']];
    foreach ($pb['steps'] as $s) {
        $lines[] = $s['when'] . ': ' . implode(' / ', $s['items']);
    }
    foreach ($pb['weather'] as $w) {
        $lines[] = 'Weather: ' . $w;
    }
    try {
        $st = rrp_db()->prepare('SELECT seen_at, note FROM lost_sightings WHERE lost_alert_id = ? ORDER BY seen_at DESC LIMIT 3');
        $st->execute([(int) $alert['id']]);
        foreach ($st->fetchAll() as $s) {
            $lines[] = 'Sighting reported ' . substr((string) $s['seen_at'], 0, 16) . ' UTC' . ($s['note'] ? ': ' . $s['note'] : '');
        }
    } catch (Throwable $e) {
    }
    return implode("\n", $lines);
}

/** Helper reports "I saw this pet" → stored, owner told by push + chat. */
function rrp_lost_add_sighting(array $reporter, array $alert, ?float $lat, ?float $lng, ?string $note): array
{
    if ($alert['status'] !== 'open') {
        return ['ok' => false, 'error' => 'This pet has already been found. Thank you!'];
    }
    $lat = $lat !== null && abs($lat) <= 90 ? round($lat, 3) : null;   // ~100 m, protects the helper
    $lng = $lng !== null && abs($lng) <= 180 ? round($lng, 3) : null;
    rrp_db()->prepare('INSERT INTO lost_sightings (lost_alert_id, reporter_owner_id, lat, lng, seen_at, note) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?)')
        ->execute([(int) $alert['id'], (int) $reporter['id'], $lat, $lng, $note ? mb_substr(trim($note), 0, 500) : null]);
    $ownerId = (int) $alert['owner_id'];
    $text = "👀 Someone nearby just saw {$alert['pet_name']}" . ($note ? ": \"" . mb_substr(trim($note), 0, 140) . "\"" : '.') . ' Open the alert to see where.';
    $thread = rrp_sipo_thread_for_pet($ownerId, (int) $alert['pet_id']);
    if ($thread) {
        rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'alert', 'proactive' => 'sighting']);
    }
    rrp_notify_owner_inbox($ownerId, 'lost_sighting', 'Sighting: ' . $alert['pet_name'], $text, null, (int) $alert['id']);
    rrp_try_web_push($ownerId, 'Sighting: ' . $alert['pet_name'], $text, rrp_app_url('lost-alert.php?id=' . (int) $alert['id']));
    return ['ok' => true];
}

/** @return list<array> */
function rrp_lost_sightings(int $alertId): array
{
    try {
        $st = rrp_db()->prepare('SELECT lat, lng, seen_at, note FROM lost_sightings WHERE lost_alert_id = ? ORDER BY seen_at DESC LIMIT 20');
        $st->execute([$alertId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Cron: after the last ping (+5 h), once a day ask the owner "Is X home?" so helpers aren't left
 * searching for a pet that is already safe (brief: force Found).
 */
function rrp_lost_owner_nudge_tick(): array
{
    $stats = ['nudged' => 0];
    try {
        $rows = rrp_db()->query(
            "SELECT * FROM lost_alerts WHERE status = 'open' AND next_ping_at IS NULL
             AND started_at < (UTC_TIMESTAMP() - INTERVAL 6 HOUR)
             AND (owner_nudged_at IS NULL OR owner_nudged_at < (UTC_TIMESTAMP() - INTERVAL 20 HOUR))"
        )->fetchAll();
    } catch (Throwable $e) {
        return $stats;
    }
    foreach ($rows as $a) {
        $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . (int) $a['owner_id'])->fetch();
        if (!$owner || rrp_sipo_is_quiet_hour((int) rrp_sipo_local_now($owner)->format('G'))) {
            continue;
        }
        $text = "Is {$a['pet_name']} home? 💙 If yes, tap Found so neighbours can stop searching. If not, let's go over the next step of the plan together.";
        $thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $a['pet_id']);
        if ($thread) {
            rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'alert', 'proactive' => 'lost_check']);
        }
        rrp_try_web_push((int) $owner['id'], 'SIPO · ' . $a['pet_name'], $text, rrp_app_url('lost.php?pet=' . (int) $a['pet_id']));
        rrp_db()->prepare('UPDATE lost_alerts SET owner_nudged_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $a['id']]);
        $stats['nudged']++;
    }
    return $stats;
}
