<?php
declare(strict_types=1);

/**
 * SIPO keeps an eye on the things that change slowly, and speaks up once when one needs attention:
 * weight drifting, calories short at the end of the day, a toothpaste tube running low.
 * Food running out and meal check-ins live in lib/care/food.php. Each message is written in the chat
 * (and the bell) and pushed to the phone only if the owner is not already in the chat.
 */

const RRP_WATCH_PASTE_DAYS = 55; // a pet-toothpaste tube lasts about two months of daily brushing

const RRP_SUPPLY_WEEK = 'This week';
const RRP_SUPPLY_MONTH = 'This month';
const RRP_SUPPLY_OLD = 'Longer ago';
const RRP_SUPPLY_NONE = 'No toothpaste yet';

/** Seconds since this watch last spoke for the pet (huge if never). */
function rrp_watch_age(int $petId, string $key): int
{
    $s = rrp_learn_state($petId, 'watch_' . $key);
    return $s['at'] > 0 ? time() - $s['at'] : PHP_INT_MAX;
}

/** Say something in the pet's chat, then push if the owner is away from it. */
function rrp_watch_say(array $owner, array $pet, string $key, string $text, string $mascot = 'concerned', ?array $choices = null): bool
{
    $thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $pet['id']);
    if (!$thread) {
        return false;
    }
    $meta = ['mascot' => $mascot, 'proactive' => 'watch'] + ($choices ? ['choices' => $choices] : []);
    $msg = rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, $meta);
    rrp_learn_set((int) $owner['id'], (int) $pet['id'], 'watch_' . $key, 'sent');
    rrp_sipo_push((int) $owner['id'], 'SIPO · ' . $pet['name'], mb_substr($text, 0, 140), rrp_app_url('si-chat.php?pet=' . (int) $pet['id']));
    rrp_sipo_log_nudge($owner, (int) $pet['id'], (int) $thread['id'], (int) ($msg['id'] ?? 0), 'watch', true);
    return true;
}

/** What is worth saying about this pet now, or null. @return array{key:string,text:string,mascot:string,choices?:list<string>}|null */
function rrp_watch_check(array $owner, array $pet): ?array
{
    $pid = (int) $pet['id'];
    $name = (string) $pet['name'];
    $local = rrp_sipo_local_now($owner);

    // Weight: more than 5% over the last 60 days (needs two weigh-ins at least a week apart).
    $series = array_values(array_filter(rrp_weight_series($pid, 12), static fn($p) => $p['date'] >= $local->modify('-60 days')->format('Y-m-d')));
    if (count($series) >= 2 && rrp_watch_age($pid, 'weight') > 14 * 86400) {
        $first = $series[0];
        $last = $series[count($series) - 1];
        $days = (int) ((strtotime($last['date']) - strtotime($first['date'])) / 86400);
        $chg = $first['kg'] > 0 ? ($last['kg'] - $first['kg']) / $first['kg'] : 0;
        if ($days >= 7 && abs($chg) > 0.05) {
            $from = rrp_fmt_weight((float) $first['kg'], $owner);
            $to = rrp_fmt_weight((float) $last['kg'], $owner);
            $pct = (int) round(abs($chg) * 100);
            $text = $chg > 0
                ? "{$name}'s weight went from {$from} to {$to} in about " . max(1, (int) round($days / 7)) . " weeks (+{$pct}%). A little less food or an extra walk can help. Is this planned? If not, mention it to the vet."
                : "{$name}'s weight went from {$from} to {$to} in about " . max(1, (int) round($days / 7)) . " weeks (−{$pct}%). If this is not planned, please let your vet know. Is {$name} eating and drinking as usual?";
            return ['key' => 'weight', 'text' => $text, 'mascot' => 'concerned'];
        }
    }

    // Calories: in the evening, well under the day's need, for owners who log meals.
    $hour = (int) $local->format('G');
    if ($hour >= 20 && $hour < 22 && rrp_watch_age($pid, 'calories') > 20 * 3600) {
        $f = rrp_food_today($owner, $pet);
        $logged = rrp_db()->prepare('SELECT COUNT(DISTINCT fed_on) FROM feeding_logs WHERE pet_id = ? AND fed_on >= ? AND fed_on < ? AND logged_at IS NOT NULL');
        $logged->execute([$pid, $local->modify('-7 days')->format('Y-m-d'), $local->format('Y-m-d')]);
        if ($f['percent'] !== null && $f['meals'] > 0 && $f['percent'] < 60 && (int) $logged->fetchColumn() >= 3) {
            return ['key' => 'calories', 'mascot' => 'concerned',
                'text' => "{$name} has had about {$f['percent']}% of today's calories ({$f['eaten_kcal']} of {$f['target_kcal']} kcal). If that is usual for today, no worries. If {$name} is eating less than normal, offer a small extra meal, and call the vet if it lasts more than a day."];
        }
    }

    // Toothpaste: a tube opened about 55 days ago is probably running low.
    $paste = rrp_learn_state($pid, 'supply_toothpaste');
    if ($paste['stage'] === 'opened' && time() - $paste['at'] > RRP_WATCH_PASTE_DAYS * 86400 && rrp_watch_age($pid, 'toothpaste') > 60 * 86400) {
        return ['key' => 'toothpaste', 'mascot' => 'happy',
            'text' => "{$name}'s toothpaste tube has been open for about " . (int) round((time() - $paste['at']) / 86400 / 7) . " weeks, so it may run low soon. Time to pick up a new one? Only pet toothpaste, never human."];
    }
    return null;
}

/**
 * Toothpaste question: once the pet has been brushed 5+ times in a month and SIPO does not know about
 * the tube yet. Asked as a pending question, like the learning ones.
 * @return array{text:string, choices:list<string>}|null
 */
function rrp_watch_supply_question(array $owner, array $pet): ?array
{
    $pid = (int) $pet['id'];
    if (rrp_learn_state($pid, 'supply_toothpaste')['stage'] !== '' || rrp_learn_state($pid, 'ask_toothpaste')['stage'] !== '' || count(rrp_daily_logs($owner, $pet, 'teeth', 30)) < 5) {
        return null;
    }
    return ['text' => "{$pet['name']}'s teeth brushing is going well 🪥 When did you open the toothpaste tube you are using? I will tell you when it is time for a new one.",
        'choices' => [RRP_SUPPLY_WEEK, RRP_SUPPLY_MONTH, RRP_SUPPLY_OLD, RRP_SUPPLY_NONE]];
}

function rrp_watch_tick(int $max = 40): array
{
    $out = ['checked' => 0, 'said' => 0];
    $owners = rrp_db()->query(
        "SELECT DISTINCT o.* FROM owners o JOIN chat_threads t ON t.owner_id = o.id AND t.thread_kind = 'si' AND t.pet_id IS NOT NULL
         WHERE o.status = 'active' AND o.onboarded_at IS NOT NULL ORDER BY o.id"
    )->fetchAll();
    foreach ($owners as $owner) {
        if ($out['said'] >= $max) {
            break;
        }
        $hour = (int) rrp_sipo_local_now($owner)->format('G');
        if (rrp_sipo_is_quiet_hour($hour)) {
            continue; // never at night
        }
        foreach (rrp_pets_by_owner((int) $owner['id']) as $pet) {
            if (!in_array($pet['type'] ?? '', ['dog', 'cat'], true)) {
                continue;
            }
            $out['checked']++;
            $w = rrp_watch_check($owner, $pet);
            if ($w && rrp_watch_say($owner, $pet, $w['key'], $w['text'], $w['mascot'])) {
                $out['said']++;
                break; // one message per owner per run
            }
        }
    }
    return $out;
}
