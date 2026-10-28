<?php
declare(strict_types=1);

/**
 * Lightweight ops check: config readable + PDO connect + owners table.
 * Public: only ok/not ok. Full details with ?key=CRON_SECRET or an admin session.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/lib/bootstrap.php';

/** Public callers only get ok/not ok; paths and DB names need the cron key or an admin session. */
function rrp_health_view(array $out): array
{
    $cfg = rrp_config_ready() ? rrp_load_config() : [];
    $secret = (string) ($cfg['cron_secret'] ?? '');
    $key = (string) ($_GET['key'] ?? '');
    $authorised = ($secret !== '' && $key !== '' && hash_equals($secret, $key));
    if (!$authorised) {
        try {
            $authorised = rrp_current_admin() !== null;
        } catch (Throwable $e) {
        }
    }
    return $authorised ? $out : ['ok' => (bool) $out['ok'], 'app' => 'siPaw'];
}

$out = [
    'ok' => false,
    'app' => 'siPaw',
    'checks' => [
        'config_readable' => false,
        'config_path' => null,
        'db_name' => null,
        'db_user' => null,
        'pdo_connect' => false,
        'owners_table' => false,
        'lost_alerts_table' => false,
        'admin_users_table' => false,
        'push_subscriptions_table' => false,
        'vapid_configured' => false,
    ],
    'hint' => null,
    'error' => null,
];

try {
    $path = rrp_config_path_in_use();
    $out['checks']['config_readable'] = $path !== null;
    $out['checks']['config_path'] = $path;

    if ($path === null) {
        $out['hint'] = 'Place sipaw.php (or legacy ringringpaw.php) at an absolute Hostinger config path or {app}/config/sipaw.php';
        http_response_code(503);
        echo json_encode(rrp_health_view($out), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    $cfg = rrp_load_config();
    $out['checks']['db_name'] = $cfg['db']['name'] ?? null;
    $out['checks']['db_user'] = $cfg['db']['user'] ?? null;

    $pdo = rrp_db();
    $out['checks']['pdo_connect'] = true;

    $stmt = $pdo->query("SHOW TABLES LIKE 'owners'");
    $out['checks']['owners_table'] = (bool) $stmt->fetchColumn();

    if (!$out['checks']['owners_table']) {
        $out['hint'] = 'Connected to DB "' . ($out['checks']['db_name'] ?? '?')
            . '" but owners table is missing. Import sql/001_schema.sql into THIS database (same as config db.name).';
        http_response_code(503);
        echo json_encode(rrp_health_view($out), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'lost_alerts'");
    $out['checks']['lost_alerts_table'] = (bool) $stmt->fetchColumn();
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    $out['checks']['admin_users_table'] = (bool) $stmt->fetchColumn();
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    $out['checks']['push_subscriptions_table'] = (bool) $stmt->fetchColumn();
    $out['checks']['vapid_configured'] = rrp_vapid_configured();

    $out['ok'] = true;
    if (!$out['checks']['lost_alerts_table'] || !$out['checks']['admin_users_table']) {
        $out['hint'] = 'Phase 1 OK. Import sql/002_lost_admin.sql for LOST HELP ME + Admin tables.';
    } elseif (!$out['checks']['vapid_configured']) {
        $out['hint'] = 'DB OK. Add vapid keys to sipaw.php for PWA push (docs/WEB_PUSH.md). Email + inbox still work.';
    } else {
        $out['hint'] = 'Config + DB + owners + lost/admin + VAPID OK.';
    }
    echo json_encode(rrp_health_view($out), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    $out['error'] = rrp_safe_db_error($e, 'Health check failed');
    $out['hint'] = 'Fix sipaw.php (or legacy ringringpaw.php) DB credentials and ensure sql/001_schema.sql was imported into the same database name.';
    http_response_code(503);
    echo json_encode(rrp_health_view($out), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
