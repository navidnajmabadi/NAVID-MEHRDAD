<?php
declare(strict_types=1);

/**
 * Super-owner test tools: fill the account with realistic sample data, or wipe it back
 * to a brand-new user (pets, chats, SIPO memory) while keeping the login.
 */

/** @return array{ok:bool, pets:list<string>, steps:list<string>} */
function rrp_dev_fill_sample(array $owner): array
{
    $ownerId = (int) $owner['id'];
    $steps = [];
    $made = [];
    $tz = rrp_sipo_owner_tz($owner);
    $now = new DateTimeImmutable('now', $tz);
    $utc = static fn(DateTimeImmutable $d) => $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $try = static function (string $label, callable $fn) use (&$steps): void {
        try {
            $r = $fn();
            $steps[] = $label . (is_array($r) && isset($r['ok']) && !$r['ok'] ? ' ✗ ' . ($r['error'] ?? '') : ' ✓');
        } catch (Throwable $e) {
            $steps[] = $label . ' ✗ ' . $e->getMessage();
        }
    };

    $samples = [
        ['name' => 'Rocky', 'type' => 'dog', 'breed' => 'Golden Retriever', 'sex' => 'male', 'birth' => '-4 years', 'kg' => [31.2, 31.8, 32.4, 32.1]],
        ['name' => 'Mochi', 'type' => 'cat', 'breed' => 'Scottish Fold', 'sex' => 'female', 'birth' => '-2 years', 'kg' => [4.1, 4.3, 4.4, 4.6]],
    ];
    // Refill, never duplicate: remove earlier sample pets (same name and breed) first.
    $old = rrp_db()->prepare('SELECT id FROM pets WHERE owner_id = ? AND ((name = ? AND breed = ?) OR (name = ? AND breed = ?))');
    $old->execute([$ownerId, 'Rocky', 'Golden Retriever', 'Mochi', 'Scottish Fold']);
    foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $oldId) {
        rrp_delete_pet($ownerId, (int) $oldId);
        rrp_db()->prepare('DELETE FROM pet_expenses WHERE pet_id = ?')->execute([(int) $oldId]);
    }
    foreach ($samples as $i => $s) {
        $res = rrp_create_pet($ownerId, ['name' => $s['name'], 'type' => $s['type'], 'custom_type' => '', 'breed' => $s['breed']]);
        if (empty($res['ok'])) {
            $steps[] = 'Pet ' . $s['name'] . ' ✗ ' . ($res['error'] ?? '');
            continue;
        }
        $pet = rrp_pet_by_id((int) $res['pet_id'], $ownerId);
        $made[] = $s['name'];
        $pid = (int) $pet['id'];
        rrp_sipo_update_pet_fields($pid, $ownerId, ['sex' => $s['sex'], 'birth_date' => $now->modify($s['birth'])->format('Y-m-01'),
            'weight_kg' => end($s['kg']), 'neutered' => true]);
        $pet = rrp_pet_by_id($pid, $ownerId);
        foreach ($s['kg'] as $k => $kg) {
            rrp_sipo_save_fact($ownerId, $pid, 'weight_' . $now->modify('-' . (3 - $k) * 2 . ' months')->format('Y_m_d'), (string) $kg, 'health');
        }
        $catalog = rrp_care_catalog(rrp_care_species($pet), rrp_care_region($owner, $pet), null);
        foreach (array_slice($catalog, 0, 4) as $j => $item) {
            $try($s['name'] . ' routine ' . $item['key'], fn() => rrp_care_record_done($owner, $pet, $item['key'], $now->modify('-' . (2 + $j * 3) . ' months')->format('Y-m-d')));
        }
        $try($s['name'] . ' overdue reminder', fn() => rrp_care_reminder_create($pid, ['title' => 'Nail trim', 'due_at' => $utc($now->modify('-2 days')->setTime(10, 0)), 'status' => 'scheduled']));
        $try($s['name'] . ' today reminder', fn() => rrp_care_reminder_create($pid, ['title' => 'Brush teeth', 'due_at' => $utc($now->setTime(19, 0)), 'status' => 'scheduled']));
        $try($s['name'] . ' week reminder', fn() => rrp_care_reminder_create($pid, ['title' => 'Vet check-up', 'due_at' => $utc($now->modify('+4 days')->setTime(11, 30)), 'status' => 'scheduled']));
        $try($s['name'] . ' food', fn() => rrp_food_save($owner, $pet, ['name' => $i ? 'Purrfect Indoor Salmon' : 'Northwind Adult Lamb & Rice', 'kind' => 'dry',
            'kcal_per_cup' => $i ? 360 : 390, 'grams_per_cup' => $i ? 95 : 110, 'package_grams' => $i ? 2000 : 11300, 'ingredients' => $i ? 'salmon, rice, chicken fat' : 'lamb, brown rice, oatmeal']));
        $try($s['name'] . ' meal times', fn() => rrp_food_set_meal_times($pet, $i ? ['08:00', '20:00'] : ['07:00', '18:30'], $i ? '1/3 cup' : '1.5 cups'));
        $try($s['name'] . ' labs (old)', fn() => rrp_labs_save($owner, $pet, ['examined_on' => $now->modify('-6 months')->format('Y-m-d'), 'clinic' => 'Harbour Vet Clinic', 'exam_type' => 'blood', 'results' => [
            ['key' => 'alt', 'label' => 'ALT', 'value' => 58, 'unit' => 'U/L', 'ref_low' => 10, 'ref_high' => 125],
            ['key' => 'creatinine', 'label' => 'Creatinine', 'value' => 1.1, 'unit' => 'mg/dL', 'ref_low' => 0.5, 'ref_high' => 1.8],
            ['key' => 'glucose', 'label' => 'Glucose', 'value' => 94, 'unit' => 'mg/dL', 'ref_low' => 70, 'ref_high' => 143],
        ]], 'manual'));
        $try($s['name'] . ' labs (new)', fn() => rrp_labs_save($owner, $pet, ['examined_on' => $now->modify('-10 days')->format('Y-m-d'), 'clinic' => 'Harbour Vet Clinic', 'exam_type' => 'blood', 'results' => [
            ['key' => 'alt', 'label' => 'ALT', 'value' => $i ? 70 : 138, 'unit' => 'U/L', 'ref_low' => 10, 'ref_high' => 125],
            ['key' => 'creatinine', 'label' => 'Creatinine', 'value' => 1.3, 'unit' => 'mg/dL', 'ref_low' => 0.5, 'ref_high' => 1.8],
            ['key' => 'glucose', 'label' => 'Glucose', 'value' => 101, 'unit' => 'mg/dL', 'ref_low' => 70, 'ref_high' => 143],
        ]], 'manual'));
        if ($i === 0) {
            $try('Rocky treatment', function () use ($owner, $pet, $now) {
                $t = rrp_treatment_start($owner, $pet, ['condition' => 'Ear infection', 'started_on' => $now->modify('-3 days')->format('Y-m-d'),
                    'planned_end_on' => $now->modify('+7 days')->format('Y-m-d'), 'instructions' => 'Clean the left ear, then 4 drops twice a day.']);
                if (!empty($t['ok']) && !empty($t['treatment_id'])) {
                    rrp_treatment_add_med($owner, $pet, (int) $t['treatment_id'], ['name' => 'Otomax', 'dose_note' => '4 drops left ear', 'times' => ['08:00', '20:00']]);
                }
                return $t;
            });
            for ($d = 6; $d >= 0; $d--) {
                $start = $now->modify("-{$d} days")->setTime(7, 30);
                $try('walk', fn() => rrp_walk_log($owner, $pet, [35, 50, 20, 45, 60, 30, 40][$d], [2600, 3900, 1500, 3400, 4800, 2200, 3100][$d], null, 'manual', $utc($start)));
            }
        }
        foreach (['pee' => '08:10', 'poop' => '08:20'] as $kind => $at) {
            $try($s['name'] . ' ' . $kind, fn() => rrp_bath_log($owner, $pet, $kind, $kind === 'poop' ? 'normal' : null, null, 'manual', $at));
        }
        for ($m = 5; $m >= 0; $m--) {
            $day = $now->modify('first day of this month')->modify("-{$m} months")->modify('+' . (3 + $i) . ' days')->format('Y-m-d');
            $try('expense', fn() => rrp_expense_add($owner, $pet, 'food', $i ? 28 + $m : 64 + 2 * $m, 'USD', min($day, $now->format('Y-m-d'))));
            if ($m % 2 === 0) {
                $try('expense', fn() => rrp_expense_add($owner, $pet, $m === 0 ? 'vet' : 'supplies', $m === 0 ? 120 : 18, 'USD', min($day, $now->format('Y-m-d'))));
            }
        }
        $try($s['name'] . ' post', fn() => rrp_forum_create_post($ownerId, $pid, $i ? 'Sunbeam nap champion 🌞🐾' : 'Morning lake walk done, zero regrets 🦮💦'));
    }
    if ($made) {
        $first = rrp_pets_by_owner($ownerId);
        foreach ($first as $p) {
            if ($p['name'] === $made[0]) {
                rrp_sipo_set_current_pet($ownerId, (int) $p['id']);
            }
        }
    }
    return ['ok' => (bool) $made, 'pets' => $made, 'steps' => $steps];
}

/** Back to a brand-new user: pets (and everything under them), chats, SIPO memory; login stays. */
function rrp_dev_reset_owner(int $ownerId): array
{
    $db = rrp_db();
    $done = [];
    foreach ([
        'pets' => 'DELETE FROM pets WHERE owner_id = ?',
        'chats' => 'DELETE FROM chat_threads WHERE owner_id = ?',
        'memory' => 'DELETE FROM sipo_memory WHERE owner_id = ?',
        'nudges' => 'DELETE FROM sipo_nudges WHERE owner_id = ?',
        'tokens' => 'DELETE FROM sipo_token_window WHERE owner_id = ?',
        'usage' => 'DELETE FROM si_usage_daily WHERE owner_id = ?',
        'expenses' => 'DELETE FROM pet_expenses WHERE owner_id = ?',
        'notifications' => 'DELETE FROM notifications WHERE owner_id = ?',
        'state' => 'UPDATE owners SET current_pet_id = NULL, sipo_cooldown_until = NULL WHERE id = ?',
    ] as $label => $sql) {
        try {
            $st = $db->prepare($sql);
            $st->execute([$ownerId]);
            $done[$label] = $st->rowCount();
        } catch (Throwable $e) {
            $done[$label] = 'skipped';
        }
    }
    error_log('Dev reset to new user: owner=' . $ownerId . ' ' . json_encode($done));
    return $done;
}

/** Delete every sample pet (Rocky the Golden Retriever, Mochi the Scottish Fold) and its data. QA B03. */
function rrp_dev_remove_samples(int $ownerId): int
{
    $st = rrp_db()->prepare("SELECT id FROM pets WHERE owner_id = ? AND ((name = 'Rocky' AND breed = 'Golden Retriever') OR (name = 'Mochi' AND breed = 'Scottish Fold'))");
    $st->execute([$ownerId]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        foreach (['DELETE FROM pet_expenses WHERE pet_id = ?', 'DELETE FROM forum_posts WHERE pet_id = ?'] as $sql) {
            try {
                rrp_db()->prepare($sql)->execute([(int) $id]);
            } catch (Throwable $e) {
            }
        }
        if (!empty(rrp_delete_pet($ownerId, (int) $id)['ok'])) {
            $n++;
        }
    }
    $cur = rrp_db()->prepare('SELECT current_pet_id FROM owners WHERE id = ?');
    $cur->execute([$ownerId]);
    $curId = (int) $cur->fetchColumn();
    if ($curId && !rrp_pet_by_id($curId, $ownerId)) {
        rrp_db()->prepare('UPDATE owners SET current_pet_id = NULL WHERE id = ?')->execute([$ownerId]);
    }
    return $n;
}
