<?php
declare(strict_types=1);

/**
 * SIPO limits (Navid 2026-10-28):
 * - Cool Down: more than ~100K tokens in a rolling 2 hours → pause 2 hours.
 * - Any sense of urgency lifts every limit for that message.
 * - Free plan daily cap comes from config (0 = unlimited while in test mode).
 */

function rrp_sipo_cfg(string $key, mixed $default): mixed
{
    if (!rrp_config_ready()) {
        return $default;
    }
    $cfg = rrp_load_config()['sipo'] ?? [];
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

function rrp_sipo_hour_bucket(int $ts): string
{
    return gmdate('Y-m-d H:00:00', $ts);
}

function rrp_sipo_track_tokens(int $ownerId, int $tokens): void
{
    if ($tokens <= 0) {
        return;
    }
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO sipo_token_window (owner_id, bucket_start, tokens) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE tokens = tokens + VALUES(tokens)'
        );
        $stmt->execute([$ownerId, rrp_sipo_hour_bucket(time()), $tokens]);
    } catch (Throwable $e) {
    }
}

function rrp_sipo_tokens_recent(int $ownerId, int $minutes): int
{
    try {
        // Hour buckets: include the bucket that overlaps the window start.
        $since = rrp_sipo_hour_bucket(time() - $minutes * 60);
        $stmt = rrp_db()->prepare('SELECT COALESCE(SUM(tokens),0) FROM sipo_token_window WHERE owner_id = ? AND bucket_start >= ?');
        $stmt->execute([$ownerId, $since]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Cool Down end time if the owner is cooling down, else null. Starts one when over budget. */
function rrp_sipo_cooldown_until(array $owner): ?DateTimeImmutable
{
    $utc = new DateTimeZone('UTC');
    $now = new DateTimeImmutable('now', $utc);
    $until = !empty($owner['sipo_cooldown_until']) ? new DateTimeImmutable((string) $owner['sipo_cooldown_until'], $utc) : null;
    if ($until && $until > $now) {
        return $until;
    }
    $budget = (int) rrp_sipo_cfg('cooldown_tokens', 100000);
    $minutes = (int) rrp_sipo_cfg('cooldown_minutes', 120);
    if ($budget <= 0 || rrp_sipo_tokens_recent((int) $owner['id'], $minutes) < $budget) {
        return null;
    }
    $until = $now->modify('+' . $minutes . ' minutes');
    try {
        $stmt = rrp_db()->prepare('UPDATE owners SET sipo_cooldown_until = ? WHERE id = ?');
        $stmt->execute([$until->format('Y-m-d H:i:s'), (int) $owner['id']]);
        // Reset the window so the next period starts clean.
        rrp_db()->prepare('DELETE FROM sipo_token_window WHERE owner_id = ?')->execute([(int) $owner['id']]);
    } catch (Throwable $e) {
    }
    return $until;
}

/**
 * Cheap first-pass urgency check (the model can also call flag_emergency).
 * Leans towards "yes": a false alarm costs a few tokens, a miss could cost a pet.
 */
function rrp_sipo_looks_urgent(string $text): bool
{
    $t = mb_strtolower($text);
    $patterns = [
        '/\b(help|urgent|emergency|asap|please hurry|hurry)\b/u',
        '/\bi need help\b/u',
        '/\b(not breathing|can\'?t breathe|struggling to breathe|choking|gasping)\b/u',
        '/\b(bleeding|blood|seizure|seizing|convuls|collapsed?|unconscious|passed out|fainted)\b/u',
        '/\b(poison|poisoned|ate (chocolate|grapes|raisins|xylitol|rat poison|onion|lily|lilies)|antifreeze)\b/u',
        '/\b(hit by a car|run over|attacked|bitten badly|broken (leg|bone))\b/u',
        '/\b(swollen|bloated) (belly|stomach)\b/u',
        '/\b(can\'?t|cannot|unable to) (pee|urinate|stand|walk)\b/u',
        '/\b(heat ?stroke|overheat(ed|ing))\b/u',
        '/\b(vomiting blood|bloody (poop|stool|diarrh))\b/u',
        '/\b(dying|is dying|going to die|lost (my|our) (dog|cat|pet))\b/u',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $t)) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{ok:bool, gate?:string, error?:string, until?:string, emergency?:bool}
 */
function rrp_sipo_gate(array $owner, string $text = ''): array
{
    $emergency = $text !== '' && rrp_sipo_looks_urgent($text);
    if ($emergency || rrp_is_super_owner($owner)) {
        return ['ok' => true, 'emergency' => $emergency];
    }

    $until = rrp_sipo_cooldown_until($owner);
    if ($until) {
        $local = $until->setTimezone(rrp_sipo_owner_tz($owner));
        return [
            'ok' => false,
            'gate' => 'cooldown',
            'until' => $until->format(DATE_ATOM),
            'error' => 'SIPO is taking a short Cool Down and will be back at ' . $local->format('H:i')
                . '. If your pet needs help right now, just tell me — I will answer straight away.',
        ];
    }

    if (rrp_owner_plan($owner) !== 'plus') {
        $cap = (int) rrp_sipo_cfg('free_daily_msgs', 0);
        if ($cap > 0) {
            $usage = rrp_si_usage_row((int) $owner['id'], rrp_si_toronto_day());
            if ((int) ($usage['message_count'] ?? 0) >= $cap) {
                return rrp_plus_required_payload('si_chat');
            }
        }
    }
    return ['ok' => true, 'emergency' => false];
}
