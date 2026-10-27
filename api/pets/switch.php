<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Instagram-style switch: the whole app follows the chosen pet. */
[$owner, $data] = rrp_api_owner_post();

$petId = (int) ($data['pet_id'] ?? 0);
$pet = rrp_pet_by_id($petId, (int) $owner['id']);
if (!$pet || (int) ($pet['is_active'] ?? 1) !== 1) {
    rrp_json(['ok' => false, 'error' => 'Pet not found'], 404);
}
rrp_sipo_set_current_pet((int) $owner['id'], $petId);
rrp_json(['ok' => true, 'current' => rrp_sipo_pet_brief($pet)]);
