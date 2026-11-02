<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

/** POST (admin only): mark a request, or turn a request into a permanent test case. */
$admin = rrp_require_admin();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !rrp_verify_csrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Reload the page.']);
    exit;
}
$id = (int) ($_POST['id'] ?? 0);
$act = (string) ($_POST['act'] ?? '');
$st = rrp_db()->prepare('SELECT id, domain, req_text, marked FROM si_request_log WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    echo json_encode(['ok' => false, 'error' => 'Request not found.']);
    exit;
}
if ($act === 'mark') {
    $v = $r['marked'] ? 0 : 1;
    rrp_db()->prepare('UPDATE si_request_log SET marked = ? WHERE id = ?')->execute([$v, $id]);
    echo json_encode(['ok' => true, 'marked' => (bool) $v]);
} elseif ($act === 'test') {
    $tid = rrp_lab_add((string) $r['domain'], (string) $r['req_text'], (string) ($_POST['must_any'] ?? ''), (string) ($_POST['must_not'] ?? ''), 'issue');
    echo json_encode($tid ? ['ok' => true, 'test_id' => $tid] : ['ok' => false, 'error' => 'A test with this text already exists.']);
} else {
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
}
