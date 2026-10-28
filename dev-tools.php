<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/devtools.php';

// Super-owner only: sample data / reset to a new user (from the side menu).
$owner = rrp_require_owner();
if (!rrp_is_super_owner($owner) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !rrp_verify_csrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Not allowed.');
}
@set_time_limit(120);
$act = (string) ($_POST['act'] ?? '');
if ($act === 'sample') {
    $r = rrp_dev_fill_sample($owner);
    $bad = array_filter($r['steps'], static fn($s) => str_contains($s, '✗'));
    rrp_flash_set($r['ok'] ? 'ok' : 'error', $r['ok']
        ? 'Sample data added 🧪 Pets: ' . implode(', ', $r['pets']) . ($bad ? '. Skipped: ' . implode('; ', array_slice($bad, 0, 3)) : '.')
        : 'Could not add sample pets: ' . implode('; ', array_slice($r['steps'], 0, 2)));
    rrp_redirect('care.php');
}
if ($act === 'reset') {
    rrp_dev_reset_owner((int) $owner['id']);
    rrp_flash_set('ok', 'Fresh start 🧹 SIPO has forgotten everything. Say hi like a new user.');
    rrp_redirect('si-chat.php?new=1');
}
http_response_code(400);
echo 'Unknown action.';
