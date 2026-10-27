<?php
declare(strict_types=1);

/** Free = 1 active pet, Plus = 3 active pets (v1 lock). Absolute hard max 3 for paid. */
const RRP_PLAN_FREE_PETS = 1;
const RRP_PLAN_PLUS_PETS = 3;
const RRP_PLAN_SUPER_PETS = 9999;
const RRP_SI_TEASER_MSGS = 3;
const RRP_SI_PLUS_DAILY_MSGS = 40;
const RRP_SI_PLUS_DAILY_TOKENS = 80000;

function rrp_super_owner_emails(): array
{
    $defaults = [
        'navid.najmabadi@gmail.com',
        'm.kiazade@gmail.com',
    ];
    if (!rrp_config_ready()) {
        return $defaults;
    }
    $cfg = rrp_load_config()['super_owners'] ?? $defaults;
    if (!is_array($cfg) || $cfg === []) {
        return $defaults;
    }
    return array_values(array_unique(array_map(
        static fn($e) => strtolower(trim((string) $e)),
        $cfg
    )));
}

function rrp_is_super_owner(array $owner): bool
{
    if (!empty($owner['is_super'])) {
        return true;
    }
    $email = strtolower(trim((string) ($owner['email'] ?? '')));
    if ($email === '') {
        return false;
    }
    return in_array($email, rrp_super_owner_emails(), true);
}

function rrp_owner_plan(array $owner): string
{
    if (rrp_is_super_owner($owner)) {
        return 'plus';
    }
    $plan = strtolower((string) ($owner['plan'] ?? 'free'));
    // Paid months run out; NULL expiry = manual/tester Plus that never ends.
    if ($plan === 'plus' && !empty($owner['plan_expires_at']) && strtotime((string) $owner['plan_expires_at'] . ' UTC') < time()) {
        return 'free';
    }
    return $plan === 'plus' ? 'plus' : 'free';
}

function rrp_plan_pet_limit(string $plan): int
{
    return $plan === 'plus' ? RRP_PLAN_PLUS_PETS : RRP_PLAN_FREE_PETS;
}

function rrp_owner_pet_limit(array $owner): int
{
    if (rrp_is_super_owner($owner)) {
        return RRP_PLAN_SUPER_PETS;
    }
    return rrp_plan_pet_limit(rrp_owner_plan($owner));
}

function rrp_feature_allowed(array $owner, string $feature): bool
{
    if (rrp_is_super_owner($owner)) {
        return true;
    }
    $plan = rrp_owner_plan($owner);
    // Free: forum/social + 1 pet. Soft-gated: SI, Health deep, routines, accounting.
    $freeOk = ['forum', 'social', 'finder', 'account', 'area', 'pets'];
    if (in_array($feature, $freeOk, true)) {
        return true;
    }
    if ($plan === 'plus') {
        return true;
    }
    return false;
}

function rrp_plus_required_payload(string $feature = 'feature'): array
{
    return [
        'ok' => false,
        'gate' => 'plus_required',
        'feature' => $feature,
        'error' => 'Plus required for this feature. Free includes social + 1 pet.',
        'upgrade_url' => 'plus.php',
    ];
}

/**
 * Ensure config-listed super emails are flagged on owners + present in admin_users.
 * NEVER updates owners.password_hash (login password stays whatever the owner set / CLI set).
 * New admin rows only: random password unless $bootstrapPassword passed (admin panel login, not owner).
 *
 * @return array{owners_flagged:int, admins_ensured:int, notes:list<string>}
 */
function rrp_elevate_super_owners(?string $bootstrapPassword = null): array
{
    $notes = [];
    $ownersFlagged = 0;
    $adminsEnsured = 0;
    $emails = rrp_super_owner_emails();
    $names = [
        'navid.najmabadi@gmail.com' => 'Navid Najmabadi',
        'm.kiazade@gmail.com' => 'Mehrdad Kiazade',
    ];

    try {
        foreach ($emails as $email) {
            try {
                $stmt = rrp_db()->prepare('UPDATE owners SET is_super = 1, plan = \'plus\' WHERE LOWER(email) = ?');
                $stmt->execute([$email]);
                $ownersFlagged += $stmt->rowCount();
            } catch (Throwable $e) {
                // is_super column may be missing until 008
                try {
                    $stmt = rrp_db()->prepare('UPDATE owners SET plan = \'plus\' WHERE LOWER(email) = ?');
                    $stmt->execute([$email]);
                    $ownersFlagged += $stmt->rowCount();
                    $notes[] = 'is_super column missing — plan=plus only for ' . $email;
                } catch (Throwable $e2) {
                    $notes[] = 'Could not flag owner ' . $email;
                }
            }

            $chk = rrp_db()->prepare('SELECT id FROM admin_users WHERE LOWER(email) = ? LIMIT 1');
            $chk->execute([$email]);
            $existing = $chk->fetch();
            if ($existing) {
                try {
                    $upd = rrp_db()->prepare('UPDATE admin_users SET status = \'active\', role_label = \'super\' WHERE id = ?');
                    $upd->execute([(int) $existing['id']]);
                } catch (Throwable $e) {
                    $upd = rrp_db()->prepare('UPDATE admin_users SET status = \'active\' WHERE id = ?');
                    $upd->execute([(int) $existing['id']]);
                }
                $adminsEnsured++;
                continue;
            }

            $name = $names[$email] ?? explode('@', $email)[0];
            $pass = $bootstrapPassword ?: bin2hex(random_bytes(16));
            $created = rrp_admin_create($name, $email, $pass);
            if (!empty($created['ok'])) {
                $adminsEnsured++;
                try {
                    $upd = rrp_db()->prepare('UPDATE admin_users SET role_label = \'super\' WHERE id = ?');
                    $upd->execute([(int) $created['admin_id']]);
                } catch (Throwable $e) {
                }
                $notes[] = 'Created admin for ' . $email . ' — set password via Admin setup / reset if bootstrap pass was random.';
            } else {
                $notes[] = 'Admin ensure failed for ' . $email . ': ' . ($created['error'] ?? 'unknown');
            }
        }
    } catch (Throwable $e) {
        $notes[] = 'elevate error: ' . $e->getMessage();
    }

    return [
        'owners_flagged' => $ownersFlagged,
        'admins_ensured' => $adminsEnsured,
        'notes' => $notes,
    ];
}
