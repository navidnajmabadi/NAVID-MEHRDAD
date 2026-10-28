<?php
declare(strict_types=1);

/**
 * New-owner setup in 4 sections (Pet info → Routines → Health → Money). SIPO introduces
 * each section (why, how it helps, time), asks one question at a time and offers
 * choice buttons. Progress is read from real data, so it never drifts from the chat.
 */

const RRP_SETUP_SECTIONS = [
    'pet' => ['Pet info', 1, 'so I know who I am looking after and can tailor food, weight and care to them'],
    'routine' => ['Routines', 2, 'so I can remind you at the right moment (meals, walks, grooming) and you never have to remember'],
    'health' => ['Health', 2, 'so I can track vaccines and parasite prevention, warn you early and have the vet details ready'],
    'money' => ['Money', 1, 'so I can show what your pet costs each month and help you plan food and vet spending'],
];

/** Owner-said facts for a pet, keyed. */
function rrp_setup_facts(int $petId): array
{
    try {
        $st = rrp_db()->prepare('SELECT fact_key, fact_value FROM sipo_memory WHERE pet_id = ?');
        $st->execute([$petId]);
        return array_column($st->fetchAll(), 'fact_value', 'fact_key');
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{sections:list<array{key:string,label:string,minutes:int,why:string,done:list<string>,missing:list<string>,complete:bool}>,
 *   current:?array, number:int, complete:bool, minutes_left:int}
 */
function rrp_sipo_setup_plan(?array $pet): array
{
    $items = [];
    if ($pet === null) {
        $items['pet'] = ['name' => false, 'kind' => false, 'breed' => false, 'age' => false, 'sex' => false, 'weight' => false, 'neutered' => false];
        $items['routine'] = ['meal times' => false, 'walks or play' => false];
        $items['health'] = ['last vaccine' => false, 'parasite prevention' => false, 'vet clinic' => false, 'conditions or allergies' => false];
        $items['money'] = ['monthly budget or a first cost' => false];
    } else {
        $pid = (int) $pet['id'];
        $f = rrp_setup_facts($pid);
        $has = static fn(array $keys) => (bool) array_intersect_key($f, array_flip($keys));
        $count = static function (string $sql) use ($pid): int {
            try {
                $st = rrp_db()->prepare($sql);
                $st->execute([$pid]);
                return (int) $st->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        $p = rrp_sipo_profile_progress($pet);
        $miss = array_flip($p['missing']);
        $items['pet'] = [
            'name' => true, 'kind' => true,
            'breed' => !isset($miss['breed']), 'age' => !isset($miss['age or birthday']), 'sex' => !isset($miss['sex']),
            'weight' => !isset($miss['weight']), 'neutered' => !isset($miss['neutered or not']),
        ];
        $dog = ($pet['type'] ?? '') === 'dog';
        $items['routine'] = [
            'meal times' => $count('SELECT COUNT(*) FROM feeding_schedules WHERE pet_id = ?') > 0,
            ($dog ? 'daily walk goal' : 'play or litter routine') => $dog ? !empty($pet['walk_goal_minutes']) : $has(['play_routine', 'litter_routine', 'litter_cleaning', 'play_time']),
        ];
        $items['health'] = [
            'last vaccine' => !isset($miss['last vaccine date']),
            'parasite prevention' => $count("SELECT COUNT(*) FROM care_reminders WHERE pet_id = ? AND kind = 'routine' AND merge_group IN ('parasite','flea_tick','deworm')") > 0
                || $has(['parasite_prevention', 'flea_tick', 'deworming', 'last_deworming']),
            'vet clinic' => $has(['vet_clinic', 'vet_name', 'vet']) || $count('SELECT COUNT(*) FROM vet_appointments WHERE pet_id = ?') > 0,
            'conditions or allergies' => !empty($pet['medical_conditions']) || $has(['allergies', 'allergy', 'conditions', 'no_conditions', 'health_conditions']),
        ];
        $items['money'] = [
            'monthly budget or a first cost' => $has(['monthly_budget', 'budget', 'food_budget']) || $count('SELECT COUNT(*) FROM pet_expenses WHERE pet_id = ?') > 0,
        ];
    }

    $sections = [];
    $current = null;
    $left = 0;
    foreach (RRP_SETUP_SECTIONS as $key => [$label, $minutes, $why]) {
        $done = array_keys(array_filter($items[$key]));
        $missing = array_keys(array_filter($items[$key], static fn($v) => !$v));
        $s = ['key' => $key, 'label' => $label, 'minutes' => $minutes, 'why' => $why, 'done' => $done, 'missing' => $missing, 'complete' => !$missing];
        $sections[] = $s;
        if ($missing) {
            $left += $minutes;
            $current ??= $s;
        }
    }
    $number = $current ? (int) array_search($current['key'], array_keys(RRP_SETUP_SECTIONS), true) + 1 : count($sections);
    return ['sections' => $sections, 'current' => $current, 'number' => $number, 'complete' => $current === null, 'minutes_left' => $left];
}

/** Prompt block while setup is incomplete; null when done or the owner skipped it all. */
function rrp_sipo_setup_block(?array $pet, ?array $thread): ?string
{
    $plan = rrp_sipo_setup_plan($pet);
    if ($plan['complete']) {
        return null;
    }
    $c = $plan['current'];
    $list = implode(' → ', array_map(static fn($s) => ($s['complete'] ? '✓ ' : '') . $s['label'] . ' (~' . $s['minutes'] . ' min)', $plan['sections']));
    return "# Setup (new owner, profile not finished)\n"
        . "Sections: {$list}. About {$plan['minutes_left']} min left in total.\n"
        . "Now: Section {$plan['number']} of 4 · {$c['label']}. Still missing: " . implode(', ', $c['missing'])
        . ($c['done'] ? '. Already known: ' . implode(', ', $c['done']) : '') . ".\n"
        . "How to run it:\n"
        . "- The first time you start a section, open with one short line: \"Section {$plan['number']} of 4 · {$c['label']} (about {$c['minutes']} min)\", then why: {$c['why']}. Then the first question.\n"
        . "- In the very first message to a new owner, also say the 4 sections and the total time in one line, and that they can say skip anytime.\n"
        . "- ONE question per message. For questions with a few likely answers (kind, sex, neutered, age range, meals per day, walk length, vaccines up to date, budget range) call ask_choices with 2 to 6 short options; the app adds \"Other\" automatically. Name, breed and weight are free text: no choices.\n"
        . "- Save every answer with the right tool before moving on (update_pet_profile, set_meal_times, set_walk_goal, care_record_done, save_pet_fact, log_expense).\n"
        . "- If the owner says skip, or asks about something else, follow them; come back to setup later, gently, once.\n"
        . "- When the owner sends a photo, lab report or label, talk only about that file in your reply; no setup question in the same message (QA B12).\n"
        . "- Never ask again for something listed as Already known.\n"
        . "- When a section is finished, celebrate in a few words (\"Pet info done ✅ 1 of 4\").";
}

/** For the chat header bar: sections done of 4, item-level percent, current section label. */
function rrp_sipo_setup_progress(?array $pet): array
{
    $plan = rrp_sipo_setup_plan($pet);
    $all = 0;
    $done = 0;
    foreach ($plan['sections'] as $s) {
        $all += count($s['done']) + count($s['missing']);
        $done += count($s['done']);
    }
    return [
        'done' => count(array_filter(array_column($plan['sections'], 'complete'))),
        'total' => count($plan['sections']),
        'percent' => $all ? (int) round(100 * $done / $all) : 0,
        'section' => $plan['current']['label'] ?? null,
        'number' => $plan['number'],
        'minutes_left' => $plan['minutes_left'],
        // QA B11: the bar opens a checklist of the 4 sections and what each still needs.
        'list' => array_map(static fn($sec) => ['label' => $sec['label'], 'done' => $sec['complete'], 'missing' => $sec['missing']], $plan['sections']),
    ];
}
