<?php
declare(strict_types=1);

/**
 * Hostinger scheduled task / cron endpoint.
 *
 * URL: https://sipaw.nakilab.com/cron/lost-escalate.php?key=YOUR_CRON_SECRET
 * Suggested schedule: every 5–15 minutes.
 *
 * Sends due LOST HELP ME escalation pings (+1h, +3h, +8h) and expires finished alerts.
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
    $stats = rrp_lost_cron_tick(50);
    echo json_encode([
        'ok' => true,
        'at' => gmdate('c'),
        'stats' => $stats,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => rrp_safe_db_error($e, 'Cron failed'),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
