<?php
declare(strict_types=1);

/** Free = 1 active pet, Plus = 3 active pets (v1 lock). Absolute hard max 3. */
const RRP_PLAN_FREE_PETS = 1;
const RRP_PLAN_PLUS_PETS = 3;
const RRP_SI_TEASER_MSGS = 3;
const RRP_SI_PLUS_DAILY_MSGS = 40;
const RRP_SI_PLUS_DAILY_TOKENS = 80000;

function rrp_owner_plan(array $owner): string
{
    $plan = strtolower((string) ($owner['plan'] ?? 'free'));
    return $plan === 'plus' ? 'plus' : 'free';
}

function rrp_plan_pet_limit(string $plan): int
{
    return $plan === 'plus' ? RRP_PLAN_PLUS_PETS : RRP_PLAN_FREE_PETS;
}

function rrp_owner_pet_limit(array $owner): int
{
    return rrp_plan_pet_limit(rrp_owner_plan($owner));
}

function rrp_feature_allowed(array $owner, string $feature): bool
{
    $plan = rrp_owner_plan($owner);
    // Free: forum/social + 1 pet. Soft-gated: SI, Health deep, routines, accounting.
    $freeOk = ['forum', 'social', 'finder', 'account', 'area', 'pets'];
    if (in_array($feature, $freeOk, true)) {
        return true;
    }
    if ($plan === 'plus') {
        return true;
    }
    // Free teaser for SI handled in Gate (message count), not here.
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
