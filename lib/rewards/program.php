<?php
declare(strict_types=1);

/**
 * Perfect weeks and referrals (brief §F + Navid 2026-10-28).
 *
 * Perfect week (Mon–Sun, owner's local time): every care reminder that came due that week was
 * done, and SIPAW was used on at least 4 days (any log, walk, meal, routine or treatment check-in).
 * Each perfect week = 5% off the next Plus payment, up to 20% (4 weeks); used weeks are marked spent.
 *
 * Referrals: share a code/link; every 3 friends who PAY (Square payment completed) = 1 free month,
 * stacking for life (30 paying friends = 10 free months).
 */

const RRP_WEEK_MIN_ACTIVE_DAYS = 4;
const RRP_WEEK_DISCOUNT_PCT = 5;
const RRP_WEEK_DISCOUNT_MAX = 20;
const RRP_REFERRALS_PER_MONTH = 3;

/** Monday of the owner's current week (local). */
function rrp_week_start(array $owner, ?DateTimeImmutable $at = null): DateTimeImmutable
{
    $d = ($at ?? rrp_sipo_local_now($owner))->setTime(0, 0);
    return $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
}

/**
 * Progress for a week. due/done = reminders due in the week (all active pets).
 * @return array{week_start:string, active_days:int, need_days:int, due:int, done:int, perfect:bool}
 */
function rrp_week_progress(array $owner, DateTimeImmutable $weekStart): array
{
    $ownerId = (int) $owner['id'];
    $tz = rrp_sipo_owner_tz($owner);
    $from = $weekStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $toLocal = $weekStart->modify('+7 days');
    $to = $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $days = [];
    $add = static function (array $rows) use (&$days) {
        foreach ($rows as $r) {
            $days[(string) $r['d']] = true;
        }
    };
    $q = static function (string $sql, array $args) {
        try {
            $st = rrp_db()->prepare($sql);
            $st->execute($args);
            return $st->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    };
    $ws = $weekStart->format('Y-m-d');
    $we = $toLocal->format('Y-m-d');
    // Coin ledger already stores the owner's local day for every caring action.
    $add($q("SELECT DISTINCT local_day AS d FROM sip_ledger WHERE owner_id = ? AND amount > 0 AND local_day >= ? AND local_day < ?
             AND entry_type NOT IN ('perfect_week','post','comment')", [$ownerId, $ws, $we]));
    $add($q("SELECT DISTINCT l.local_day AS d FROM pet_walks l JOIN pets p ON p.id = l.pet_id WHERE p.owner_id = ? AND l.local_day >= ? AND l.local_day < ?", [$ownerId, $ws, $we]));
    $add($q("SELECT DISTINCT l.local_day AS d FROM pet_bathroom_logs l JOIN pets p ON p.id = l.pet_id WHERE p.owner_id = ? AND l.local_day >= ? AND l.local_day < ?", [$ownerId, $ws, $we]));
    $add($q("SELECT DISTINCT l.fed_on AS d FROM feeding_logs l JOIN pets p ON p.id = l.pet_id WHERE p.owner_id = ? AND l.fed_on >= ? AND l.fed_on < ?", [$ownerId, $ws, $we]));

    $due = $q("SELECT r.status, r.outcome FROM care_reminders r JOIN pets p ON p.id = r.pet_id AND p.is_active = 1
               WHERE p.owner_id = ? AND r.due_at >= ? AND r.due_at < ? AND r.status <> 'dismissed'", [$ownerId, $from, $to]);
    $doneN = count(array_filter($due, static fn($r) => $r['status'] === 'completed' || $r['outcome'] === 'done'));
    $active = count($days);
    return [
        'week_start' => $ws,
        'active_days' => $active,
        'need_days' => RRP_WEEK_MIN_ACTIVE_DAYS,
        'due' => count($due),
        'done' => $doneN,
        'perfect' => $active >= RRP_WEEK_MIN_ACTIVE_DAYS && $doneN >= count($due),
    ];
}

/** Evaluate last week once (called from cron on/after Monday local). */
function rrp_week_evaluate(array $owner): ?array
{
    $last = rrp_week_start($owner)->modify('-7 days');
    $st = rrp_db()->prepare('SELECT 1 FROM reward_weeks WHERE owner_id = ? AND week_start = ?');
    $st->execute([(int) $owner['id'], $last->format('Y-m-d')]);
    if ($st->fetchColumn()) {
        return null;
    }
    $p = rrp_week_progress($owner, $last);
    rrp_db()->prepare('INSERT IGNORE INTO reward_weeks (owner_id, week_start, perfect, active_days, due_items, done_items) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([(int) $owner['id'], $p['week_start'], $p['perfect'] ? 1 : 0, $p['active_days'], $p['due'], $p['done']]);
    if ($p['perfect']) {
        $coins = rrp_coin_award((int) $owner['id'], 'perfect_week', 'Perfect care week', 'week', (int) str_replace('-', '', $p['week_start']));
        $pct = rrp_week_discount_pct($owner);
        $text = "🌟 Perfect care week! Every reminder done and you checked in on {$p['active_days']} days. "
            . "That's {$pct}% off your next Plus month so far" . ($coins ? " and +{$coins} coins" : '') . '.';
        foreach (rrp_pets_by_owner((int) $owner['id']) as $pet) {
            $thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $pet['id']);
            if ($thread) {
                rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'happy', 'proactive' => 'perfect_week']);
            }
            break; // one message, in the first pet's chat
        }
    }
    return $p;
}

/** Unspent perfect weeks from the last ~2 months (oldest first), at most 4. @return list<string> week_start */
function rrp_week_unspent(array $owner): array
{
    try {
        $st = rrp_db()->prepare(
            'SELECT week_start FROM reward_weeks WHERE owner_id = ? AND perfect = 1 AND spent_order_id IS NULL
             AND week_start >= (CURDATE() - INTERVAL 63 DAY) ORDER BY week_start LIMIT ' . intdiv(RRP_WEEK_DISCOUNT_MAX, RRP_WEEK_DISCOUNT_PCT)
        );
        $st->execute([(int) $owner['id']]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/** Discount for the next Plus payment: 5% per unspent perfect week, max 20%. */
function rrp_week_discount_pct(array $owner): int
{
    return min(RRP_WEEK_DISCOUNT_MAX, RRP_WEEK_DISCOUNT_PCT * count(rrp_week_unspent($owner)));
}

function rrp_week_spend(int $ownerId, array $weeks, int $orderId): void
{
    if (!$weeks) {
        return;
    }
    $st = rrp_db()->prepare('UPDATE reward_weeks SET spent_order_id = ? WHERE owner_id = ? AND week_start = ? AND spent_order_id IS NULL');
    foreach ($weeks as $w) {
        $st->execute([$orderId, $ownerId, $w]);
    }
}

/** Cron worker: evaluate last week for owners whose local week has rolled over. */
function rrp_rewards_tick(int $limit = 200): array
{
    $stats = ['evaluated' => 0, 'perfect' => 0];
    $owners = rrp_db()->query("SELECT * FROM owners WHERE status = 'active' ORDER BY id LIMIT " . (int) $limit)->fetchAll();
    foreach ($owners as $o) {
        $r = rrp_week_evaluate($o);
        if ($r !== null) {
            $stats['evaluated']++;
            $stats['perfect'] += $r['perfect'] ? 1 : 0;
        }
    }
    return $stats;
}

// -----------------------------------------------------------------------------
// Referrals
// -----------------------------------------------------------------------------

function rrp_referral_code(array $owner): string
{
    if (!empty($owner['referral_code'])) {
        return (string) $owner['referral_code'];
    }
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
    for ($try = 0; $try < 8; $try++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        try {
            $st = rrp_db()->prepare('UPDATE owners SET referral_code = ? WHERE id = ? AND referral_code IS NULL');
            $st->execute([$code, (int) $owner['id']]);
            if ($st->rowCount() === 1) {
                return $code;
            }
            $cur = rrp_db()->query('SELECT referral_code FROM owners WHERE id = ' . (int) $owner['id'])->fetchColumn();
            if ($cur) {
                return (string) $cur;
            }
        } catch (Throwable $e) {
            // duplicate code → try again
        }
    }
    return '';
}

function rrp_referral_link(array $owner): string
{
    return rrp_app_url('signup.php?ref=' . rawurlencode(rrp_referral_code($owner)));
}

/** Signup: link the new owner to whoever invited them (code from ?ref= cookie). */
function rrp_referral_attach(int $newOwnerId, ?string $code): void
{
    $code = strtoupper(trim((string) $code));
    if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
        return;
    }
    try {
        $st = rrp_db()->prepare('SELECT id FROM owners WHERE referral_code = ? LIMIT 1');
        $st->execute([$code]);
        $ref = (int) $st->fetchColumn();
        if ($ref && $ref !== $newOwnerId) {
            rrp_db()->prepare("INSERT IGNORE INTO referrals (referrer_owner_id, referred_owner_id, status) VALUES (?, ?, 'signed_up')")->execute([$ref, $newOwnerId]);
        }
    } catch (Throwable $e) {
    }
}

/**
 * A referred owner paid for the first time → count it; every 3 paid → +1 free month (stacks).
 * @return int free months granted now
 */
function rrp_referral_on_paid(int $payerOwnerId): int
{
    $st = rrp_db()->prepare("SELECT * FROM referrals WHERE referred_owner_id = ? AND status = 'signed_up' LIMIT 1");
    $st->execute([$payerOwnerId]);
    $r = $st->fetch();
    if (!$r) {
        return 0;
    }
    rrp_db()->prepare("UPDATE referrals SET status = 'paid', paid_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(int) $r['id']]);
    $referrer = (int) $r['referrer_owner_id'];
    $granted = 0;
    while (true) {
        $ids = rrp_db()->prepare("SELECT id FROM referrals WHERE referrer_owner_id = ? AND status = 'paid' ORDER BY paid_at, id LIMIT " . RRP_REFERRALS_PER_MONTH);
        $ids->execute([$referrer]);
        $batch = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
        if (count($batch) < RRP_REFERRALS_PER_MONTH) {
            break;
        }
        $in = implode(',', $batch);
        rrp_db()->exec("UPDATE referrals SET status = 'rewarded', rewarded_free_month = 1, rewarded_at = UTC_TIMESTAMP() WHERE id IN ({$in})");
        rrp_db()->prepare('UPDATE owners SET referral_free_months_owed = referral_free_months_owed + 1 WHERE id = ?')->execute([$referrer]);
        $granted++;
    }
    $text = $granted
        ? "🎉 A friend you invited just joined Plus. That's 3 paying friends, so you've earned a FREE month! Use it on the Plus page whenever you like."
        : "🎉 A friend you invited just joined Plus. " . rrp_referral_progress($referrer)['to_next'] . ' more and you get a free month.';
    $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . $referrer)->fetch();
    foreach ($owner ? rrp_pets_by_owner($referrer) : [] as $pet) {
        $thread = rrp_sipo_thread_for_pet($referrer, (int) $pet['id']);
        if ($thread) {
            rrp_sipo_insert_message((int) $thread['id'], 'assistant', $text, 0, ['mascot' => 'happy', 'proactive' => 'referral']);
        }
        break;
    }
    rrp_try_web_push($referrer, 'SIPAW', $text, rrp_app_url('rewards.php'));
    return $granted;
}

/** @return array{signed_up:int, paid_waiting:int, rewarded:int, to_next:int, free_months:int} */
function rrp_referral_progress(int $ownerId): array
{
    $st = rrp_db()->prepare("SELECT status, COUNT(*) AS n FROM referrals WHERE referrer_owner_id = ? GROUP BY status");
    $st->execute([$ownerId]);
    $by = ['signed_up' => 0, 'paid' => 0, 'rewarded' => 0];
    foreach ($st->fetchAll() as $r) {
        $by[$r['status']] = (int) $r['n'];
    }
    $owed = (int) rrp_db()->query('SELECT referral_free_months_owed FROM owners WHERE id = ' . $ownerId)->fetchColumn();
    return [
        'signed_up' => $by['signed_up'],
        'paid_waiting' => $by['paid'],
        'rewarded' => $by['rewarded'],
        'to_next' => RRP_REFERRALS_PER_MONTH - $by['paid'],
        'free_months' => $owed,
    ];
}
