<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/**
 * Boards API. GET ?id= → post + comments. POST (JSON, or multipart for a photo post):
 * create | vote | comment | report | follow | delete.
 */
$owner = rrp_api_owner();
$ownerId = (int) $owner['id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    $post = rrp_forum_post_by_id($id, $ownerId);
    if (!$post || $post['moderation_status'] !== 'visible') {
        rrp_json(['ok' => false, 'error' => 'Not found'], 404);
    }
    rrp_json(['ok' => true, 'post' => rrp_forum_present($post), 'comments' => rrp_forum_comments($id)]);
}

[, $data] = rrp_api_owner_post();
$action = (string) ($data['action'] ?? 'create');

$result = match ($action) {
    'create' => rrp_forum_create_post($ownerId, (int) ($data['pet_id'] ?? 0), (string) ($data['body'] ?? ''), $_FILES['photo'] ?? null),
    'vote' => rrp_forum_vote($ownerId, (int) ($data['post_id'] ?? 0), (int) ($data['vote'] ?? 0)),
    'comment' => rrp_forum_add_comment($ownerId, (int) ($data['pet_id'] ?? 0), (int) ($data['post_id'] ?? 0), (string) ($data['body'] ?? '')),
    'report' => rrp_forum_report($ownerId, (string) ($data['target_type'] ?? 'post'), (int) ($data['target_id'] ?? 0),
        (string) ($data['reason'] ?? 'other'), isset($data['detail']) ? (string) $data['detail'] : null),
    'follow' => rrp_forum_follow($ownerId, (int) ($data['pet_id'] ?? 0), !empty($data['on'])),
    'delete' => rrp_forum_delete_own($ownerId, (int) ($data['post_id'] ?? 0)),
    default => ['ok' => false, 'error' => 'Unknown action'],
};
if ($action === 'create' && !empty($result['ok'])) {
    $result['post'] = rrp_forum_present(rrp_forum_post_by_id((int) $result['post_id'], $ownerId) ?? []);
}
if (!empty($result['suspended'])) {
    rrp_logout();
}
rrp_json($result, empty($result['ok']) ? (!empty($result['removed']) ? 422 : 400) : 200);
