<?php
declare(strict_types=1);

/**
 * Hostinger cron — Proactive SIPO: 3–4 check-ins a day at each owner's usual hours, never 21:00–09:00 local.
 *
 * URL: https://sipaw.nakilab.com/cron/sipo-nudges.php?key=YOUR_CRON_SECRET
 * Schedule: every 15 minutes.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$cfg = rrp_load_config();
$secret = (string) ($cfg['cron_secret'] ?? '');
$key = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');

if ($secret === '' || $secret === 'CHANGE_ME_CRON_SECRET_LONG_RANDOM' || !hash_equals($secret, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $stats = rrp_sipo_nudge_tick(40);
    $stats['rewards'] = rrp_rewards_tick();
    $stats['renewals'] = rrp_billing_renewal_tick();
    $stats['naki'] = rrp_naki_tick();
    rrp_ops_heartbeat('cron_sipo');
    echo json_encode([
        'ok' => true,
        'at' => gmdate('c'),
        'stats' => $stats,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => rrp_safe_db_error($e, 'SIPO nudge cron failed'),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
