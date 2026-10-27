<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

// Report factory: de-identified CSV exports (admin only).
$admin = rrp_require_admin();
$key = (string) ($_GET['r'] ?? '');
if (!isset(rrp_ins_reports()[$key])) {
    http_response_code(404);
    exit('Unknown report');
}
rrp_admin_log((int) $admin['id'], 'report_export', 'report', null, $key);
rrp_ins_csv($key);
