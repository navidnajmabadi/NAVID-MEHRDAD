<?php
declare(strict_types=1);

/**
 * Every SIPO reply becomes a "request" row (si_request_log): topic, score, the checks that decided
 * the score, and what it cost in tokens. NAKI's graph, the test lab and the Admin token view read this.
 * Stored text is redacted: no emails, phones, pet or owner names.
 */

const RRP_DOMAINS = [
    'health' => 'Health', 'labs' => 'Labs', 'routine' => 'Routines', 'reminder' => 'Reminders', 'food' => 'Food',
    'lost' => 'Lost pet', 'money' => 'Expenses', 'profile' => 'Profile', 'info' => 'Information', 'search' => 'Search',
    'safety' => 'Safety', 'general' => 'General',
];

/** Topic of one owner message (cheap keyword scoring, first best match). */
function rrp_sipo_classify_domain(string $text): string
{
    $t = mb_strtolower($text);
    $rules = [
        'safety' => '/poison|chocolate|ate (a |an )?(whole )?(bar|bag|box)|emergency|seiz|collaps|bleed|not breathing|swallow|dose|mg\b|overdose/',
        'lost' => '/\blost\b|missing|escaped|ran away|runaway|came home|is home|found (him|her|them)/',
        'labs' => '/lab\b|blood test|urine|result|\balt\b|\bbun\b|kidney|liver|thyroid|glucose|📎/',
        'reminder' => '/remind|alarm|notify|at \d{1,2}(:\d{2})?\s?(am|pm)?|in \d+ (min|hour)/',
        'money' => '/spent|spend|cost|expens|paid|price|budget|money|[$€£]/',
        'food' => '/food|eat|meal|feed|kibble|calor|portion|treat\b|diet|hungry|breakfast|dinner/',
        'routine' => '/walk|poop|pee\b|bathroom|teeth|brush|nail|bath\b|groom|routine|daily|vaccin|worm|flea|tick|rabies|booster/',
        'search' => '/\bvets?\b|clinic|near me|nearby|find (a|me)|weather|where can/',
        'profile' => '/name|breed|birthday|age\b|weigh|sex\b|neuter|spay|my (phone|address|email)|call me|profile/',
        'health' => '/sick|vomit|diarr|itch|limp|cough|skin|ear|eye|pain|tired|allerg|sneez|scratch|symptom|medic|pill|drops/',
    ];
    foreach ($rules as $d => $re) {
        if (preg_match($re, $t)) {
            return $d;
        }
    }
    return mb_strlen(trim($t)) > 25 ? 'info' : 'general';
}

/** Remove what could identify someone, then shorten. */
function rrp_sipo_redact_request(string $text, array $names = []): string
{
    $t = function_exists('rrp_naki_redact') ? rrp_naki_redact($text) : $text;
    foreach ($names as $n) {
        $n = trim((string) $n);
        if (mb_strlen($n) >= 2) {
            $t = preg_replace('/\b' . preg_quote($n, '/') . '\b/iu', '[name]', $t) ?? $t;
        }
    }
    return mb_substr(trim(preg_replace('/\s+/', ' ', $t) ?? $t), 0, 380);
}

/**
 * Score one reply 0 to 5 from fixed checks. $facts: tools used and whether they succeeded, owner's next
 * message when known. Returns [score, issues, checks].
 *
 * @return array{score:float, issues:list<string>, checks:array<string,bool>}
 */
function rrp_sipo_score_reply(string $reply, array $tools, array $ctx = []): array
{
    $issues = [];
    $checks = [];
    $sig = function_exists('rrp_naki_signals') ? rrp_naki_signals($reply, $ctx['next_owner'] ?? null, ['tools' => $tools, 'proactive' => $ctx['proactive'] ?? null]) : ['issues' => []];
    foreach ($sig['issues'] as $i) {
        $issues[] = $i;
    }
    // Claimed an action but nothing was saved.
    $claims = (bool) preg_match('/\b(done|saved|updated|changed|i\'?ll remind you|i\'?ve set|booked)\b/i', $reply);
    $acted = (bool) array_intersect($tools, ['update_pet_profile', 'save_pet_fact', 'set_reminder', 'care_record_done', 'reminder_outcome',
        'routine_plan', 'food_save', 'set_meal_times', 'log_expense', 'treatment_start', 'start_lost_mode']);
    $checks['claims_match_actions'] = !$claims || $acted || !empty($ctx['proactive']);
    if (!$checks['claims_match_actions'] && !in_array('false_claim', $issues, true)) {
        $issues[] = 'false_claim';
    }
    $checks['one_question'] = substr_count($reply, '?') <= 1;
    $checks['short'] = str_word_count(strip_tags($reply)) <= 90;
    $checks['no_tool_errors'] = empty($ctx['tool_errors']);
    if (!$checks['no_tool_errors']) {
        $issues[] = 'tool_error';
    }
    $checks['token_budget'] = (int) ($ctx['total_tokens'] ?? 0) <= 9000;
    if (!$checks['token_budget']) {
        $issues[] = 'token_heavy';
    }
    $issues = array_values(array_unique($issues));
    $score = max(0.0, 5.0 - 0.8 * count($issues));
    return ['score' => round($score, 1), 'issues' => $issues, 'checks' => $checks];
}

/** Write one request row. Never throws. */
function rrp_sipo_log_request(array $ctx, string $ownerBody, array $reply, ?array $assistant, string $source = 'live'): void
{
    try {
        $names = [(string) ($ctx['pet']['name'] ?? ''), (string) ($ctx['owner']['nickname'] ?? ''), (string) ($ctx['owner']['name'] ?? '')];
        $u = (array) ($ctx['usage'] ?? []);
        $tools = (array) ($reply['tools'] ?? []);
        $sc = rrp_sipo_score_reply((string) ($reply['text'] ?? ''), $tools, [
            'tool_errors' => $ctx['tool_errors'] ?? [], 'proactive' => $ctx['proactive'] ?? null,
            'total_tokens' => ($u['prompt'] ?? 0) + ($u['completion'] ?? 0),
        ]);
        rrp_db()->prepare(
            'INSERT INTO si_request_log (message_id, domain, req_text, score, issues, checks_json, prompt_tokens, completion_tokens, cached_tokens, reasoning_tokens, rounds, tools_used, tools_offered, model, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $assistant['id'] ?? null, rrp_sipo_classify_domain($ownerBody), rrp_sipo_redact_request($ownerBody, $names),
            $sc['score'], implode(',', $sc['issues']), json_encode($sc['checks']),
            (int) ($u['prompt'] ?? 0), (int) ($u['completion'] ?? 0), (int) ($u['cached'] ?? 0), (int) ($u['reasoning'] ?? 0),
            max(1, (int) ($u['rounds'] ?? 1)), mb_substr(implode(',', $tools), 0, 250), (int) ($u['tools_offered'] ?? 0),
            mb_substr((string) ($reply['model'] ?? ''), 0, 60), $source,
        ]);
    } catch (Throwable $e) {
        error_log('rrp_sipo_log_request: ' . $e->getMessage()); // migration 024 not run yet, or a full table: never block a reply
    }
}
