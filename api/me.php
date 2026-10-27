<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$plan = rrp_owner_plan($owner);
$limit = rrp_owner_pet_limit($owner);
$active = rrp_pet_count((int) $owner['id'], true);
$area = rrp_owner_area($owner);
$day = rrp_si_toronto_day();
$usage = rrp_si_usage_row((int) $owner['id'], $day);

rrp_json([
    'ok' => true,
    'owner' => [
        'id' => (int) $owner['id'],
        'name' => $owner['name'],
        'email' => $owner['email'],
        'plan' => $plan,
        'locale' => $owner['locale'] ?? 'en',
    ],
    'pets' => [
        'active' => $active,
        'limit' => $limit,
        'can_add' => $active < $limit,
    ],
    'area' => $area,
    'si_usage' => [
        'day' => $day,
        'message_count' => (int) ($usage['message_count'] ?? 0),
        'token_count' => (int) ($usage['token_count'] ?? 0),
        'daily_msg_cap' => $plan === 'plus' ? 0 : (int) rrp_sipo_cfg('free_daily_msgs', 0),
    ],
]);
