<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

/** POST: send a test notification to every device of this owner and say exactly what happened. */
[$owner, $data] = rrp_api_owner_post();
if (!rrp_vapid_configured()) {
    rrp_json(['ok' => false, 'error' => 'Phone notifications are not set up on the server yet (the VAPID keys are missing in the config).', 'code' => 'vapid_not_configured']);
}
$subs = rrp_push_subscriptions_for_owner((int) $owner['id']);
if (!$subs) {
    rrp_json(['ok' => false, 'error' => 'No device is subscribed yet. Turn on phone notifications on this device first.', 'code' => 'no_subscription']);
}
$devices = [];
$sent = 0;
foreach ($subs as $sub) {
    $r = rrp_webpush_send($sub, 'SIPO · test', 'If you can read this, phone notifications work on this device 💙', rrp_app_url('settings.php#notifications'));
    if (!empty($r['gone'])) {
        rrp_delete_push_subscription_by_endpoint((string) $sub['endpoint']);
    }
    $host = (string) parse_url((string) $sub['endpoint'], PHP_URL_HOST);
    $devices[] = [
        'service' => str_contains($host, 'fcm.googleapis') ? 'Chrome / Android' : (str_contains($host, 'push.apple') ? 'Safari / iPhone' : (str_contains($host, 'mozilla') ? 'Firefox' : $host)),
        'ok' => !empty($r['ok']),
        'status' => $r['status'] ?? null,
        'error' => $r['error'] ?? null,
    ];
    $sent += !empty($r['ok']) ? 1 : 0;
}
rrp_notify_record((int) $owner['id'], 'SIPO · test', 'Test notification sent.', rrp_app_url('settings.php#notifications'));
rrp_json(['ok' => $sent > 0, 'sent' => $sent, 'devices' => $devices]);
