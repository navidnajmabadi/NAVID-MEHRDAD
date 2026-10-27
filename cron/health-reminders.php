<?php
declare(strict_types=1);

/**
 * Hostinger cron — vaccine / med / vet reminders.
 *
 * URL: https://sipaw.nakilab.com/cron/health-reminders.php?key=YOUR_CRON_SECRET
 * Suggested: same schedule as lost-escalate (every 10 minutes).
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
    $stats = rrp_health_cron_tick(50);
    // PR 2: reminder follow-ups ("did you go?"), meal check-ins, food running out.
    $stats['followups'] = rrp_care_followup_tick(60);
    $stats['meals'] = rrp_food_meal_tick();
    $stats['supply'] = rrp_food_supply_tick();
    echo json_encode([
        'ok' => true,
        'at' => gmdate('c'),
        'stats' => $stats,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => rrp_safe_db_error($e, 'Health cron failed'),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
