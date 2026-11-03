<?php
declare(strict_types=1);

/**
 * Token diet: every model call used to carry all 32 tool definitions (~4.9K tokens) and every prompt
 * block (~6.3K tokens), and a reply with tools makes 2 to 4 calls. Most turns need a fraction of that.
 *
 * The router reads the owner's message plus SIPO's previous question (so a bare "yes" keeps its topic)
 * and sends only the tool groups and prompt blocks that fit. When it is unsure, the group is included.
 * Proactive messages (no owner text) and emergencies always get everything.
 */

/** Tools that every turn can need. */
const RRP_ROUTE_CORE_TOOLS = ['create_pet_profile', 'update_pet_profile', 'save_pet_fact', 'set_reminder', 'ask_choices',
    'flag_emergency', 'care_record_done', 'reminder_outcome'];

/** key => [regex on the lowercase text, tools it unlocks]. */
const RRP_ROUTE_GROUPS = [
    'food' => ['/food|eat|meal|feed|kibble|calor|portion|treat|bowl|diet|hungry|supply|\bbag\b|breakfast|dinner|lunch/', ['food_save', 'food_log_meal', 'food_supply', 'set_meal_times']],
    'treatment' => ['/medic|pill|drops|treatment|dose|prescri|antibiotic|course|surgery|injur|sick|vomit|diarr|limp|itch|allerg|vet said|insulin|tablet/', ['treatment_start', 'treatment_add_medicine', 'treatment_log', 'treatment_recheck', 'treatment_finish']],
    'routine' => ['/walk|exercise|poop|pee\b|bathroom|toilet|teeth|brush|nail|bath|groom|routine|every day|daily|weekly|vaccin|worm|flea|tick|rabies|booster|checkup|check-up/', ['routine_plan', 'log_bathroom', 'log_walk', 'set_walk_goal', 'set_meal_times']],
    'lost' => ['/lost|missing|escaped|ran away|runaway|found|came home|is home/', ['start_lost_mode', 'mark_pet_found']],
    'money' => ['/spent|spend|cost|expens|paid|price|budget|money|[$€£]|bill\b|invoice/', ['log_expense', 'spending_summary']],
    'pets' => ['/another pet|add (a |my )?(pet|dog|cat)|second (dog|cat)|switch|my pets|new pet|puppy|kitten|adopt/', ['add_pet', 'switch_pet', 'list_my_pets']],
    'app' => ['/\bopen\b|show me|go to|take me|page|dashboard|settings|screen|where (is|can)/', ['open_page']],
    'vets' => ['/\bvet\b|vets\b|clinic|emergency|doctor|hospital/', ['find_vets']],
    'weather' => ['/weather|rain|snow|cold|hot\b|temperature|outside|forecast|walk|today|tonight/', ['get_weather']],
    'support' => ['/support|ticket|bug|contact|complain|problem with (the )?app|not working/', ['open_support_ticket']],
    'profile' => ['/my (name|phone|number|address|email|city)|call me|phone|address/', ['update_my_profile']],
];

/** Prompt blocks that only matter for some topics: block key => regex. */
const RRP_ROUTE_BLOCKS = [
    'routines' => '/vaccin|worm|flea|tick|rabies|booster|teeth|brush|nail|bath|ear|routine|checkup|check-up|blood|due|overdue|remind|schedule|every|care|health/',
    'labs' => '/lab|blood|urine|test|result|value|alt\b|bun\b|kidney|liver|thyroid|sugar|glucose|range|report|📎/',
    'knowledge' => '/health|sick|vomit|diarr|itch|limp|food|eat|diet|weight|fat|thin|behav|bark|scratch|teeth|ear|skin|cough|allerg|pain|tired|water|drink|poop|pee|worm|flea|tick|vaccin|age|senior|puppy|kitten|spay|neuter|heat/',
];

/** Text of the current turn (owner message + SIPO's last words); '' means "no routing, send everything". */
function rrp_sipo_turn_hint(?string $set = null): string
{
    static $hint = '';
    if ($set !== null) {
        $hint = mb_strtolower($set);
    }
    return $hint;
}

function rrp_sipo_hint_matches(string $block): bool
{
    $h = rrp_sipo_turn_hint();
    if ($h === '' || !isset(RRP_ROUTE_BLOCKS[$block])) {
        return true;
    }
    return (bool) preg_match(RRP_ROUTE_BLOCKS[$block], $h);
}

/**
 * @param list<array<string,mixed>> $defs full tool list
 * @return list<array<string,mixed>> the tools this turn can use
 */
function rrp_sipo_route_tools(array $defs): array
{
    $h = rrp_sipo_turn_hint();
    if ($h === '' || mb_strlen($h) > 1500 || str_contains($h, '📎')) {
        return $defs; // proactive message, very long message, or a file summary: everything
    }
    $allow = array_flip(RRP_ROUTE_CORE_TOOLS);
    foreach (RRP_ROUTE_GROUPS as [$re, $tools]) {
        if (preg_match($re, $h)) {
            foreach ($tools as $t) {
                $allow[$t] = true;
            }
        }
    }
    return array_values(array_filter($defs, static fn($d) => isset($allow[$d['function']['name']])));
}
