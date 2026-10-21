<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    $data = $_POST;
}
if ($method === 'GET') {
    $data = array_merge($data, $_GET);
}

$action = (string) ($data['action'] ?? ($method === 'GET' ? 'messages' : ''));

if ($method === 'GET' && $action === 'messages') {
    $threadId = (int) ($data['thread_id'] ?? 0);
    $afterId = (int) ($data['after_id'] ?? 0);
    $thread = $threadId ? rrp_chat_thread_by_id($threadId) : null;
    if (!$thread) {
        rrp_json(['ok' => false, 'error' => 'Thread not found'], 404);
    }
    $owner = rrp_current_owner();
    $ok = false;
    if ($owner && rrp_chat_owner_can_access($thread, (int) $owner['id'])) {
        $ok = true;
    } elseif (rrp_chat_finder_can_access($thread)) {
        $ok = true;
    }
    if (!$ok) {
        rrp_json(['ok' => false, 'error' => 'Not allowed'], 403);
    }
    $messages = rrp_chat_messages($threadId, $afterId);
    rrp_json(['ok' => true, 'messages' => $messages]);
}

if ($method !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST required'], 405);
}

if (!rrp_verify_csrf($data['csrf'] ?? null)) {
    rrp_json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
}

if ($action === 'start') {
    $scanId = (int) ($data['scan_id'] ?? 0);
    $name = isset($data['finder_name']) ? (string) $data['finder_name'] : null;
    if (!$scanId) {
        rrp_json(['ok' => false, 'error' => 'scan_id required'], 400);
    }
    $result = rrp_chat_start_for_scan($scanId, $name);
    if (empty($result['ok'])) {
        rrp_json(['ok' => false, 'error' => $result['error'] ?? 'Could not start'], 400);
    }
    rrp_json([
        'ok' => true,
        'thread_id' => (int) $result['thread']['id'],
        'created' => !empty($result['created']),
    ]);
}

if ($action === 'send') {
    $threadId = (int) ($data['thread_id'] ?? 0);
    $body = (string) ($data['body'] ?? '');
    $as = (string) ($data['as'] ?? '');
    $thread = $threadId ? rrp_chat_thread_by_id($threadId) : null;
    if (!$thread) {
        rrp_json(['ok' => false, 'error' => 'Thread not found'], 404);
    }

    $owner = rrp_current_owner();
    if ($as === 'owner' || ($as === '' && $owner && rrp_chat_owner_can_access($thread, (int) $owner['id']))) {
        if (!$owner || !rrp_chat_owner_can_access($thread, (int) $owner['id'])) {
            rrp_json(['ok' => false, 'error' => 'Not allowed'], 403);
        }
        $result = rrp_chat_send($threadId, 'owner', $body, null, (int) $owner['id']);
    } else {
        if (!rrp_chat_finder_can_access($thread)) {
            rrp_json(['ok' => false, 'error' => 'Not allowed'], 403);
        }
        $result = rrp_chat_send($threadId, 'finder', $body);
    }

    if (empty($result['ok'])) {
        rrp_json(['ok' => false, 'error' => $result['error'] ?? 'Send failed'], 400);
    }
    rrp_json(['ok' => true, 'message' => $result['message']]);
}

rrp_json(['ok' => false, 'error' => 'Unknown action'], 400);
