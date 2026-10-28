<?php
declare(strict_types=1);

/**
 * App-wide SIPO tools: everything an owner can do in the app, SIPO can do from chat
 * (add / switch pets, Lost mode, Found, owner profile, Social posts).
 * Owner and pets always come from the server-side thread, never from model ids.
 */

const RRP_SIPO_APP_TOOLS = ['add_pet', 'switch_pet', 'list_my_pets', 'start_lost_mode', 'mark_pet_found', 'update_my_profile', 'post_to_social'];

/** @return list<array<string,mixed>> */
function rrp_sipo_app_tool_defs(callable $fn): array
{
    return [
        $fn('add_pet', 'Add ANOTHER pet for this owner (works from any chat). Call it once you know the new pet\'s name and kind. The app then opens the new pet\'s own chat.', [
            'name' => ['type' => 'string', 'description' => 'Pet name as the owner confirmed it'],
            'kind' => ['type' => 'string', 'enum' => ['dog', 'cat', 'bird', 'rabbit', 'hamster', 'other']],
            'other_kind' => ['type' => 'string', 'description' => 'If kind=other, what animal'],
            'breed' => ['type' => 'string'],
        ], ['name', 'kind']),
        $fn('switch_pet', 'Open the chat of another of the owner\'s pets by name.', [
            'name' => ['type' => 'string'],
        ], ['name']),
        $fn('list_my_pets', 'List all of the owner\'s pets with kind and whether Lost mode is on.', []),
        $fn('start_lost_mode', 'Turn on Lost mode for THIS pet when the owner says it is lost or missing. Alerts nearby SIPAW users.', [
            'seen_lat' => ['type' => 'number', 'description' => 'Only if the owner shared where it was last seen'],
            'seen_lng' => ['type' => 'number'],
        ]),
        $fn('mark_pet_found', 'Close Lost mode for THIS pet when the owner says it is home or found.', []),
        $fn('update_my_profile', 'Save the OWNER\'s own details they told you (what to call them, phone, home address). Send only what changed.', [
            'name' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'address' => ['type' => 'string', 'description' => 'Home address or area, used for nearby Lost mode alerts'],
        ]),
        $fn('post_to_social', 'Publish a text post on the SIPAW Social board as THIS pet, only after the owner approved the exact text.', [
            'text' => ['type' => 'string'],
        ], ['text']),
    ];
}

/**
 * @param array{owner:array, thread:array, pet:?array} $ctx  (by reference: may set navigate)
 * @return array<string,mixed>
 */
function rrp_sipo_run_app_tool(string $name, array $a, array &$ctx): array
{
    $owner = $ctx['owner'];
    $ownerId = (int) $owner['id'];
    $pet = $ctx['pet'];

    switch ($name) {
        case 'add_pet':
            if ($pet === null) {
                // Onboarding chat: the normal path binds this chat to the new pet.
                return rrp_sipo_run_tool('create_pet_profile', $a, $ctx);
            }
            $kind = (string) ($a['kind'] ?? '');
            $res = rrp_create_pet($ownerId, [
                'name' => (string) ($a['name'] ?? ''),
                'type' => $kind === 'other' ? 'custom' : $kind,
                'custom_type' => (string) ($a['other_kind'] ?? ($kind === 'other' ? 'pet' : '')),
                'breed' => (string) ($a['breed'] ?? ''),
            ]);
            if (empty($res['ok'])) {
                $limit = ($res['gate'] ?? '') !== '' || str_contains(strtolower((string) ($res['error'] ?? '')), 'limit');
                return ['ok' => false, 'error' => $res['error'] ?? 'Could not add the pet.',
                    'hint' => $limit ? 'Free has 1 pet for now; Plus allows up to 3 (Settings → Plus).' : null];
            }
            $newId = (int) $res['pet_id'];
            rrp_sipo_set_current_pet($ownerId, $newId);
            $ctx['events'][] = 'pet_added';
            $ctx['navigate'] = 'si-chat.php?pet=' . $newId;
            return ['ok' => true, 'pet_id' => $newId, 'note' => 'Saved. The app opens the new pet\'s chat in a moment; say so briefly.'];

        case 'switch_pet':
            $want = mb_strtolower(trim((string) ($a['name'] ?? '')));
            foreach (rrp_pets_by_owner($ownerId) as $p) {
                if (mb_strtolower((string) $p['name']) === $want) {
                    rrp_sipo_set_current_pet($ownerId, (int) $p['id']);
                    $ctx['navigate'] = 'si-chat.php?pet=' . (int) $p['id'];
                    return ['ok' => true, 'note' => 'The app opens ' . $p['name'] . '\'s chat now.'];
                }
            }
            return ['ok' => false, 'error' => 'No pet with that name.', 'pets' => array_column(rrp_pets_by_owner($ownerId), 'name')];

        case 'list_my_pets':
            $out = [];
            foreach (rrp_pets_by_owner($ownerId) as $p) {
                $out[] = ['name' => $p['name'], 'kind' => rrp_pet_type_label($p), 'breed' => $p['breed'] ?: null,
                    'lost' => rrp_open_lost_for_pet((int) $p['id']) !== null, 'this_chat' => $pet && (int) $pet['id'] === (int) $p['id']];
            }
            return ['ok' => true, 'pets' => $out];

        case 'start_lost_mode':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet in this chat yet.'];
            }
            $lat = isset($a['seen_lat']) ? (float) $a['seen_lat'] : null;
            $lng = isset($a['seen_lng']) ? (float) $a['seen_lng'] : null;
            $res = rrp_trigger_lost($ownerId, (int) $pet['id'], $lat, $lng);
            if (!empty($res['ok'])) {
                $ctx['emergency'] = true;
                $ctx['events'][] = 'lost_started';
                $ctx['navigate'] = 'si-chat.php?pet=' . (int) $pet['id'];
            }
            return ['ok' => !empty($res['ok']), 'error' => $res['error'] ?? null,
                'note' => !empty($res['ok']) ? 'Nearby SIPAW users are being alerted. Walk the owner through the search plan.' : null];

        case 'mark_pet_found':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet in this chat yet.'];
            }
            $alert = rrp_open_lost_for_pet((int) $pet['id']);
            if (!$alert) {
                return ['ok' => false, 'error' => 'Lost mode is not on for this pet.'];
            }
            $res = rrp_mark_lost_found($ownerId, (int) $alert['id']);
            if (!empty($res['ok'])) {
                $ctx['events'][] = 'lost_found';
                $ctx['navigate'] = 'si-chat.php?pet=' . (int) $pet['id'];
            }
            return ['ok' => !empty($res['ok']), 'error' => $res['error'] ?? null];

        case 'update_my_profile':
            $cur = rrp_db()->prepare('SELECT name, nickname, phone, address FROM owners WHERE id = ? LIMIT 1');
            $cur->execute([$ownerId]);
            $row = $cur->fetch() ?: [];
            $res = rrp_update_owner_profile($ownerId, [
                'nickname' => trim((string) ($a['name'] ?? '')) ?: (string) ($row['nickname'] ?: $row['name'] ?? ''),
                'phone' => array_key_exists('phone', $a) ? (string) $a['phone'] : (string) ($row['phone'] ?? ''),
                'address' => array_key_exists('address', $a) ? (string) $a['address'] : (string) ($row['address'] ?? ''),
            ]);
            if (!empty($res['ok'])) {
                $ctx['events'][] = 'owner_updated';
            }
            return ['ok' => !empty($res['ok']), 'error' => $res['error'] ?? null];

        case 'post_to_social':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet in this chat yet.'];
            }
            $res = rrp_forum_create_post($ownerId, (int) $pet['id'], (string) ($a['text'] ?? ''));
            if (!empty($res['ok'])) {
                $ctx['events'][] = 'social_posted';
            }
            return ['ok' => !empty($res['ok']), 'error' => $res['error'] ?? null, 'where' => 'Social tab'];
    }
    return ['ok' => false, 'error' => 'Unknown tool ' . $name];
}
