<?php
declare(strict_types=1);

/**
 * Lightweight ops check: config readable + PDO connect + owners table.
 * No secrets in output. Safe to hit while diagnosing signup 500s.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/lib/bootstrap.php';

$out = [
    'ok' => false,
    'app' => 'RingRingPaw',
    'checks' => [
        'config_readable' => false,
        'config_path' => null,
        'db_name' => null,
        'db_user' => null,
        'pdo_connect' => false,
        'owners_table' => false,
        'lost_alerts_table' => false,
        'admin_users_table' => false,
    ],
    'hint' => null,
    'error' => null,
];

try {
    $path = rrp_config_path_in_use();
    $out['checks']['config_readable'] = $path !== null;
    $out['checks']['config_path'] = $path;

    if ($path === null) {
        $out['hint'] = 'Place ringringpaw.php at an absolute Hostinger config path or {app}/config/ringringpaw.php';
        http_response_code(503);
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
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
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'lost_alerts'");
    $out['checks']['lost_alerts_table'] = (bool) $stmt->fetchColumn();
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    $out['checks']['admin_users_table'] = (bool) $stmt->fetchColumn();

    $out['ok'] = true;
    if (!$out['checks']['lost_alerts_table'] || !$out['checks']['admin_users_table']) {
        $out['hint'] = 'Phase 1 OK. Import sql/002_lost_admin.sql for LOST HELP ME + Admin tables.';
    } else {
        $out['hint'] = 'Config + DB + owners + lost/admin tables OK.';
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    $out['error'] = rrp_safe_db_error($e, 'Health check failed');
    $out['hint'] = 'Fix ringringpaw.php DB credentials and ensure sql/001_schema.sql was imported into the same database name.';
    http_response_code(503);
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
