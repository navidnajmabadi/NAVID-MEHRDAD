<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

[$owner, $data] = rrp_api_owner_post();

$result = rrp_si_send(
    (int) $owner['id'],
    $owner,
    (int) ($data['pet_id'] ?? 0),
    (string) ($data['body'] ?? ''),
    (int) ($data['thread_id'] ?? 0)
);

$status = 200;
if (empty($result['ok'])) {
    $gate = $result['gate'] ?? '';
    $status = match ($gate) {
        'plus_required' => 402,
        'rate_limit', 'cooldown' => 429,
        default => 400,
    };
}
rrp_json($result, $status);
