<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/finance.php';

/**
 * App-wide SIPO tools: everything an owner can do in the app, SIPO can do from chat
 * (add / switch pets, Lost mode, Found, owner profile, Social posts).
 * Owner and pets always come from the server-side thread, never from model ids.
 */

const RRP_SIPO_APP_TOOLS = ['add_pet', 'switch_pet', 'list_my_pets', 'start_lost_mode', 'mark_pet_found', 'update_my_profile', 'post_to_social',
    'open_page', 'log_expense', 'spending_summary', 'ask_choices'];

/** Pages SIPO can open for the owner: key => [file, what it shows]. Missing files are skipped. */
function rrp_sipo_app_pages(): array
{
    $all = [
        'home' => ['dashboard.php', 'Home overview of all pets'],
        'health' => ['care.php', 'Health dashboard: profile, routines, vaccines, treatments, labs, food, charts'],
        'daily' => ['daily.php', 'Daily life: bathroom, walks, grooming'],
        'routines' => ['routines.php', 'Everything scheduled: overdue, today, this week'],
        'social' => ['forum.php', 'Social board: posts from pets'],
        'lost' => ['lost.php', 'Lost mode page and how it works'],
        'finance' => ['finance.php', 'Spending on pets: monthly totals and categories'],
        'settings' => ['settings.php', 'Settings: profile, pets, password, account'],
        'account' => ['account.php', 'Owner profile: name, phone, address'],
        'notifications' => ['alerts.php', 'Notification centre'],
        'rewards' => ['rewards.php', 'Paw coins and invite friends'],
        'plus' => ['plus.php', 'Plus plan and payment'],
        'walk' => ['walk.php', 'Start a tracked walk'],
        'vets' => ['vet.php', 'Vet records'],
        'area' => ['area.php', 'Home area on the map'],
        'support' => ['support.php', 'Help and support tickets'],
    ];
    return array_filter($all, static fn($p) => is_file(dirname(__DIR__, 2) . '/' . $p[0]));
}

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
        $fn('switch_pet', 'Only when the owner explicitly asks to go to another pet\'s chat: shows a button to open it. Never call it just because another pet is mentioned; use pet_name on the action tool instead.', [
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
        $fn('post_to_social', 'Publish a post on the Social board as THIS pet. If the owner asks you to write one, write it yourself (short, warm, 1 to 3 emojis, in the pet\'s voice if fun), show the draft, and call this only after they say yes.', [
            'text' => ['type' => 'string'],
        ], ['text']),
        $fn('open_page', 'Only when the owner explicitly asks to see or open a page (e.g. "show my dashboard", "open settings"): shows a button to open it. Never call it to answer a question about a page. Pages: '
            . implode(', ', array_map(static fn($k, $p) => $k . ' (' . $p[1] . ')', array_keys(rrp_sipo_app_pages()), rrp_sipo_app_pages())), [
            'page' => ['type' => 'string', 'enum' => array_keys(rrp_sipo_app_pages())],
        ], ['page']),
        $fn('log_expense', 'Record money the owner spent on THIS pet (food, vet, medicine, grooming, supplies).', [
            'category' => ['type' => 'string', 'enum' => array_keys(RRP_EXPENSE_CATEGORIES)],
            'amount' => ['type' => 'number'],
            'currency' => ['type' => 'string', 'description' => '3-letter code if the owner said one, e.g. USD, EUR, IRR'],
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD local; omit for today'],
            'note' => ['type' => 'string'],
        ], ['category', 'amount']),
        $fn('ask_choices', 'Show tap-to-answer buttons under your question (2 to 6 short options). The app adds "Other" automatically. Write the question in your message too.', [
            'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '2 to 6 options, max 4 words each'],
        ], ['options']),
        $fn('spending_summary', 'How much the owner spent on pets: this month, last month, this year, by category.', [
            'all_pets' => ['type' => 'boolean'],
        ]),
    ];
}

/**
 * @param array{owner:array, thread:array, pet:?array} $ctx  (by reference: may add links = buttons under the reply)
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
            $kind = rrp_sipo_norm_kind((string) ($a['kind'] ?? ''), (string) ($a['other_kind'] ?? ''));
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
            $ctx['events'][] = 'pet_added';
            $ctx['links'][] = ['url' => 'si-chat.php?pet=' . $newId, 'label' => 'Open ' . trim((string) ($a['name'] ?? 'the new pet')) . '’s chat'];
            return ['ok' => true, 'pet_id' => $newId, 'note' => 'Saved. A button under your reply opens the new pet\'s chat; this chat stays open.'];

        case 'switch_pet':
            $want = mb_strtolower(trim((string) ($a['name'] ?? '')));
            foreach (rrp_pets_by_owner($ownerId) as $p) {
                if (mb_strtolower((string) $p['name']) === $want) {
                    // Never switch on our own (QA B06/B24): offer a button, the owner decides.
                    $ctx['links'][] = ['url' => 'si-chat.php?pet=' . (int) $p['id'], 'label' => 'Open ' . $p['name'] . '’s chat'];
                    return ['ok' => true, 'note' => 'A button under your reply opens ' . $p['name'] . '\'s chat. Do not say it already switched.'];
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
                $ctx['links'][] = ['url' => 'lost.php?pet=' . (int) $pet['id'], 'label' => 'Open Lost mode'];
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

        case 'open_page':
            $pages = rrp_sipo_app_pages();
            $key = (string) ($a['page'] ?? '');
            if (!isset($pages[$key])) {
                return ['ok' => false, 'error' => 'Unknown page.', 'pages' => array_keys($pages)];
            }
            $url = $pages[$key][0];
            if ($pet && in_array($key, ['health', 'daily', 'routines'], true)) {
                $url .= '?pet=' . (int) $pet['id'];
            }
            $ctx['links'][] = ['url' => $url, 'label' => 'Open ' . ucfirst($key)];
            $ctx['events'][] = 'page_opened';
            return ['ok' => true, 'note' => 'A button under your reply opens it. Do not say it is already open.'];

        case 'log_expense':
            if ($pet === null) {
                return ['ok' => false, 'error' => 'No pet in this chat yet.'];
            }
            $res = rrp_expense_add($owner, $pet, (string) ($a['category'] ?? 'other'), (float) ($a['amount'] ?? 0),
                isset($a['currency']) ? (string) $a['currency'] : null, isset($a['date']) ? (string) $a['date'] : null, isset($a['note']) ? (string) $a['note'] : null);
            if (!empty($res['ok'])) {
                $ctx['events'][] = 'expense_logged';
            }
            return $res;

        case 'ask_choices':
            $opts = [];
            foreach ((array) ($a['options'] ?? []) as $o) {
                $o = trim(mb_substr((string) $o, 0, 40));
                if ($o !== '' && !preg_match('/^other\b/i', $o) && !in_array($o, $opts, true)) {
                    $opts[] = $o;
                }
            }
            if (count($opts) < 2) {
                return ['ok' => false, 'error' => 'Give 2 to 6 options.'];
            }
            $ctx['choices'] = array_slice($opts, 0, 6);
            return ['ok' => true, 'note' => 'Buttons shown with an Other option. Now write the question (one question) in your reply.'];

        case 'spending_summary':
            $sum = rrp_expense_summary($owner, empty($a['all_pets']) ? $pet : null, 12);
            unset($sum['recent'], $sum['months']);
            return ['ok' => true] + $sum;
    }
    return ['ok' => false, 'error' => 'Unknown tool ' . $name];
}
