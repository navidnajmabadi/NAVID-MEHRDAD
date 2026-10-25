<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    $post = rrp_forum_post_by_id($id);
    if (!$post) {
        rrp_json(['ok' => false, 'error' => 'Not found'], 404);
    }
    rrp_json(['ok' => true, 'post' => $post, 'comments' => rrp_forum_comments($id)]);
}

if ($method !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data) || !$data) {
    $data = $_POST;
}
if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF'], 403);
}

$action = (string) ($data['action'] ?? 'create');

if ($action === 'create') {
    $result = rrp_forum_create_post((int) $owner['id'], (int) ($data['pet_id'] ?? 0), (string) ($data['body'] ?? ''));
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

if ($action === 'vote') {
    $result = rrp_forum_vote((int) $owner['id'], (int) ($data['post_id'] ?? 0), (int) ($data['vote'] ?? 0));
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

if ($action === 'comment') {
    $result = rrp_forum_add_comment(
        (int) $owner['id'],
        (int) ($data['pet_id'] ?? 0),
        (int) ($data['post_id'] ?? 0),
        (string) ($data['body'] ?? '')
    );
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

if ($action === 'report') {
    $result = rrp_forum_report(
        (int) $owner['id'],
        (string) ($data['target_type'] ?? 'post'),
        (int) ($data['target_id'] ?? 0),
        (string) ($data['reason'] ?? 'other'),
        isset($data['detail']) ? (string) $data['detail'] : null
    );
    rrp_json($result, empty($result['ok']) ? 400 : 200);
}

rrp_json(['ok' => false, 'error' => 'Unknown action'], 400);
