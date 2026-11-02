<?php
declare(strict_types=1);

/**
 * SIPO drives the conversation. While a pet's setup is unfinished, every reply must end with the
 * next question; the owner should never have to type "next?" (live bug: after "I'll remind you at
 * 17:00 for the walk" SIPO stopped and waited). The model is asked to do this in the prompt, and
 * this code guarantees it: if a model reply has no question, the next missing item is asked.
 *
 * Never drives during an emergency, Lost mode, a pending learning question, or after the owner
 * asked to stop. An item that was asked twice without an answer is skipped, so nothing nags.
 */

const RRP_DRIVE_MAX_ASKS = 2;

/** Words that mean "stop asking me setup questions for now". */
function rrp_drive_is_stop(string $body): bool
{
    return (bool) preg_match('/\b(stop|enough|no more questions|not now|later|skip (all|everything|the rest)|leave me)\b/i', $body);
}

/** @return array{text:string, choices:?list<string>}|null question for one missing setup item */
function rrp_drive_question(string $item, string $name, string $type): ?array
{
    $n = $name;
    return match ($item) {
        'breed' => ['text' => "What's {$n}'s breed? “Mixed” or “not sure” is fine.", 'choices' => null],
        'age' => ['text' => "How old is {$n}? A birthday or a rough age works.", 'choices' => null],
        'sex' => ['text' => "Is {$n} a boy or a girl?", 'choices' => ['Boy', 'Girl']],
        'weight' => ['text' => "How much does {$n} weigh? kg or lb is fine, or say “not sure”.", 'choices' => null],
        'neutered' => ['text' => "Is {$n} neutered or spayed?", 'choices' => ['Yes', 'No', 'Not sure']],
        'meal times' => ['text' => "What times does {$n} usually eat? For example morning and evening.", 'choices' => null],
        'daily walk goal' => ['text' => "What's {$n}'s daily walk goal in minutes?", 'choices' => ['15 min', '30 min', '45 min', '1 hour']],
        'play or litter routine' => ['text' => $type === 'cat'
            ? "How does {$n} play and use the litter box day to day? A short description is perfect."
            : "How does {$n} spend the day, and how much exercise do they get?", 'choices' => null],
        'last vaccine' => ['text' => "When was {$n}'s last vaccine? Check the certificate if you have it.", 'choices' => ['This year', 'Last year', 'Longer ago', 'Not sure']],
        'parasite prevention' => ['text' => "Is {$n} on flea, tick or worm prevention?", 'choices' => ['Yes', 'No', 'Not sure']],
        'vet clinic' => ['text' => "Which vet clinic do you use for {$n}?", 'choices' => null],
        'conditions or allergies' => ['text' => "Does {$n} have any health conditions or allergies?", 'choices' => ['None', 'Yes', 'Not sure']],
        'monthly budget or a first cost' => ['text' => "Roughly how much do you spend on {$n} each month? A rough number is fine.", 'choices' => null],
        default => null,
    };
}

/** True when the reply already ends in a question or offers buttons. */
function rrp_drive_reply_asks(array $reply): bool
{
    if (!empty($reply['choices'])) {
        return true;
    }
    $text = trim((string) ($reply['text'] ?? ''));
    $tail = mb_substr($text, -240);
    return str_contains($tail, '?');
}

/**
 * @param array $reply reply produced by the model (text, choices, ...)
 * @return array the reply, with the next setup question appended when it was missing
 */
function rrp_drive_next(array &$ctx, array $reply, string $ownerBody = ''): array
{
    $pet = $ctx['pet'] ?? null;
    if (!$pet || !empty($ctx['emergency']) || rrp_drive_reply_asks($reply) || trim((string) ($reply['text'] ?? '')) === '') {
        return $reply;
    }
    $ownerId = (int) $ctx['owner']['id'];
    $pid = (int) $pet['id'];
    $fresh = rrp_pet_by_id($pid, $ownerId) ?: $pet;

    // The owner asked us to stop: respect it for 24 hours.
    if ($ownerBody !== '' && rrp_drive_is_stop($ownerBody)) {
        rrp_learn_set($ownerId, $pid, 'drive_pause', 'paused');
        return $reply;
    }
    $pause = rrp_learn_state($pid, 'drive_pause');
    if ($pause['stage'] === 'paused' && time() - $pause['at'] < 86400) {
        return $reply;
    }
    if (function_exists('rrp_open_lost_for_pet') && rrp_open_lost_for_pet($pid)) {
        return $reply;
    }
    if (function_exists('rrp_learn_pending') && rrp_learn_pending($pid)) {
        return $reply; // a check-in question is already waiting for an answer
    }

    $plan = rrp_sipo_setup_plan($fresh);
    if ($plan['complete']) {
        return $reply;
    }
    $name = (string) $fresh['name'];
    $type = (string) ($fresh['type'] ?? '');
    foreach ($plan['sections'] as $sec) {
        foreach ($sec['missing'] as $item) {
            $slug = 'drive_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($item));
            $asked = (int) rrp_learn_state($pid, $slug)['stage'];
            if ($asked >= RRP_DRIVE_MAX_ASKS) {
                continue; // asked twice already: leave it, move on
            }
            $q = rrp_drive_question($item, $name, $type);
            if ($q === null) {
                continue;
            }
            rrp_learn_set($ownerId, $pid, $slug, (string) ($asked + 1));
            $first = $item === ($sec['missing'][0] ?? '') && count($sec['done']) === 0;
            $intro = $first ? "Section {$plan['number']} of 4 · {$sec['label']} (about {$sec['minutes']} min), {$sec['why']}.\n" : '';
            $reply['text'] = rtrim((string) $reply['text']) . "\n\n" . $intro . $q['text'];
            if ($q['choices']) {
                $reply['choices'] = $q['choices'];
            }
            return rrp_sipo_guard_choices($reply, '');
        }
    }
    return $reply;
}
