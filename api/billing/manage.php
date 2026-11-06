<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Plus settings: auto-renew on/off, use coins on/off, remove the saved card. */
[$owner, $data] = rrp_api_owner_post();
$what = (string) ($data['what'] ?? '');
if (!in_array($what, ['auto_renew', 'use_coins', 'remove_card'], true)) {
    rrp_json(['ok' => false, 'error' => 'Unknown setting.'], 400);
}
rrp_billing_manage((int) $owner['id'], $what, !empty($data['on']));
rrp_json(['ok' => true, 'profile' => rrp_billing_profile((int) $owner['id'])]);
