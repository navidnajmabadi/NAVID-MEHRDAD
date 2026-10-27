<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Quick-log buttons on the Daily page: bathroom event or a manual walk. */
[$owner, $data] = rrp_api_owner_post();
$pet = rrp_pet_by_id((int) ($data['pet_id'] ?? 0), (int) $owner['id']);
if (!$pet) {
    rrp_json(['ok' => false, 'error' => 'Pet not found'], 404);
}
$what = (string) ($data['what'] ?? '');
if ($what === 'walk') {
    $res = rrp_walk_log($owner, $pet, (int) ($data['minutes'] ?? 0),
        is_numeric($data['km'] ?? null) ? (int) round((float) $data['km'] * 1000) : null, null, 'manual');
} else {
    $res = rrp_bath_log($owner, $pet, $what, isset($data['stool']) ? (string) $data['stool'] : null, null, 'button');
}
rrp_json($res, empty($res['ok']) ? 400 : 200);
