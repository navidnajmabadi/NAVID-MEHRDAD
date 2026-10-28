<?php
declare(strict_types=1);

/**
 * Pet spending (pet_expenses, migration 006): log from SIPO or the Finance page,
 * monthly totals and by-category breakdown for the dashboard.
 */

const RRP_EXPENSE_CATEGORIES = ['food' => 'Food', 'vet' => 'Vet', 'meds' => 'Medicine', 'groom' => 'Grooming', 'supplies' => 'Supplies', 'other' => 'Other'];

/** @return array{ok:bool, error?:string, id?:int} */
function rrp_expense_add(array $owner, array $pet, string $category, float $amount, ?string $currency = null, ?string $date = null, ?string $note = null): array
{
    if (!isset(RRP_EXPENSE_CATEGORIES[$category])) {
        $category = 'other';
    }
    if ($amount <= 0 || $amount > 1000000) {
        return ['ok' => false, 'error' => 'Amount must be more than 0.'];
    }
    $currency = strtoupper(trim((string) ($currency ?: rrp_expense_currency($owner))));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = 'USD';
    }
    $today = rrp_sipo_local_now($owner)->format('Y-m-d');
    $date = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= $today ? $date : $today;
    rrp_db()->prepare(
        'INSERT INTO pet_expenses (pet_id, owner_id, category, amount, currency, spent_on, note) VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([(int) $pet['id'], (int) $owner['id'], $category, round($amount, 2), $currency, $date, $note !== null ? mb_substr($note, 0, 255) : null]);
    return ['ok' => true, 'id' => (int) rrp_db()->lastInsertId(), 'currency' => $currency, 'date' => $date];
}

function rrp_expense_delete(int $ownerId, int $id): bool
{
    $st = rrp_db()->prepare('DELETE FROM pet_expenses WHERE id = ? AND owner_id = ?');
    $st->execute([$id, $ownerId]);
    return $st->rowCount() > 0;
}

/** Owner's usual currency: their last entry, else USD. */
function rrp_expense_currency(array $owner): string
{
    try {
        $st = rrp_db()->prepare('SELECT currency FROM pet_expenses WHERE owner_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([(int) $owner['id']]);
        return (string) ($st->fetchColumn() ?: 'USD');
    } catch (Throwable $e) {
        return 'USD';
    }
}

/**
 * @return array{currency:string, this_month:float, last_month:float, year:float,
 *   months:list<array{month:string,label:string,total:float}>, by_category:array<string,float>, recent:list<array>}
 */
function rrp_expense_summary(array $owner, ?array $pet = null, int $months = 6): array
{
    $cur = rrp_expense_currency($owner);
    $now = rrp_sipo_local_now($owner);
    $from = $now->modify('first day of this month')->modify('-' . ($months - 1) . ' months')->format('Y-m-d');
    $where = 'owner_id = ? AND currency = ?' . ($pet ? ' AND pet_id = ?' : '');
    $args = $pet ? [(int) $owner['id'], $cur, (int) $pet['id']] : [(int) $owner['id'], $cur];

    $byMonth = [];
    $st = rrp_db()->prepare("SELECT DATE_FORMAT(spent_on, '%Y-%m') AS m, SUM(amount) AS t FROM pet_expenses WHERE {$where} AND spent_on >= ? GROUP BY m");
    $st->execute([...$args, $from]);
    foreach ($st->fetchAll() as $r) {
        $byMonth[(string) $r['m']] = (float) $r['t'];
    }
    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $d = $now->modify('first day of this month')->modify("-{$i} months");
        $series[] = ['month' => $d->format('Y-m'), 'label' => $d->format('M'), 'total' => $byMonth[$d->format('Y-m')] ?? 0.0];
    }

    $cat = [];
    $st = rrp_db()->prepare("SELECT category, SUM(amount) AS t FROM pet_expenses WHERE {$where} AND spent_on >= ? GROUP BY category ORDER BY t DESC");
    $st->execute([...$args, $from]);
    foreach ($st->fetchAll() as $r) {
        $cat[(string) $r['category']] = (float) $r['t'];
    }

    $st = rrp_db()->prepare("SELECT SUM(amount) FROM pet_expenses WHERE {$where} AND spent_on >= ?");
    $st->execute([...$args, $now->format('Y-01-01')]);
    $year = (float) $st->fetchColumn();

    $st = rrp_db()->prepare(
        'SELECT e.id, e.category, e.amount, e.currency, e.spent_on, e.note, p.name AS pet_name FROM pet_expenses e JOIN pets p ON p.id = e.pet_id
         WHERE e.owner_id = ?' . ($pet ? ' AND e.pet_id = ?' : '') . ' ORDER BY e.spent_on DESC, e.id DESC LIMIT 20'
    );
    $st->execute($pet ? [(int) $owner['id'], (int) $pet['id']] : [(int) $owner['id']]);

    return [
        'currency' => $cur,
        'this_month' => $series[$months - 1]['total'],
        'last_month' => $months > 1 ? $series[$months - 2]['total'] : 0.0,
        'year' => $year,
        'months' => $series,
        'by_category' => $cat,
        'recent' => $st->fetchAll(),
    ];
}
