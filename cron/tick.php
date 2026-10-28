<?php
declare(strict_types=1);

/**
 * Hostinger cron — ONE job for everything, every 5 minutes:
 *   hPanel → Cron jobs → every 5 minutes → curl -fsS "https://sipaw.nakilab.com/cron/tick.php?key=YOUR_CRON_SECRET"
 *
 * Every run: reminders due, care follow-ups, meals, food supply, Lost mode pings.
 * Every 15 minutes (minute 0-4, 15-19, 30-34, 45-49): SIPO check-ins, rewards,
 * renewals and NAKI (they call the model, so not every 5 minutes).
 * The older per-job URLs keep working; remove them in hPanel once this one runs.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$cfg = rrp_load_config();
$secret = (string) ($cfg['cron_secret'] ?? '');
$key = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? ($argv[1] ?? ''));

if ($secret === '' || $secret === 'CHANGE_ME_CRON_SECRET_LONG_RANDOM' || !hash_equals($secret, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_SLASHES);
    exit;
}

@set_time_limit(240);
$stats = [];
$run = static function (string $name, callable $job) use (&$stats): void {
    // One failing job must not stop the others.
    try {
        $stats[$name] = $job();
    } catch (Throwable $e) {
        $stats[$name] = ['error' => rrp_safe_db_error($e, $name . ' failed')];
    }
};

$run('lost', static fn() => rrp_lost_cron_tick(50));
$run('lost_owner', static fn() => rrp_lost_owner_nudge_tick());
$run('health', static fn() => rrp_health_cron_tick(50));
$run('followups', static fn() => rrp_care_followup_tick(60));
$run('meals', static fn() => rrp_food_meal_tick());
$run('supply', static fn() => rrp_food_supply_tick());
rrp_ops_heartbeat('cron_health');

$quarter = ((int) gmdate('i')) % 15 < 5 || isset($_GET['all']);
if ($quarter) {
    $run('sipo_nudges', static fn() => rrp_sipo_nudge_tick(40));
    $run('rewards', static fn() => rrp_rewards_tick());
    $run('renewals', static fn() => rrp_billing_renewal_tick());
    $run('naki', static fn() => rrp_naki_tick());
    rrp_ops_heartbeat('cron_sipo');
}

echo json_encode(['ok' => true, 'at' => gmdate('c'), 'quarter' => $quarter, 'stats' => $stats], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
