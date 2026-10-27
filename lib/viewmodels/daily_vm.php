<?php
declare(strict_types=1);

/** Daily life screen (daily.php): bathroom, walks, grooming for the current pet. */
function rrp_vm_daily(array $owner, array $pet): array
{
    $isDog = ($pet['type'] ?? '') === 'dog';
    $grooming = array_values(array_filter(rrp_care_status($owner, $pet), static fn($r) => $r['group'] === 'hygiene'));
    return [
        'pet' => rrp_sipo_pet_brief($pet),
        'is_dog' => $isDog,
        'bath' => rrp_bath_summary($owner, $pet),
        'walks' => $isDog ? rrp_walk_summary($owner, $pet) : null,
        'window' => $isDog ? rrp_walk_window($owner) : null,
        'grooming' => $grooming,
    ];
}
