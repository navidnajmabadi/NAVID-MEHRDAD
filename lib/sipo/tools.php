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
        $defs[] = $fn('update_pet_profile', 'Save core profile fields the owner told you. Send only fields you learned. Changing a value that is already saved needs the owner\'s yes first: then send confirmed=true.', [
            'name' => ['type' => 'string', 'description' => 'New pet name (rename). Never put a name in breed.'],
            'confirmed' => ['type' => 'boolean', 'description' => 'true only after the owner said yes to changing an already-saved value'],
            'breed' => ['type' => 'string'],
            'sex' => ['type' => 'string', 'enum' => ['male', 'female']],
            'birth_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD (use the 1st if only month/year known)'],
            'age_years' => ['type' => 'number'],
            'weight_kg' => ['type' => 'number', 'description' => 'Convert lb to kg'],
            'neutered' => ['type' => 'boolean'],
            'color' => ['type' => 'string'],
            'conditions' => ['type' => 'string', 'description' => 'Known medical conditions, comma separated'],
            'vet_kcal_per_day' => ['type' => 'integer', 'description' => 'Daily calories ONLY if the vet gave a number'],
            'activity' => ['type' => 'string', 'enum' => ['low', 'normal', 'high']],
            'lifestyle' => ['type' => 'string', 'enum' => ['indoor', 'outdoor', 'mixed']],
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
        $defs = array_merge($defs, rrp_sipo_care_tool_defs($fn));
    }
    foreach (rrp_sipo_app_tool_defs($fn) as $d) {
        if (!$onboarding || $d['function']['name'] !== 'add_pet') {
            $defs[] = $d;
        }
    }
    // Every per-pet tool accepts pet_name (another of the owner's pets) so SIPO never has to switch chats.
    foreach ($defs as &$d) {
        if (in_array($d['function']['name'], RRP_SIPO_PET_TOOLS, true)) {
            $d['function']['parameters']['properties'] = (object) ((array) $d['function']['parameters']['properties'] + [
                'pet_name' => ['type' => 'string', 'description' => 'Only if this is about ANOTHER of the owner\'s pets (not the one in this chat): that pet\'s name'],
            ]);
        }
    }
    unset($d);
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

/** Health + food tools (PR 2). */
function rrp_sipo_care_tool_defs(callable $fn): array
{
    $date = ['type' => 'string', 'description' => 'YYYY-MM-DD (YYYY-MM if the day is unknown)'];
    return [
        $fn('care_record_done', 'Owner says a care routine was done (now or in the past). Uses a routine key from "Care routines". Books the next one.', [
            'routine_key' => ['type' => 'string'], 'date' => $date, 'note' => ['type' => 'string'],
            'interval_days' => ['type' => 'integer', 'description' => 'Only if the vet/certificate gives a different interval, e.g. 1095 for a 3-year rabies vaccine'],
        ], ['routine_key', 'date']),
        $fn('reminder_outcome', 'Close or move an open reminder (ids are in "Open reminders").', [
            'reminder_id' => ['type' => 'integer'],
            'outcome' => ['type' => 'string', 'enum' => ['done', 'rescheduled', 'vet_booked', 'skipped']],
            'new_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD[ HH:MM] for rescheduled/vet_booked, or the done date'],
            'note' => ['type' => 'string'],
        ], ['reminder_id', 'outcome']),
        $fn('treatment_start', 'The pet is under vet treatment for a condition. Store the vet instructions word for word.', [
            'condition' => ['type' => 'string'], 'vet_name' => ['type' => 'string'], 'started_on' => $date,
            'planned_end_on' => $date, 'instructions' => ['type' => 'string', 'description' => 'Exactly what the vet said, verbatim'],
        ], ['condition']),
        $fn('treatment_add_medicine', 'Add a prescribed medicine with reminder times. Never invent or change the dose.', [
            'treatment_id' => ['type' => 'integer'], 'name' => ['type' => 'string'],
            'dose_as_prescribed' => ['type' => 'string', 'description' => 'Owner/vet wording, verbatim'],
            'times' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Local HH:MM, agreed with the owner'],
            'ends_on' => $date,
        ], ['treatment_id', 'name', 'times']),
        $fn('treatment_log', 'Daily progress for a treatment.', [
            'treatment_id' => ['type' => 'integer'], 'trend' => ['type' => 'string', 'enum' => ['better', 'same', 'worse']],
            'med_given' => ['type' => 'boolean'], 'note' => ['type' => 'string'],
        ], ['treatment_id', 'trend']),
        $fn('treatment_recheck', 'Book a recheck visit for a treatment.', [
            'treatment_id' => ['type' => 'integer'], 'when' => ['type' => 'string', 'description' => 'YYYY-MM-DD[ HH:MM] local'], 'clinic' => ['type' => 'string'],
        ], ['treatment_id', 'when']),
        $fn('treatment_finish', 'Treatment is over (vet said so or course finished).', [
            'treatment_id' => ['type' => 'integer'], 'note' => ['type' => 'string'],
        ], ['treatment_id']),
        $fn('food_save', 'Save a food or treat the pet eats (from the owner or a label).', [
            'name' => ['type' => 'string'], 'kind' => ['type' => 'string', 'enum' => ['dry', 'wet', 'treat', 'raw', 'homemade', 'other']],
            'kcal_per_kg' => ['type' => 'number'], 'kcal_per_cup' => ['type' => 'number'], 'grams_per_cup' => ['type' => 'number'],
            'package_grams' => ['type' => 'number'], 'ingredients' => ['type' => 'string'], 'just_opened' => ['type' => 'boolean'],
        ], ['name', 'kind']),
        $fn('food_log_meal', 'Log what the pet ate (meal or treat), how much, and whether it liked it.', [
            'food_id' => ['type' => 'integer'], 'food_name' => ['type' => 'string'],
            'grams' => ['type' => 'number'], 'cups' => ['type' => 'number'], 'kcal' => ['type' => 'number'],
            'liked' => ['type' => 'string', 'enum' => ['loved', 'ok', 'refused']], 'note' => ['type' => 'string'],
        ]),
        $fn('food_supply', 'Update how much of a food is left, a new bag opened, or grams fed per day.', [
            'food_id' => ['type' => 'integer'], 'remaining_grams' => ['type' => 'number'],
            'new_package' => ['type' => 'boolean'], 'daily_grams' => ['type' => 'number'],
        ], ['food_id']),
        $fn('log_bathroom', 'Log a pee, poop, accident or vomit the owner reports. "Peed 3 times" = count 3.', [
            'count' => ['type' => 'integer', 'description' => 'How many times (default 1, max 10)'],
            'kind' => ['type' => 'string', 'enum' => ['pee', 'poop', 'accident', 'vomit']],
            'stool' => ['type' => 'string', 'enum' => ['normal', 'soft', 'diarrhea', 'hard', 'blood', 'mucus']],
            'at' => ['type' => 'string', 'description' => 'Local HH:MM if not just now'],
            'note' => ['type' => 'string'],
        ], ['kind']),
        $fn('log_walk', 'Log a walk: minutes, and km if known.', [
            'minutes' => ['type' => 'integer'], 'km' => ['type' => 'number'], 'note' => ['type' => 'string'],
        ], ['minutes']),
        $fn('set_walk_goal', 'Daily walk goal in minutes (from the owner or their vet).', [
            'minutes_per_day' => ['type' => 'integer'],
        ], ['minutes_per_day']),
        $fn('routine_plan', 'Start a routine at the owner\'s chosen rhythm (e.g. teeth every day, bath every 60 days).', [
            'routine_key' => ['type' => 'string'], 'interval_days' => ['type' => 'integer'],
            'start' => ['type' => 'string', 'description' => 'YYYY-MM-DD[ HH:MM] local; default today'],
        ], ['routine_key', 'interval_days']),
        $fn('set_meal_times', 'Save the meal routine; SIPO checks in after each meal.', [
            'times' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Local HH:MM'],
            'portion_note' => ['type' => 'string'],
        ], ['times']),
    ];
}

/**
 * @param array{owner:array, thread:array, pet:?array, user_message_id:?int} $ctx  (by reference: pet/thread may change)
 * @return array<string,mixed>
 */
/** QA B08: "Dog", "puppy" or kind=other + "dog" must still save as a dog, not Not specified. */
function rrp_sipo_norm_kind(string $kind, string $other = ''): string
{
    $map = ['dog' => 'dog', 'dogs' => 'dog', 'puppy' => 'dog', 'pup' => 'dog', 'cat' => 'cat', 'cats' => 'cat', 'kitten' => 'cat', 'kitty' => 'cat',
        'bird' => 'bird', 'parrot' => 'bird', 'budgie' => 'bird', 'rabbit' => 'rabbit', 'bunny' => 'rabbit', 'hamster' => 'hamster'];
    foreach ([$kind, $other] as $v) {
        $v = mb_strtolower(trim($v));
        if (isset($map[$v])) {
            return $map[$v];
        }
    }
    return mb_strtolower(trim($kind)) === 'other' || trim($kind) !== '' ? 'other' : '';
}

/** Tools that act on one pet: they accept pet_name so SIPO can act on another pet without switching chats. */
const RRP_SIPO_PET_TOOLS = ['update_pet_profile', 'save_pet_fact', 'set_reminder', 'care_record_done', 'reminder_outcome',
    'treatment_start', 'treatment_add_medicine', 'treatment_log', 'treatment_recheck', 'treatment_finish', 'food_save',
    'food_log_meal', 'food_supply', 'set_meal_times', 'log_bathroom', 'log_walk', 'set_walk_goal', 'routine_plan',
    'log_expense', 'post_to_social', 'start_lost_mode', 'mark_pet_found'];

function rrp_sipo_run_tool(string $name, array $args, array &$ctx): array
{
    $want = mb_strtolower(trim((string) ($args['pet_name'] ?? '')));
    unset($args['pet_name']);
    if ($want === '' || !in_array($name, RRP_SIPO_PET_TOOLS, true)
        || ($ctx['pet'] && mb_strtolower((string) $ctx['pet']['name']) === $want)) {
        return rrp_sipo_run_tool_on_pet($name, $args, $ctx);
    }
    // QA B27: act on the named pet for this one call; the chat stays on its own pet.
    $target = null;
    foreach (rrp_pets_by_owner((int) $ctx['owner']['id']) as $p) {
        if (mb_strtolower((string) $p['name']) === $want) {
            $target = $p;
        }
    }
    if (!$target) {
        return ['ok' => false, 'error' => 'No pet with that name.', 'pets' => array_column(rrp_pets_by_owner((int) $ctx['owner']['id']), 'name')];
    }
    $own = $ctx['pet'];
    $ctx['pet'] = $target;
    $res = rrp_sipo_run_tool_on_pet($name, $args, $ctx);
    $ctx['pet'] = $own;
    return $res + ['for_pet' => $target['name']];
}

function rrp_sipo_run_tool_on_pet(string $name, array $args, array &$ctx): array
{
    $owner = $ctx['owner'];
    $pet = $ctx['pet'];
    $ownerId = (int) $owner['id'];
    if (in_array($name, RRP_SIPO_APP_TOOLS, true)) {
        return rrp_sipo_run_app_tool($name, $args, $ctx);
    }

    switch ($name) {
        case 'create_pet_profile':
            if ($pet !== null) {
                return ['ok' => false, 'error' => 'This chat already has a pet.'];
            }
            $kind = rrp_sipo_norm_kind((string) ($args['kind'] ?? ''), (string) ($args['other_kind'] ?? ''));
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
            $check = rrp_sipo_profile_change_check($pet, $args);
            if ($check !== null) {
                return $check; // QA B01/B29: ask the owner first, nothing saved
            }
            if (!empty($args['name'])) {
                rrp_db()->prepare('UPDATE pets SET name = ? WHERE id = ? AND owner_id = ?')
                    ->execute([mb_substr(trim((string) $args['name']), 0, 60), (int) $pet['id'], $ownerId]);
                $ctx['events'][] = 'profile_updated';
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
                return ['ok' => false, 'error' => 'The pet profile is not created yet: get the name and kind first, then set the reminder.'];
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
                // The open chat can fire short reminders exactly on time (cron runs every 10–15 min).
                $ctx['reminders'][] = ['id' => (int) $res['reminder_id'], 'title' => (string) ($args['title'] ?? ''), 'due_utc' => $due];
            }
            $localDue = (new DateTimeImmutable($due, new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner));
            return ['ok' => !empty($res['ok']), 'error' => $res['error'] ?? null,
                'will_notify_at_local' => $localDue->format('Y-m-d H:i'), 'channel' => 'phone notification + this chat'];

        case 'care_record_done':
        case 'reminder_outcome':
        case 'treatment_start':
        case 'treatment_add_medicine':
        case 'treatment_log':
        case 'treatment_recheck':
        case 'treatment_finish':
        case 'food_save':
        case 'food_log_meal':
        case 'food_supply':
        case 'set_meal_times':
        case 'log_bathroom':
        case 'log_walk':
        case 'set_walk_goal':
        case 'routine_plan':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet yet.'];
            }
            $res = rrp_sipo_run_care_tool($name, $args, $owner, $pet);
            if (!empty($res['ok'])) {
                $ctx['events'][] = $name;
                $ctx['pet'] = rrp_pet_by_id((int) $pet['id'], $ownerId) ?: $pet;
            }
            return $res;

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
                // No home area: the map search still finds vets around the phone's own location.
                $ctx['links'][] = ['url' => 'https://www.google.com/maps/search/emergency+vet', 'label' => 'Vets near you on the map', 'external' => true];
                return ['ok' => false, 'error' => 'Home area not set, so no list. A map button for vets near the owner is shown under your reply: tell them to tap it and call the nearest one. Later, ask them to set the home area.'];
            }
            $vets = rrp_sipo_find_vets($c['lat'], $c['lng'], !empty($args['emergency_only']));
            $search = sprintf('https://www.google.com/maps/search/emergency+vet/@%F,%F,13z', $c['lat'], $c['lng']);
            $ctx['links'][] = ['url' => 'https://www.google.com/maps/search/emergency+vet/@' . sprintf('%F,%F', $c['lat'], $c['lng']) . ',13z', 'label' => 'Vets near you on the map', 'external' => true];
            return ['ok' => true, 'clinics' => $vets, 'map_search' => $search,
                'note' => $vets ? 'Give names, distance and phone numbers. A map button is shown under your reply.'
                    : 'No clinics in the map data near the home area. Do NOT just say none: tell the owner to tap the map button under your reply for vets near them, and to call the nearest one now.'];

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

function rrp_sipo_run_care_tool(string $name, array $a, array $owner, array $pet): array
{
    $tid = (int) ($a['treatment_id'] ?? 0);
    $str = static fn($k) => isset($a[$k]) && $a[$k] !== '' ? (string) $a[$k] : null;
    try {
        return match ($name) {
            'care_record_done' => rrp_care_record_done($owner, $pet, (string) ($a['routine_key'] ?? ''), (string) ($a['date'] ?? ''), $str('note'),
                isset($a['interval_days']) ? (int) $a['interval_days'] : null),
            'reminder_outcome' => rrp_care_reminder_outcome($owner, $pet, (int) ($a['reminder_id'] ?? 0), (string) ($a['outcome'] ?? ''), $str('new_date'), $str('note')),
            'treatment_start' => rrp_treatment_start($owner, $pet, $a),
            'treatment_add_medicine' => rrp_treatment_add_med($owner, $pet, $tid, $a),
            'treatment_log' => rrp_treatment_log($owner, $pet, $tid, (string) ($a['trend'] ?? ''), isset($a['med_given']) ? (bool) $a['med_given'] : null, $str('note')),
            'treatment_recheck' => rrp_treatment_add_recheck($owner, $pet, $tid, (string) ($a['when'] ?? ''), $str('clinic')),
            'treatment_finish' => rrp_treatment_finish($owner, $pet, $tid, $str('note')),
            'food_save' => rrp_food_save($owner, $pet, $a),
            'food_log_meal' => rrp_food_log_meal($owner, $pet, $a),
            'food_supply' => rrp_food_update_supply($owner, $pet, (int) ($a['food_id'] ?? 0),
                isset($a['remaining_grams']) ? (float) $a['remaining_grams'] : null, !empty($a['new_package']),
                isset($a['daily_grams']) ? (float) $a['daily_grams'] : null),
            'set_meal_times' => rrp_food_set_meal_times($pet, (array) ($a['times'] ?? []), $str('portion_note')),
            'log_bathroom' => (static function () use ($owner, $pet, $a, $str) { // QA B33: "peed 3 times" = 3 events
                $n = max(1, min(10, (int) ($a['count'] ?? 1)));
                $r = ['ok' => false];
                for ($i = 0; $i < $n; $i++) {
                    $r = rrp_bath_log($owner, $pet, (string) ($a['kind'] ?? ''), $str('stool'), $str('note'), 'chat', $i === 0 ? $str('at') : null);
                    if (empty($r['ok'])) {
                        break;
                    }
                }
                return $r + ['logged' => !empty($r['ok']) ? $n : 0];
            })(),
            'log_walk' => rrp_walk_log($owner, $pet, (int) ($a['minutes'] ?? 0),
                isset($a['km']) && is_numeric($a['km']) ? (int) round((float) $a['km'] * 1000) : null, null, 'chat', null, $str('note')),
            'set_walk_goal' => rrp_walk_set_goal($pet, (int) ($a['minutes_per_day'] ?? 0)),
            'routine_plan' => rrp_care_plan($owner, $pet, (string) ($a['routine_key'] ?? ''), (int) ($a['interval_days'] ?? 0), $str('start')),
            default => ['ok' => false, 'error' => 'Unknown tool'],
        };
    } catch (Throwable $e) {
        error_log('care tool ' . $name . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save that — try again.'];
    }
}

/**
 * QA B01/B29: changing an already-saved value, or saving an odd one, needs the owner's yes.
 * @return array|null error for the model (nothing saved), or null when it may go ahead
 */
function rrp_sipo_profile_change_check(array $pet, array $args): ?array
{
    $confirmed = ($args['confirmed'] ?? false) === true;
    $odd = static fn(string $v) => (bool) preg_match('/\d/', $v)
        || (mb_strlen($v) >= 6 && !preg_match('/[aeiouy]/i', $v))
        || preg_match('/(asdf|qwer|zxcv|hjkl|jkl;)/i', $v);
    $changes = [];
    $warn = [];
    $fields = ['name' => 'name', 'breed' => 'breed', 'sex' => 'sex', 'birth_date' => 'birth_date', 'weight_kg' => 'weight_kg'];
    foreach ($fields as $arg => $col) {
        if (!isset($args[$arg]) || $args[$arg] === '' || $args[$arg] === null) {
            continue;
        }
        $new = (string) $args[$arg];
        $old = (string) ($pet[$col] ?? '');
        if ($col === 'sex' && $old === 'unknown') {
            $old = '';
        }
        if (in_array($arg, ['name', 'breed'], true) && $odd($new)) {
            $warn[] = "{$arg} \"{$new}\" looks unusual";
        }
        if ($arg === 'weight_kg' && (float) $old > 0 && abs((float) $new - (float) $old) / (float) $old > 0.2) {
            $warn[] = "weight jumps from {$old} kg to {$new} kg";
        }
        // A new weigh-in is a measurement, not a correction: only big jumps (above) need a yes.
        if ($arg !== 'weight_kg' && $old !== '' && mb_strtolower($old) !== mb_strtolower($new)) {
            $changes[] = "{$arg}: {$old} → {$new}";
        }
    }
    if (($changes || $warn) && !$confirmed) {
        return ['ok' => false, 'needs_confirm' => true, 'changes' => $changes, 'check' => $warn,
            'error' => 'Not saved yet. Ask the owner to confirm (old → new' . ($warn ? ', and whether the unusual value is right' : '') . '), then call again with confirmed=true.'];
    }
    return null;
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
    if (isset($args['vet_kcal_per_day']) && is_numeric($args['vet_kcal_per_day']) && $args['vet_kcal_per_day'] > 20 && $args['vet_kcal_per_day'] < 6000) {
        $sets[] = 'vet_kcal_per_day = ?'; $vals[] = (int) $args['vet_kcal_per_day']; $saved[] = 'vet_kcal_per_day';
    }
    if (in_array($args['activity'] ?? null, ['low', 'normal', 'high'], true)) {
        $sets[] = 'activity = ?'; $vals[] = $args['activity']; $saved[] = 'activity';
    }
    if (in_array($args['lifestyle'] ?? null, ['indoor', 'outdoor', 'mixed'], true)) {
        $sets[] = 'lifestyle = ?'; $vals[] = $args['lifestyle']; $saved[] = 'lifestyle';
    }
    if (!$sets) {
        return [];
    }
    $vals[] = $petId;
    $vals[] = $ownerId;
    try {
        rrp_db()->prepare('UPDATE pets SET ' . implode(', ', $sets) . ' WHERE id = ? AND owner_id = ?')->execute($vals);
        foreach ($saved as $k) {
            rrp_coin_award($ownerId, 'profile_field', 'Profile: ' . str_replace('_', ' ', $k), 'profile:' . $k, $petId, $petId);
        }
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
    $key = sprintf('vets3:%.2f,%.2f', $lat, $lng);
    $rows = rrp_sipo_cache_get($key, 86400);
    if (!is_array($rows) || !$rows) {
        // QA B23: Overpass timed out (25 s+) so SIPO found no clinics. Nominatim's tag search answers in
        // ~2 s; Overpass is only a short backup. A failure is never cached.
        $rows = rrp_sipo_vets_nominatim($lat, $lng, 15) ?? [];
        if (count($rows) < 3) {
            $rows = rrp_sipo_vets_nominatim($lat, $lng, 40) ?? $rows;
        }
        if (!$rows) {
            $rows = rrp_sipo_vets_overpass($lat, $lng) ?? [];
        }
        usort($rows, static fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
        if ($rows) {
            rrp_sipo_cache_set($key, $rows);
        }
    }
    if ($emergencyOnly) {
        $em = array_values(array_filter($rows, static fn($r) => $r['emergency']));
        if ($em) {
            $rows = $em;
        }
    }
    // Clinics with a phone first among the closest: in an emergency the owner needs to call.
    $near = array_slice($rows, 0, 8);
    usort($near, static fn($a, $b) => [empty($a['phone']), $a['distance_km']] <=> [empty($b['phone']), $b['distance_km']]);
    return array_slice($near, 0, 5);
}

/** @return list<array>|null null when the service failed */
function rrp_sipo_vets_nominatim(float $lat, float $lng, int $km): ?array
{
    $dLat = $km / 111.0;
    $dLng = $km / (111.0 * max(0.2, cos(deg2rad($lat))));
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => '[amenity=veterinary]', 'format' => 'jsonv2', 'limit' => 40, 'extratags' => 1, 'addressdetails' => 1, 'bounded' => 1,
        'viewbox' => sprintf('%F,%F,%F,%F', $lng - $dLng, $lat + $dLat, $lng + $dLng, $lat - $dLat),
    ]);
    $data = rrp_sipo_http_json($url, 8);
    if (!is_array($data)) {
        return null;
    }
    $rows = [];
    foreach ($data as $r) {
        $elat = (float) ($r['lat'] ?? 0);
        $elng = (float) ($r['lon'] ?? 0);
        if (!$elat) {
            continue;
        }
        $x = $r['extratags'] ?? [];
        $ad = $r['address'] ?? [];
        $rows[] = rrp_sipo_vet_row((string) ($r['name'] ?: 'Veterinary clinic'), $x['phone'] ?? $x['contact:phone'] ?? $x['contact:mobile'] ?? null,
            trim(implode(' ', array_filter([$ad['house_number'] ?? null, $ad['road'] ?? null, $ad['suburb'] ?? $ad['city'] ?? null]))) ?: null,
            $lat, $lng, $elat, $elng, (($x['emergency'] ?? '') === 'yes') || str_contains(strtolower((string) ($x['opening_hours'] ?? '')), '24/7'));
    }
    return $rows;
}

/** @return list<array>|null */
function rrp_sipo_vets_overpass(float $lat, float $lng): ?array
{
    $q = sprintf('[out:json][timeout:8];nwr["amenity"="veterinary"](around:20000,%F,%F);out center tags 40;', $lat, $lng);
    $data = rrp_sipo_http_json('https://overpass-api.de/api/interpreter', 9, 'data=' . rawurlencode($q));
    if (!is_array($data) || !isset($data['elements'])) {
        return null;
    }
    $rows = [];
    foreach ($data['elements'] as $el) {
        $t = $el['tags'] ?? [];
        $elat = (float) ($el['lat'] ?? $el['center']['lat'] ?? 0);
        $elng = (float) ($el['lon'] ?? $el['center']['lon'] ?? 0);
        if ($elat) {
            $rows[] = rrp_sipo_vet_row((string) ($t['name'] ?? $t['name:en'] ?? 'Veterinary clinic'), $t['phone'] ?? $t['contact:phone'] ?? null,
                trim(implode(' ', array_filter([$t['addr:housenumber'] ?? null, $t['addr:street'] ?? null, $t['addr:city'] ?? null]))) ?: null,
                $lat, $lng, $elat, $elng, (($t['emergency'] ?? '') === 'yes') || str_contains(strtolower((string) ($t['opening_hours'] ?? '')), '24/7'));
        }
    }
    return $rows;
}

function rrp_sipo_vet_row(string $name, ?string $phone, ?string $address, float $lat, float $lng, float $elat, float $elng, bool $emergency): array
{
    return [
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'distance_km' => round(rrp_haversine_km($lat, $lng, $elat, $elng), 1),
        'emergency' => $emergency,
        'map' => sprintf('https://www.google.com/maps/search/?api=1&query=%F,%F', $elat, $elng),
    ];
}
