<?php
declare(strict_types=1);

/**
 * Proactive SIPO (brief §H): SIPO starts the conversation 3–4× a day,
 * at the hours this owner usually opens the app, never 21:00–09:00 local.
 */

const RRP_SIPO_NUDGES_PER_DAY = 4;
const RRP_SIPO_NUDGE_GAP_HOURS = 3;
const RRP_SIPO_DEFAULT_HOURS = [9, 13, 18];

/**
 * Up to 3 preferred local hours (busiest first) inside waking hours.
 * @return list<int>
 */
function rrp_sipo_preferred_hours(int $ownerId): array
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT local_hour FROM owner_activity_hours WHERE owner_id = ? AND local_hour BETWEEN ? AND ? AND hits >= 2
             ORDER BY hits DESC, local_hour ASC LIMIT 3'
        );
        $stmt->execute([$ownerId, RRP_SIPO_QUIET_END, RRP_SIPO_QUIET_START - 1]);
        $hours = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $hours = [];
    }
    // Fill gaps with defaults that are ≥ GAP hours from what we have.
    foreach (RRP_SIPO_DEFAULT_HOURS as $h) {
        if (count($hours) >= 3) {
            break;
        }
        $far = true;
        foreach ($hours as $x) {
            if (abs($x - $h) < RRP_SIPO_NUDGE_GAP_HOURS) {
                $far = false;
            }
        }
        if ($far) {
            $hours[] = $h;
        }
    }
    sort($hours);
    return $hours;
}

/**
 * Should SIPO reach out to this owner right now? Returns the reason it may not.
 * @return array{ok:bool, reason?:string}
 */
function rrp_sipo_nudge_allowed(array $owner, DateTimeImmutable $localNow): array
{
    $hour = (int) $localNow->format('G');
    if (rrp_sipo_is_quiet_hour($hour)) {
        return ['ok' => false, 'reason' => 'quiet_hours'];
    }
    if (!in_array($hour, rrp_sipo_preferred_hours((int) $owner['id']), true)) {
        return ['ok' => false, 'reason' => 'not_preferred_hour'];
    }
    if (rrp_sipo_cooldown_until($owner) !== null) {
        return ['ok' => false, 'reason' => 'cooldown'];
    }
    $stmt = rrp_db()->prepare('SELECT COUNT(*) AS n, MAX(created_at) AS last_at FROM sipo_nudges WHERE owner_id = ? AND local_day = ?');
    $stmt->execute([(int) $owner['id'], $localNow->format('Y-m-d')]);
    $row = $stmt->fetch() ?: ['n' => 0, 'last_at' => null];
    if ((int) $row['n'] >= RRP_SIPO_NUDGES_PER_DAY) {
        return ['ok' => false, 'reason' => 'daily_cap'];
    }
    // Anything in any SIPO thread in the last GAP hours (owner or SIPO) → wait.
    $recent = rrp_db()->prepare(
        "SELECT MAX(t.last_message_at) FROM chat_threads t WHERE t.owner_id = ? AND t.thread_kind = 'si'"
    );
    $recent->execute([(int) $owner['id']]);
    $lastMsg = $recent->fetchColumn();
    $gapStart = gmdate('Y-m-d H:i:s', time() - RRP_SIPO_NUDGE_GAP_HOURS * 3600);
    if (($lastMsg && $lastMsg > $gapStart) || ($row['last_at'] && $row['last_at'] > $gapStart)) {
        return ['ok' => false, 'reason' => 'too_soon'];
    }
    return ['ok' => true];
}

/** Which pet thread to talk about, and why. @return array{thread:array, kind:string}|null */
function rrp_sipo_pick_nudge(int $ownerId): ?array
{
    $stmt = rrp_db()->prepare(
        "SELECT t.* FROM chat_threads t JOIN pets p ON p.id = t.pet_id AND p.is_active = 1
         WHERE t.owner_id = ? AND t.thread_kind = 'si' AND t.pet_id IS NOT NULL
         ORDER BY t.last_message_at IS NULL DESC, t.last_message_at ASC LIMIT 1"
    );
    $stmt->execute([$ownerId]);
    $thread = $stmt->fetch();
    if (!$thread) {
        return null;
    }
    $petId = (int) $thread['pet_id'];
    $due = rrp_db()->prepare(
        "SELECT COUNT(*) FROM care_reminders WHERE pet_id = ? AND status IN ('scheduled','sent','snoozed')
         AND due_at <= (UTC_TIMESTAMP() + INTERVAL 1 DAY)"
    );
    $due->execute([$petId]);
    if ((int) $due->fetchColumn() > 0) {
        return ['thread' => $thread, 'kind' => 'reminder'];
    }
    $pet = rrp_pet_by_id($petId, $ownerId);
    $gaps = $pet && (empty($pet['weight_kg']) || empty($pet['birth_date']) && empty($pet['age_years']) || empty($pet['image_path']));
    if ($gaps) {
        $asked = rrp_db()->prepare(
            "SELECT COUNT(*) FROM sipo_nudges WHERE pet_id = ? AND kind = 'profile_gap' AND created_at > (UTC_TIMESTAMP() - INTERVAL 2 DAY)"
        );
        $asked->execute([$petId]);
        if ((int) $asked->fetchColumn() === 0) {
            return ['thread' => $thread, 'kind' => 'profile_gap'];
        }
    }
    return ['thread' => $thread, 'kind' => 'checkin'];
}

/**
 * Cron worker (cron/sipo-nudges.php, every 15 min).
 * @return array{checked:int, sent:int, skipped:array<string,int>, summarized:int}
 */
function rrp_sipo_nudge_tick(int $maxSend = 40): array
{
    $stats = ['checked' => 0, 'sent' => 0, 'skipped' => [], 'summarized' => 0];
    $owners = rrp_db()->query(
        "SELECT DISTINCT o.* FROM owners o JOIN chat_threads t ON t.owner_id = o.id AND t.thread_kind = 'si' AND t.pet_id IS NOT NULL
         WHERE o.status = 'active' ORDER BY o.id"
    )->fetchAll();

    foreach ($owners as $owner) {
        if ($stats['sent'] >= $maxSend) {
            break;
        }
        $stats['checked']++;
        $allowed = rrp_sipo_nudge_allowed($owner, rrp_sipo_local_now($owner));
        if (!$allowed['ok']) {
            $stats['skipped'][$allowed['reason']] = ($stats['skipped'][$allowed['reason']] ?? 0) + 1;
            continue;
        }
        $pick = rrp_sipo_pick_nudge((int) $owner['id']);
        if (!$pick) {
            continue;
        }
        $msg = rrp_sipo_proactive($owner, $pick['thread'], $pick['kind']);
        if (!$msg) {
            $stats['skipped']['no_message'] = ($stats['skipped']['no_message'] ?? 0) + 1;
            continue;
        }
        $pet = rrp_pet_by_id((int) $pick['thread']['pet_id'], (int) $owner['id']);
        $title = 'SIPO · ' . ($pet['name'] ?? 'your pet');
        rrp_try_web_push((int) $owner['id'], $title, mb_substr($msg['body'], 0, 140), rrp_app_url('si-chat.php?pet=' . (int) $pick['thread']['pet_id']));
        rrp_sipo_log_nudge($owner, (int) $pick['thread']['pet_id'], (int) $pick['thread']['id'], (int) $msg['id'], $pick['kind'], true);
        $stats['sent']++;
    }

    // Keep long chats cheap: fold old messages of recently active threads.
    $busy = rrp_db()->query(
        "SELECT * FROM chat_threads WHERE thread_kind = 'si' AND last_message_at > (UTC_TIMESTAMP() - INTERVAL 1 DAY) ORDER BY last_message_at DESC LIMIT 15"
    )->fetchAll();
    foreach ($busy as $t) {
        if (rrp_sipo_maybe_summarize($t)) {
            $stats['summarized']++;
        }
    }
    return $stats;
}
