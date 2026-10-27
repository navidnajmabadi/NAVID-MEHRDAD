<?php
declare(strict_types=1);

/**
 * ViewModels: one plain array per screen. Pages render them in PHP and the
 * matching api/ endpoint returns the same array as JSON.
 */

/** Pets for the Instagram-style switcher. */
function rrp_vm_pet_switcher(array $owner): array
{
    $pets = rrp_pets_by_owner((int) $owner['id']);
    $current = rrp_sipo_current_pet($owner, $pets);
    return [
        'pets' => array_map('rrp_sipo_pet_brief', $pets),
        'current' => $current ? rrp_sipo_pet_brief($current) : null,
        'can_add' => rrp_pet_count((int) $owner['id'], true) < rrp_owner_pet_limit($owner),
    ];
}

/**
 * SIPO chat screen.
 * @param bool $newPet open the onboarding thread for a new pet
 */
function rrp_vm_chat(array $owner, bool $newPet = false): array
{
    $ownerId = (int) $owner['id'];
    $switcher = rrp_vm_pet_switcher($owner);
    $onboarding = $newPet || $switcher['current'] === null;

    if ($onboarding && !$switcher['can_add'] && $switcher['current'] !== null) {
        // Over the pet cap: stay on the current pet's chat, explain in the UI.
        $onboarding = false;
        $capNote = 'Free includes 1 pet for now. Archive a pet or upgrade to add another.';
    }

    $thread = $onboarding
        ? rrp_sipo_onboarding_thread($ownerId)
        : rrp_sipo_thread_for_pet($ownerId, (int) $switcher['current']['id']);

    $gate = rrp_sipo_gate($owner);
    $live = rrp_sipo_live_context($owner, false);
    $pet = !empty($thread['pet_id']) ? rrp_pet_by_id((int) $thread['pet_id'], $ownerId) : null;

    return [
        'owner' => ['nickname' => rrp_sipo_owner_nickname($owner)],
        'switcher' => $switcher,
        'thread' => [
            'id' => (int) $thread['id'],
            'onboarding' => $pet === null,
            'pet' => $pet ? rrp_sipo_pet_brief($pet) : null,
        ],
        'messages' => rrp_sipo_messages((int) $thread['id'], 60),
        'gate' => $gate['ok'] ? null : ['kind' => $gate['gate'] ?? 'blocked', 'message' => $gate['error'] ?? ''],
        'note' => $capNote ?? null,
        'day_part' => $live['day_part'],
        'panel' => $pet ? rrp_vm_pet_panel($pet) : null,
        'poll_ms' => 20000,
    ];
}

/** Side panel on tablet/desktop: pet card + what SIPO is tracking. */
function rrp_vm_pet_panel(array $pet): array
{
    $facts = rrp_sipo_facts((int) $pet['id']);
    $profile = [];
    foreach ([
        'Breed' => $pet['breed'] ?? null,
        'Sex' => ($pet['sex'] ?? 'unknown') !== 'unknown' ? ucfirst((string) $pet['sex']) : null,
        'Age' => !empty($pet['age_years']) ? rtrim(rtrim((string) $pet['age_years'], '0'), '.') . ' y' : null,
        'Weight' => !empty($pet['weight_kg']) ? rtrim(rtrim((string) $pet['weight_kg'], '0'), '.') . ' kg' : null,
    ] as $k => $v) {
        $profile[] = ['label' => $k, 'value' => $v];
    }
    $upcoming = [];
    try {
        $stmt = rrp_db()->prepare(
            "SELECT title, due_at FROM care_reminders WHERE pet_id = ? AND status IN ('pending_approval','scheduled','snoozed')
             ORDER BY due_at ASC LIMIT 4"
        );
        $stmt->execute([(int) $pet['id']]);
        foreach ($stmt->fetchAll() as $r) {
            $upcoming[] = ['title' => $r['title'], 'due' => substr((string) $r['due_at'], 0, 10)];
        }
    } catch (Throwable $e) {
    }
    return [
        'profile' => $profile,
        'facts_count' => count($facts),
        'upcoming' => $upcoming,
    ];
}
