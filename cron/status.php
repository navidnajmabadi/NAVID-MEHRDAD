<?php
declare(strict_types=1);

/**
 * Public, read-only: is the 5-minute cron running? Shows only timestamps, no data or secrets.
 * https://sipaw.nakilab.com/cron/status.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$out = ['ok' => false, 'now_utc' => gmdate('Y-m-d H:i:s')];
try {
    $st = rrp_db()->query(
        "SELECT source, MAX(created_at) AS last_utc
         FROM site_heartbeats WHERE source IN ('cron_health', 'cron_sipo') AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 DAY) GROUP BY source"
    );
    foreach ($st->fetchAll() as $r) {
        $mins = (int) floor((time() - strtotime($r['last_utc'] . ' UTC')) / 60);
        $out[$r['source'] === 'cron_health' ? 'every_5_min' : 'every_15_min'] = ['last_run_utc' => $r['last_utc'], 'minutes_ago' => $mins];
    }
    $runs = rrp_db()->query("SELECT COUNT(*) FROM site_heartbeats WHERE source = 'cron_health' AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)")->fetchColumn();
    $out['runs_last_hour'] = (int) $runs;
    $out['ok'] = isset($out['every_5_min']) && $out['every_5_min']['minutes_ago'] <= 7;
    $out['verdict'] = $out['ok'] ? 'Cron is running.' : (isset($out['every_5_min']) ? 'Cron ran before but not in the last 7 minutes.' : 'No cron run in the last 24 hours.');
} catch (Throwable $e) {
    $out['verdict'] = 'Could not read status.';
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
