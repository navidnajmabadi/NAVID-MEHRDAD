<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** GPS walk from walk.php → stored with route; SIPO cheers in the pet's chat. */
[$owner, $data] = rrp_api_owner_post();
$pet = rrp_pet_by_id((int) ($data['pet_id'] ?? 0), (int) $owner['id']);
if (!$pet) {
    rrp_json(['ok' => false, 'error' => 'Pet not found'], 404);
}
$minutes = (int) round(((int) ($data['seconds'] ?? 0)) / 60);
$started = is_numeric($data['started_ms'] ?? null) ? gmdate('Y-m-d H:i:s', (int) floor((float) $data['started_ms'] / 1000)) : null;
$res = rrp_walk_log($owner, $pet, max(1, $minutes), isset($data['meters']) ? (int) $data['meters'] : null,
    is_array($data['route'] ?? null) ? $data['route'] : null, 'gps', $started);
if (empty($res['ok'])) {
    rrp_json($res, 400);
}
// One line in the pet's chat so SIPO remembers and can react.
$thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $pet['id']);
$km = round(((int) ($data['meters'] ?? 0)) / 1000, 2);
$reply = $thread ? rrp_sipo_send($owner, $thread, "🐾 Walk done with {$pet['name']}: {$km} km in {$minutes} min (GPS).") : null;
rrp_json(['ok' => true, 'summary' => $res['summary'], 'assistant' => $reply['assistant'] ?? null]);
