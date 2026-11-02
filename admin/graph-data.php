<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

/** JSON for the NAKI brain graph (admin only, aggregated and redacted). GET: graph. GET ?node=: one node's detail. */
$admin = rrp_require_admin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
$node = (string) ($_GET['node'] ?? '');
if ($node !== '') {
    if (!preg_match('/^(core|[dcirt]:[a-z0-9_]+)$/', $node)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Bad node']);
        exit;
    }
    echo json_encode(['ok' => true, 'node' => rrp_graph_node($node)], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true] + rrp_graph_build(), JSON_UNESCAPED_UNICODE);
