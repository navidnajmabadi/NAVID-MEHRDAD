<?php
declare(strict_types=1);

/**
 * Health dashboard ViewModel — rendered for the owner (care.php) and, read-only,
 * for a vet through a share link (vet.php). Same data, same view.
 */

function rrp_vm_health(array $owner, array $pet): array
{
    $petId = (int) $pet['id'];
    $routines = rrp_care_status($owner, $pet);
    $treatments = rrp_treatments_for_pet($petId);
    $labs = rrp_labs_trends($petId);
    $foodNeed = rrp_food_daily_kcal($pet);
    $foodToday = rrp_food_today($owner, $pet);
    $foods = rrp_foods_for_pet($petId);

    $alerts = [];
    foreach ($labs as $a) {
        if (in_array($a['latest']['flag'], ['low', 'high'], true)) {
            $alerts[] = $a['label'] . ' is ' . $a['latest']['flag'];
        }
    }
    foreach ($routines as $r) {
        if ($r['state'] === 'overdue') {
            $alerts[] = $r['title'] . ' is overdue';
        }
    }
    foreach ($treatments as $t) {
        if ($t['status'] === 'active' && rrp_treatment_worse_streak((int) $t['id']) >= 2) {
            $alerts[] = $t['condition_name'] . ': worse 2+ days';
        }
    }
    foreach ($foods as $f) {
        if ($f['days_left'] !== null && $f['days_left'] <= RRP_FOOD_SUPPLY_WARN_DAYS) {
            $alerts[] = $f['name'] . ($f['days_left'] <= 0 ? ' has run out' : ' runs out in ' . $f['days_left'] . ' days');
        }
    }
    $active = array_values(array_filter($treatments, static fn($t) => $t['status'] === 'active'));
    $state = $alerts ? 'attention' : ($active ? 'treatment' : 'good');

    return [
        'pet' => rrp_sipo_pet_brief($pet) + [
            'breed' => $pet['breed'] ?? null,
            'sex' => ($pet['sex'] ?? 'unknown') !== 'unknown' ? $pet['sex'] : null,
            'age' => rrp_vm_age_label($pet),
            'weight_kg' => $pet['weight_kg'] !== null ? (float) $pet['weight_kg'] : null,
            'conditions' => $pet['medical_conditions'] ?? null,
        ],
        'status' => [
            'state' => $state,
            'headline' => match ($state) {
                'good' => $pet['name'] . ' is doing well',
                'treatment' => $pet['name'] . ' is under vet care',
                default => $pet['name'] . ' needs a look',
            },
            'alerts' => array_slice($alerts, 0, 6),
        ],
        'routines' => $routines,
        'treatments' => $treatments,
        'labs' => $labs,
        'weights' => rrp_vm_weight_points($petId, $pet),
        'food' => [
            'need' => $foodNeed,
            'today' => $foodToday,
            'foods' => $foods,
            'preferences' => rrp_food_preferences($petId),
        ],
        'region_label' => ['na' => 'USA / Canada guidelines (AAHA, CAPC)', 'eu' => 'Europe / UK guidelines (WSAVA, ESCCAP)', 'global' => 'WSAVA global guidelines'][rrp_care_region($owner, $pet)] ?? '',
    ];
}

function rrp_vm_age_label(array $pet): ?string
{
    $m = rrp_food_age_months($pet);
    if ($m === null) {
        return null;
    }
    return $m < 24 ? $m . ' months' : intdiv($m, 12) . ' years';
}

/** Weight history from dated weight facts (written by update_pet_profile). */
function rrp_vm_weight_points(int $petId, array $pet): array
{
    $pts = [];
    try {
        $stmt = rrp_db()->prepare("SELECT fact_key, fact_value FROM sipo_memory WHERE pet_id = ? AND fact_key LIKE 'weight\\_%' ORDER BY fact_key");
        $stmt->execute([$petId]);
        foreach ($stmt->fetchAll() as $r) {
            if (preg_match('/^weight_(\d{4})_(\d{2})_(\d{2})$/', (string) $r['fact_key'], $m) && is_numeric($kg = (float) $r['fact_value'])) {
                $pts[] = ['date' => "{$m[1]}-{$m[2]}-{$m[3]}", 'value' => (float) $r['fact_value']];
            }
        }
    } catch (Throwable $e) {
    }
    if (!$pts && !empty($pet['weight_kg'])) {
        $pts[] = ['date' => substr((string) ($pet['updated_at'] ?? gmdate('Y-m-d')), 0, 10), 'value' => (float) $pet['weight_kg']];
    }
    return $pts;
}

// -----------------------------------------------------------------------------
// Vet share links
// -----------------------------------------------------------------------------

const RRP_SHARE_DAYS = 30;

/** @return array{ok:bool, url?:string, expires_at?:string, error?:string} token shown once */
function rrp_share_create(array $owner, array $pet): array
{
    $token = bin2hex(random_bytes(24));
    $expires = gmdate('Y-m-d H:i:s', time() + RRP_SHARE_DAYS * 86400);
    try {
        rrp_db()->prepare('INSERT INTO health_share_links (pet_id, owner_id, token_hash, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([(int) $pet['id'], (int) $owner['id'], hash('sha256', $token), $expires]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Run sql/010 to enable share links.'];
    }
    return ['ok' => true, 'url' => rrp_app_url('vet.php?t=' . $token), 'expires_at' => $expires];
}

function rrp_share_list(int $petId): array
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT id, expires_at, revoked_at, view_count, last_viewed_at, created_at FROM health_share_links
             WHERE pet_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 10'
        );
        $stmt->execute([$petId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function rrp_share_revoke(array $owner, int $id): void
{
    rrp_db()->prepare('UPDATE health_share_links SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND owner_id = ?')->execute([$id, (int) $owner['id']]);
}

/** @return array{link:array, pet:array, owner:array}|null */
function rrp_share_resolve(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    $stmt = rrp_db()->prepare(
        'SELECT * FROM health_share_links WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $link = $stmt->fetch();
    if (!$link) {
        return null;
    }
    $owner = rrp_db()->query('SELECT * FROM owners WHERE id = ' . (int) $link['owner_id'] . " AND status = 'active'")->fetch();
    $pet = $owner ? rrp_pet_by_id((int) $link['pet_id'], (int) $owner['id']) : null;
    if (!$owner || !$pet) {
        return null;
    }
    rrp_db()->prepare('UPDATE health_share_links SET view_count = view_count + 1, last_viewed_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([(int) $link['id']]);
    return ['link' => $link, 'pet' => $pet, 'owner' => $owner];
}

/**
 * Top of the owner's Health dashboard: KPI tiles, section tabs, activity and spending series.
 * @return array{kpis:list<array>, sections:array<string,string>, walk:?array, spend:?array}
 */
function rrp_vm_health_top(array $owner, array $pet, array $vm): array
{
    require_once dirname(__DIR__) . '/finance.php';
    $r = $vm['routines'];
    $known = array_filter($r, static fn($x) => $x['state'] !== 'unknown');
    $onTrack = array_filter($known, static fn($x) => $x['state'] === 'ok');
    $overdue = count(array_filter($r, static fn($x) => $x['state'] === 'overdue'));
    $activeT = array_filter($vm['treatments'], static fn($t) => $t['status'] === 'active');
    $abnLabs = array_filter($vm['labs'], static fn($a) => in_array($a['latest']['flag'], ['low', 'high'], true));
    $w = $vm['weights'];
    $wTrend = count($w) >= 2 ? round((float) end($w)['value'] - (float) $w[count($w) - 2]['value'], 1) : null;
    $kcal = $vm['food']['today'] ?? [];
    $need = $vm['food']['need']['kcal'] ?? null;

    $walk = null;
    try {
        $walk = rrp_walk_summary($owner, $pet);
    } catch (Throwable $e) {
    }
    $spend = null;
    try {
        $spend = rrp_expense_summary($owner, $pet, 6);
    } catch (Throwable $e) {
    }
    $money = static fn(float $v, string $c) => ($c === 'USD' ? '$' : ($c === 'EUR' ? '€' : $c . ' ')) . number_format($v, $v >= 100 ? 0 : 2);

    $kpis = [
        ['label' => 'Status', 'value' => ['good' => 'Good', 'treatment' => 'Vet care', 'attention' => 'Check'][$vm['status']['state']] ?? '–',
            'sub' => count($vm['status']['alerts']) . ' alerts', 'state' => $vm['status']['state'] === 'good' ? 'ok' : 'warn', 'href' => '#sec-overview'],
        ['label' => 'Routines', 'value' => $known ? round(100 * count($onTrack) / count($known)) . '%' : '–',
            'sub' => $overdue ? $overdue . ' overdue' : 'on track', 'state' => $overdue ? 'bad' : 'ok', 'href' => '#sec-routines'],
        ['label' => 'Weight', 'value' => $vm['pet']['weight_kg'] !== null ? $vm['pet']['weight_kg'] . ' kg' : '–',
            'sub' => $wTrend === null ? 'no trend yet' : ($wTrend > 0 ? '+' : '') . $wTrend . ' kg', 'href' => '#sec-profile'],
        ['label' => 'Treatments', 'value' => (string) count($activeT), 'sub' => 'active', 'state' => $activeT ? 'warn' : 'ok', 'href' => '#sec-treatment'],
        ['label' => 'Labs', 'value' => (string) count($vm['labs']), 'sub' => $abnLabs ? count($abnLabs) . ' out of range' : 'in range', 'state' => $abnLabs ? 'bad' : 'ok', 'href' => '#sec-labs'],
        ['label' => 'Food today', 'value' => (int) ($kcal['eaten_kcal'] ?? 0) . ' kcal', 'sub' => $need ? (int) ($kcal['percent'] ?? 0) . '% of ' . (int) $need : 'need unknown', 'href' => '#sec-food'],
    ];
    if ($walk) {
        $kpis[] = ['label' => 'Walks (7 days)', 'value' => (int) $walk['week_minutes'] . ' min', 'sub' => $walk['streak'] . ' day streak', 'href' => '#sec-activity'];
    }
    if ($spend) {
        $kpis[] = ['label' => 'Spent this month', 'value' => $money($spend['this_month'], $spend['currency']), 'sub' => 'last month ' . $money($spend['last_month'], $spend['currency']), 'href' => '#sec-spending'];
    }
    $sections = ['sec-overview' => 'Overview', 'sec-profile' => 'Profile'];
    if ($vm['treatments']) {
        $sections['sec-treatment'] = 'Treatment';
    }
    $sections += ['sec-labs' => 'Labs', 'sec-food' => 'Food', 'sec-routines' => 'Routines', 'sec-activity' => 'Activity', 'sec-spending' => 'Spending'];
    return ['kpis' => $kpis, 'sections' => $sections, 'walk' => $walk, 'spend' => $spend, 'money' => $money];
}
