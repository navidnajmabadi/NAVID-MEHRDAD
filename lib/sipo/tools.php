<?php
declare(strict_types=1);

/**
 * SIPO function tools. Each executor returns a small array that is sent back to
 * the model as the tool result. Executors never trust ids from the model:
 * owner and pet always come from the server-side thread.
 */

/** @return list<array<string,mixed>> */
function rrp_sipo_tool_defs(bool $onboarding): array
{
    $fn = static fn(string $name, string $desc, array $props, array $required = []) => [
        'type' => 'function',
        'function' => [
            'name' => $name,
            'description' => $desc,
            'parameters' => [
                'type' => 'object',
                'properties' => (object) $props,
                'required' => $required,
                'additionalProperties' => false,
            ],
        ],
    ];

    $defs = [];
    if ($onboarding) {
        $defs[] = $fn('create_pet_profile', 'Create the pet profile once you know its name and kind. Only during onboarding.', [
            'name' => ['type' => 'string', 'description' => 'Pet name as the owner confirmed it'],
            'kind' => ['type' => 'string', 'enum' => ['dog', 'cat', 'bird', 'rabbit', 'hamster', 'other']],
            'other_kind' => ['type' => 'string', 'description' => 'If kind=other, what animal'],
            'breed' => ['type' => 'string'],
        ], ['name', 'kind']);
    } else {
        $defs[] = $fn('update_pet_profile', 'Save core profile fields the owner told you. Send only fields you learned.', [
            'breed' => ['type' => 'string'],
            'sex' => ['type' => 'string', 'enum' => ['male', 'female']],
            'birth_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD (use the 1st if only month/year known)'],
            'age_years' => ['type' => 'number'],
            'weight_kg' => ['type' => 'number', 'description' => 'Convert lb to kg'],
            'neutered' => ['type' => 'boolean'],
            'color' => ['type' => 'string'],
            'conditions' => ['type' => 'string', 'description' => 'Known medical conditions, comma separated'],
        ]);
        $defs[] = $fn('save_pet_fact', 'Remember a lasting fact about the pet or its routine (food brand, vet name, allergy, fear, last vaccine date…).', [
            'key' => ['type' => 'string', 'description' => 'short snake_case key, e.g. food_brand, vet_clinic, last_rabies_vaccine'],
            'value' => ['type' => 'string'],
            'category' => ['type' => 'string', 'enum' => ['identity', 'health', 'food', 'routine', 'behavior', 'home', 'owner', 'other']],
        ], ['key', 'value', 'category']);
        $defs[] = $fn('set_reminder', 'Create a care reminder the owner agreed to.', [
            'title' => ['type' => 'string', 'description' => 'e.g. "Rabies booster", "Flea treatment"'],
            'due' => ['type' => 'string', 'description' => 'YYYY-MM-DD or YYYY-MM-DD HH:MM in the owner\'s local time'],
        ], ['title', 'due']);
    }
    $defs[] = $fn('get_weather', 'Current weather and today\'s forecast at the owner\'s home area.', []);
    $defs[] = $fn('find_vets', 'Find veterinary clinics near the owner\'s home area, with phone numbers when known.', [
        'emergency_only' => ['type' => 'boolean'],
    ]);
    $defs[] = $fn('open_support_ticket', 'Open a support ticket when the app is broken or the owner reports a problem with SIPAW.', [
        'subject' => ['type' => 'string'],
        'details' => ['type' => 'string'],
    ], ['subject', 'details']);
    $defs[] = $fn('flag_emergency', 'Mark this conversation as an emergency so limits are lifted and the owner sees the alert state.', [
        'reason' => ['type' => 'string'],
    ], ['reason']);
    return $defs;
}

/**
 * @param array{owner:array, thread:array, pet:?array, user_message_id:?int} $ctx  (by reference: pet/thread may change)
 * @return array<string,mixed>
 */
function rrp_sipo_run_tool(string $name, array $args, array &$ctx): array
{
    $owner = $ctx['owner'];
    $pet = $ctx['pet'];
    $ownerId = (int) $owner['id'];

    switch ($name) {
        case 'create_pet_profile':
            if ($pet !== null) {
                return ['ok' => false, 'error' => 'This chat already has a pet.'];
            }
            $kind = (string) ($args['kind'] ?? '');
            $res = rrp_create_pet($ownerId, [
                'name' => (string) ($args['name'] ?? ''),
                'type' => $kind === 'other' ? 'custom' : $kind,
                'custom_type' => (string) ($args['other_kind'] ?? ($kind === 'other' ? 'pet' : '')),
                'breed' => (string) ($args['breed'] ?? ''),
            ]);
            if (empty($res['ok'])) {
                return ['ok' => false, 'error' => $res['error'] ?? 'Could not create pet.', 'gate' => $res['gate'] ?? null];
            }
            $newPet = rrp_pet_by_id((int) $res['pet_id'], $ownerId);
            if (!$newPet) {
                return ['ok' => false, 'error' => 'Pet saved but could not be loaded.'];
            }
            rrp_sipo_bind_thread_to_pet((int) $ctx['thread']['id'], $newPet);
            rrp_sipo_set_current_pet($ownerId, (int) $newPet['id']);
            $ctx['pet'] = $newPet;
            $ctx['thread']['pet_id'] = (int) $newPet['id'];
            $ctx['thread']['onboarding'] = 0;
            $ctx['events'][] = 'pet_created';
            return ['ok' => true, 'pet' => ['name' => $newPet['name'], 'kind' => rrp_pet_type_label($newPet)]];

        case 'update_pet_profile':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet yet.'];
            }
            $saved = rrp_sipo_update_pet_fields((int) $pet['id'], $ownerId, $args);
            $ctx['pet'] = rrp_pet_by_id((int) $pet['id'], $ownerId) ?: $pet;
            if ($saved) {
                $ctx['events'][] = 'profile_updated';
            }
            return ['ok' => true, 'saved' => $saved];

        case 'save_pet_fact':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet yet.'];
            }
            $ok = rrp_sipo_save_fact(
                $ownerId,
                (int) $pet['id'],
                (string) ($args['key'] ?? ''),
                (string) ($args['value'] ?? ''),
                (string) ($args['category'] ?? 'other'),
                $ctx['user_message_id'] ?? null
            );
            return ['ok' => $ok];

        case 'set_reminder':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet yet.'];
            }
            $due = rrp_sipo_local_to_utc((string) ($args['due'] ?? ''), $owner);
            if ($due === null) {
                return ['ok' => false, 'error' => 'Use YYYY-MM-DD or YYYY-MM-DD HH:MM.'];
            }
            $res = rrp_care_reminder_create((int) $pet['id'], [
                'title' => (string) ($args['title'] ?? ''),
                'due_at' => $due,
                'status' => 'scheduled',
            ]);
            if (!empty($res['ok'])) {
                $ctx['events'][] = 'reminder_set';
            }
            return ['ok' => !empty($res['ok']), 'error' => $res['error'] ?? null];

        case 'get_weather':
            $c = rrp_sipo_owner_coords($owner);
            if ($c['lat'] === null) {
                return ['ok' => false, 'error' => 'Home area not set. Ask the owner to set it on the map (Area page).'];
            }
            $w = rrp_sipo_weather($c['lat'], $c['lng']);
            return $w ? ['ok' => true, 'weather' => $w['text']] : ['ok' => false, 'error' => 'Weather service unavailable.'];

        case 'find_vets':
            $c = rrp_sipo_owner_coords($owner);
            if ($c['lat'] === null) {
                return ['ok' => false, 'error' => 'Home area not set. Ask the owner which city they are in, or to set the area on the map.'];
            }
            $vets = rrp_sipo_find_vets($c['lat'], $c['lng'], !empty($args['emergency_only']));
            return ['ok' => true, 'clinics' => $vets, 'note' => $vets ? null : 'No clinics found in OpenStreetMap nearby.'];

        case 'open_support_ticket':
            $res = rrp_ticket_create(
                $ownerId,
                mb_substr('[SIPO] ' . (string) ($args['subject'] ?? 'Support'), 0, 200),
                (string) ($args['details'] ?? '')
            );
            if (!empty($res['ok'])) {
                $ctx['events'][] = 'ticket_opened';
            }
            return ['ok' => !empty($res['ok']), 'reply_within_days' => 7];

        case 'flag_emergency':
            $ctx['emergency'] = true;
            $ctx['events'][] = 'emergency';
            error_log('SIPO emergency owner=' . $ownerId . ' reason=' . mb_substr((string) ($args['reason'] ?? ''), 0, 200));
            return ['ok' => true];
    }
    return ['ok' => false, 'error' => 'Unknown tool ' . $name];
}

function rrp_sipo_update_pet_fields(int $petId, int $ownerId, array $args): array
{
    $sets = [];
    $vals = [];
    $saved = [];
    $str = static fn($v, int $max) => mb_substr(trim((string) $v), 0, $max);

    if (!empty($args['breed'])) {
        $sets[] = 'breed = ?'; $vals[] = $str($args['breed'], 120); $saved[] = 'breed';
    }
    if (in_array($args['sex'] ?? null, ['male', 'female'], true)) {
        $sets[] = 'sex = ?'; $vals[] = $args['sex']; $saved[] = 'sex';
    }
    if (!empty($args['birth_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['birth_date'])) {
        $sets[] = 'birth_date = ?'; $vals[] = $args['birth_date']; $saved[] = 'birth_date';
    }
    if (isset($args['age_years']) && is_numeric($args['age_years']) && $args['age_years'] >= 0 && $args['age_years'] < 40) {
        $sets[] = 'age_years = ?'; $vals[] = round((float) $args['age_years'], 1); $saved[] = 'age_years';
    }
    if (isset($args['weight_kg']) && is_numeric($args['weight_kg']) && $args['weight_kg'] > 0 && $args['weight_kg'] < 200) {
        $sets[] = 'weight_kg = ?'; $vals[] = round((float) $args['weight_kg'], 2); $saved[] = 'weight_kg';
    }
    if (isset($args['neutered']) && is_bool($args['neutered'])) {
        $sets[] = 'neutered = ?'; $vals[] = $args['neutered'] ? 1 : 0; $saved[] = 'neutered';
    }
    if (!empty($args['color'])) {
        $sets[] = 'color = ?'; $vals[] = $str($args['color'], 80); $saved[] = 'color';
    }
    if (!empty($args['conditions'])) {
        $sets[] = 'medical_conditions = ?'; $vals[] = $str($args['conditions'], 2000); $saved[] = 'conditions';
    }
    if (!$sets) {
        return [];
    }
    $vals[] = $petId;
    $vals[] = $ownerId;
    try {
        rrp_db()->prepare('UPDATE pets SET ' . implode(', ', $sets) . ' WHERE id = ? AND owner_id = ?')->execute($vals);
        if (in_array('weight_kg', $saved, true)) {
            // Keep a dated trail for weight charts (PR 2).
            rrp_sipo_save_fact($ownerId, $petId, 'weight_' . gmdate('Y_m_d'), $args['weight_kg'] . ' kg', 'health');
        }
        return $saved;
    } catch (Throwable $e) {
        error_log('rrp_sipo_update_pet_fields: ' . $e->getMessage());
        return [];
    }
}

/** Owner-local "YYYY-MM-DD[ HH:MM]" → UTC "Y-m-d H:i:s" (date-only = 09:00 local). */
function rrp_sipo_local_to_utc(string $local, array $owner): ?string
{
    $local = trim($local);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $local)) {
        $local .= ' 09:00';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $local)) {
        return null;
    }
    try {
        $dt = new DateTimeImmutable(str_replace('T', ' ', $local), rrp_sipo_owner_tz($owner));
    } catch (Throwable $e) {
        return null;
    }
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/**
 * Nearby vets from OpenStreetMap (Overpass API, free). Cached 1 day.
 * @return list<array{name:string, phone:?string, address:?string, distance_km:float, emergency:bool}>
 */
function rrp_sipo_find_vets(float $lat, float $lng, bool $emergencyOnly = false): array
{
    $key = sprintf('vets:%.2f,%.2f', $lat, $lng);
    $rows = rrp_sipo_cache_get($key, 86400);
    if (!is_array($rows)) {
        $q = sprintf(
            '[out:json][timeout:10];(node["amenity"="veterinary"](around:15000,%1$F,%2$F);way["amenity"="veterinary"](around:15000,%1$F,%2$F););out center tags 40;',
            $lat,
            $lng
        );
        $data = rrp_sipo_http_json('https://overpass-api.de/api/interpreter', 12, 'data=' . rawurlencode($q));
        $rows = [];
        foreach (($data['elements'] ?? []) as $el) {
            $tags = $el['tags'] ?? [];
            $elat = (float) ($el['lat'] ?? $el['center']['lat'] ?? 0);
            $elng = (float) ($el['lon'] ?? $el['center']['lon'] ?? 0);
            if (empty($tags['name']) || !$elat) {
                continue;
            }
            $addr = trim(implode(' ', array_filter([
                $tags['addr:housenumber'] ?? null,
                $tags['addr:street'] ?? null,
                $tags['addr:city'] ?? null,
            ])));
            $rows[] = [
                'name' => (string) $tags['name'],
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                'address' => $addr !== '' ? $addr : null,
                'distance_km' => round(rrp_haversine_km($lat, $lng, $elat, $elng), 1),
                'emergency' => (($tags['emergency'] ?? '') === 'yes') || str_contains(strtolower((string) ($tags['opening_hours'] ?? '')), '24/7'),
            ];
        }
        usort($rows, static fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
        rrp_sipo_cache_set($key, $rows);
    }
    if ($emergencyOnly) {
        $em = array_values(array_filter($rows, static fn($r) => $r['emergency']));
        if ($em) {
            $rows = $em;
        }
    }
    return array_slice($rows, 0, 5);
}
