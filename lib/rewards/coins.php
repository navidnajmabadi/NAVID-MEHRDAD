<?php
declare(strict_types=1);

/**
 * Paw coins (brief §F + Navid 2026-10-28). No name — the UI shows a coin with a paw.
 * Earned by caring (routines, logs, verified uploads) and by friendly posting. One reward per
 * real event, daily caps so it can't be farmed, and coins are taken back if content is removed.
 * Rules are not explained up front; SIPO just mentions "+10" when it happens.
 */

/** key => [coins, daily cap (0 = no cap), once per related thing] */
const RRP_COIN_RULES = [
    'routine_vaccine' => [10, 0, true],
    'routine_parasite' => [8, 0, true],
    'routine_checkup' => [10, 0, true],
    'routine_hygiene' => [2, 4, true],
    'treatment_log' => [2, 1, false],
    'meal_log' => [1, 3, false],
    'walk' => [3, 2, false],
    'bathroom_log' => [1, 3, false],
    'lab_report' => [15, 2, true],
    'food_label' => [5, 2, true],
    'profile_field' => [2, 0, true],
    'post' => [2, 3, true],
    'comment' => [1, 5, true],
    'perfect_week' => [25, 0, true],
];

function rrp_coin_rule(string $key): ?array
{
    $over = rrp_config_ready() ? (rrp_load_config()['coins']['rules'][$key] ?? null) : null;
    $base = RRP_COIN_RULES[$key] ?? null;
    if ($base === null) {
        return null;
    }
    if (is_int($over)) {
        $base[0] = $over;
    }
    return $base;
}

function rrp_coin_balance(int $ownerId): int
{
    try {
        $st = rrp_db()->prepare('SELECT sip_balance FROM owners WHERE id = ?');
        $st->execute([$ownerId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Award coins. Returns coins actually given (0 if capped / duplicate / unknown).
 * $relatedType/$relatedId identify the thing (e.g. 'care_reminder', 12) for dedupe and reversal.
 */
function rrp_coin_award(int $ownerId, string $key, string $description, ?string $relatedType = null, ?int $relatedId = null, ?int $petId = null): int
{
    $rule = rrp_coin_rule($key);
    if ($rule === null || $rule[0] <= 0) {
        return 0;
    }
    [$amount, $cap, $once] = $rule;
    $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . $ownerId)->fetch();
    if (!$owner) {
        return 0;
    }
    $day = rrp_sipo_local_now($owner)->format('Y-m-d');
    $pdo = rrp_db();
    try {
        if ($once && $relatedType !== null && $relatedId !== null) {
            $d = $pdo->prepare('SELECT 1 FROM sip_ledger WHERE owner_id = ? AND entry_type = ? AND related_type = ? AND related_id = ? AND amount > 0 LIMIT 1');
            $d->execute([$ownerId, $key, $relatedType, $relatedId]);
            if ($d->fetchColumn()) {
                return 0;
            }
        }
        if ($cap > 0) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM sip_ledger WHERE owner_id = ? AND entry_type = ? AND local_day = ? AND amount > 0');
            $c->execute([$ownerId, $key, $day]);
            if ((int) $c->fetchColumn() >= $cap) {
                return 0;
            }
        }
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE owners SET sip_balance = sip_balance + ? WHERE id = ?')->execute([$amount, $ownerId]);
        $bal = (int) $pdo->query('SELECT sip_balance FROM owners WHERE id = ' . $ownerId)->fetchColumn();
        $pdo->prepare(
            'INSERT INTO sip_ledger (owner_id, pet_id, entry_type, amount, balance_after, description, related_type, related_id, local_day)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$ownerId, $petId, $key, $amount, $bal, mb_substr($description, 0, 200), $relatedType, $relatedId, $day]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('rrp_coin_award: ' . $e->getMessage());
        return 0;
    }
    rrp_coin_note_recent($ownerId, $amount, $description);
    return $amount;
}

/** Take back coins earned for a thing (e.g. a removed post). */
function rrp_coin_revoke(int $ownerId, string $key, string $relatedType, int $relatedId, string $why): int
{
    try {
        $st = rrp_db()->prepare('SELECT COALESCE(SUM(amount),0) FROM sip_ledger WHERE owner_id = ? AND entry_type = ? AND related_type = ? AND related_id = ?');
        $st->execute([$ownerId, $key, $relatedType, $relatedId]);
        $net = (int) $st->fetchColumn();
        if ($net <= 0) {
            return 0;
        }
        $pdo = rrp_db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE owners SET sip_balance = GREATEST(0, sip_balance - ?) WHERE id = ?')->execute([$net, $ownerId]);
        $bal = (int) $pdo->query('SELECT sip_balance FROM owners WHERE id = ' . $ownerId)->fetchColumn();
        $pdo->prepare('INSERT INTO sip_ledger (owner_id, entry_type, amount, balance_after, description, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$ownerId, $key, -$net, $bal, mb_substr($why, 0, 200), $relatedType, $relatedId]);
        $pdo->commit();
        return $net;
    } catch (Throwable $e) {
        if (rrp_db()->inTransaction()) {
            rrp_db()->rollBack();
        }
        return 0;
    }
}

/** Remember the last award in this request/session so SIPO can say "+10" once. */
function rrp_coin_note_recent(int $ownerId, int $amount, string $description): void
{
    $GLOBALS['rrp_coin_recent'][$ownerId][] = ['amount' => $amount, 'description' => $description];
}

/** @return list<array{amount:int, description:string}> */
function rrp_coin_recent(int $ownerId): array
{
    return $GLOBALS['rrp_coin_recent'][$ownerId] ?? [];
}

/** @return list<array> newest first */
function rrp_coin_history(int $ownerId, int $limit = 20): array
{
    try {
        $st = rrp_db()->prepare('SELECT entry_type, amount, balance_after, description, created_at FROM sip_ledger WHERE owner_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $st->execute([$ownerId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Coin with a paw (Navid: no name, just the coin). */
function rrp_coin_svg(int $size = 18): string
{
    return '<svg class="paw-coin" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">'
        . '<circle cx="12" cy="12" r="11" fill="#F5C542" stroke="#C99A1A" stroke-width="1.5"/>'
        . '<circle cx="12" cy="12" r="8" fill="none" stroke="#C99A1A" stroke-width="0.8" opacity=".7"/>'
        . '<ellipse cx="12" cy="14.6" rx="3.2" ry="2.6" fill="#7A5A0A"/>'
        . '<circle cx="8.4" cy="10.8" r="1.25" fill="#7A5A0A"/><circle cx="10.6" cy="8.6" r="1.25" fill="#7A5A0A"/>'
        . '<circle cx="13.4" cy="8.6" r="1.25" fill="#7A5A0A"/><circle cx="15.6" cy="10.8" r="1.25" fill="#7A5A0A"/></svg>';
}

/** Header badge: coin with paw + balance, links to Rewards (always shown, Navid 2026-10-28). */
function rrp_coin_badge(array $owner): void
{
    $n = rrp_coin_balance((int) $owner['id']);
    echo '<a class="coin-badge" href="rewards.php" aria-label="' . $n . ' paw coins, rewards">' . rrp_coin_svg(18)
        . '<span class="data" data-coin-balance>' . $n . '</span></a>';
}
