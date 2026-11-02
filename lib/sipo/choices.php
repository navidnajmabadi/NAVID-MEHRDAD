<?php
declare(strict_types=1);

/**
 * Choice guard: the model may attach tap buttons to a question, and sometimes attaches the wrong
 * ones (live bug: asked "What's her breed?" under Dog / Cat buttons after the owner had just tapped
 * Dog). Whatever the model says, buttons only survive when they fit the question actually asked.
 *
 * Rules, in order:
 *  1. Buttons the owner just answered with are stale: drop them.
 *  2. The question is the last sentence ending in "?". If it is about a free-text topic (breed, name,
 *     weight, birthday, phone, address, ...), only skip or confirm style buttons may stay ("Mixed", "Not sure", "Yes", "No", "Later").
 *  3. A button set that belongs to another topic (kind, sex, neutered, yes/no) is dropped unless the
 *     question is about that topic.
 *  4. Fewer than two buttons left means no buttons.
 */

/** Topic words as they appear in the question sentence. */
const RRP_CHOICE_TOPICS = [
    'breed' => '/\bbreeds?\b/i',
    'kind' => '/\b(dog or (a )?cat|cat or (a )?dog|what kind of (pet|animal)|which (kind|type)|what type of|species|kind of pet)\b/i',
    'sex' => '/\b(boy or (a )?girl|girl or (a )?boy|male or female|female or male|sex\b|gender)\b/i',
    'neutered' => '/\b(neuter|neutered|spay|spayed|fixed)\b/i',
    'name' => '/\b(name)\b/i',
    'walk' => '/\bwalk goal\b|\bminutes\b.*\bwalk|\bwalk\b.*\bminutes\b/i',
    'weight' => '/\b(weigh|weight|how heavy|kilo|kg|pounds?|lbs?)\b/i',
    'birthday' => '/\b(birthday|born|how old|age\b|birth ?date|date of birth)\b/i',
    'contact' => '/\b(phone|number|address|email|where do you live|city|country)\b/i',
];

/** Topics answered by typing, not tapping. */
const RRP_FREE_TEXT_TOPICS = ['breed', 'name', 'weight', 'contact'];

/** Which topic a set of buttons belongs to, or null when it is a general set. */
function rrp_choice_set_topic(array $choices): ?string
{
    $norm = array_map(static fn($c) => mb_strtolower(trim((string) $c)), $choices);
    $kinds = ['dog', 'cat', 'puppy', 'kitten', 'rabbit', 'bird', 'other pet'];
    $sexes = ['male', 'female', 'boy', 'girl'];
    $neut = ['neutered', 'spayed', 'not neutered', 'not spayed', 'intact', 'fixed', 'not fixed'];
    $in = static fn(array $set) => $norm && !array_diff($norm, $set);
    if ($in($kinds)) {
        return 'kind';
    }
    if ($in($sexes)) {
        return 'sex';
    }
    if ($in($neut)) {
        return 'neutered';
    }
    return null;
}

/** The question the owner is being asked: the last sentence that ends in "?" (else the last sentence). */
function rrp_choice_question(string $text): string
{
    // Drop the progress line the app appends ("Violet's profile: 2/7").
    $text = preg_replace('/\n+[^\n]*profile:\s*\d+\/\d+[^\n]*$/iu', '', trim($text)) ?? $text;
    $parts = preg_split('/(?<=[.!?…])\s+|\n+/u', trim($text)) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($p) => $p !== ''));
    for ($i = count($parts) - 1; $i >= 0; $i--) {
        if (str_contains($parts[$i], '?')) {
            return $parts[$i];
        }
    }
    return $parts ? $parts[count($parts) - 1] : '';
}

/** The topic of a question, or null if none of the known topics is asked. */
function rrp_choice_question_topic(string $question): ?string
{
    // Specific topics win over loose ones ("breed" over "kind of dog").
    foreach (['breed', 'kind', 'sex', 'neutered', 'walk', 'weight', 'birthday', 'contact', 'name'] as $t) {
        if (preg_match(RRP_CHOICE_TOPICS[$t], $question)) {
            return $t;
        }
    }
    return null;
}

/**
 * @param array $reply result of rrp_sipo_generate (text, choices, ...)
 * @return array the same reply with only fitting buttons
 */
function rrp_sipo_guard_choices(array $reply, string $ownerBody = ''): array
{
    $choices = array_values(array_filter((array) ($reply['choices'] ?? []), static fn($c) => trim((string) $c) !== ''));
    // 1. Stale: the owner just answered with one of these.
    $said = mb_strtolower(trim($ownerBody));
    if ($said !== '' && in_array($said, array_map(static fn($c) => mb_strtolower(trim((string) $c)), $choices), true)) {
        $choices = [];
    }

    $q = rrp_choice_question((string) ($reply['text'] ?? ''));
    $topic = rrp_choice_question_topic($q);
    $setTopic = $choices ? rrp_choice_set_topic($choices) : null;

    // A reminder offer ("Yes, 09:00" / "Another time" / "No thanks") only fits a question about reminding
    // (live bug: those buttons sat under "send her blood test image?").
    if ($choices && !preg_match('/\bremind/i', (string) ($reply['text'] ?? ''))) {
        $isReminderSet = (bool) array_filter($choices, static fn($c) => preg_match('/^(yes, .*\d|another time|remind me)/i', trim((string) $c)));
        if ($isReminderSet) {
            $choices = [];
        }
    }

    // 3. A set from another topic is wrong here.
    if ($choices && $setTopic !== null && $setTopic !== $topic) {
        $choices = [];
    }
    // 2. Free-text question: only skip-style answers may stay.
    if ($choices && $topic !== null && in_array($topic, RRP_FREE_TEXT_TOPICS, true)) {
        $choices = array_values(array_filter($choices, static fn($c) => preg_match('/^(mixed|mix|not sure|unknown|i don.?t know|skip|no idea|none|yes|no|yep|nope|later|not now|that.?s right|correct)\b/i', trim((string) $c))));
    }
    // Breed gets helpful skip buttons even when the model gave none.
    if ($topic === 'breed' && !$choices && $q !== '' && str_contains($q, '?')) {
        $choices = ['Mixed breed', 'Not sure'];
    }

    // The walk goal question always has useful buttons, even when the model forgot them.
    if ($topic === 'walk' && !$choices && str_contains($q, '?')) {
        $choices = ['15 min', '30 min', '45 min', '1 hour'];
    }

    // 4. A single button is not a choice.
    if (count($choices) < 2) {
        $choices = [];
    }
    $reply['choices'] = $choices ?: null;
    return $reply;
}
