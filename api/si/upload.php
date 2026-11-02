<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** POST multipart: csrf, thread_id, file → SIPO reads it, saves the data, replies. */
[$owner, $data] = rrp_api_owner_post();

$thread = rrp_sipo_thread_by_id((int) ($data['thread_id'] ?? 0), (int) $owner['id']);
if (!$thread) {
    rrp_json(['ok' => false, 'error' => 'Chat not found'], 404);
}
$gate = rrp_sipo_gate($owner);
if (empty($gate['ok'])) {
    rrp_json($gate, 429);
}

// Several files at once: each file is read with quiet=1 (saved, no chat reply), then one final
// call with finish=1 posts a single combined summary so SIPO answers once, not per file.
if (!empty($data['finish'])) {
    $prior = array_values(array_filter(array_map('strval', (array) ($data['prior'] ?? []))));
    if (!$prior) {
        rrp_json(['ok' => false, 'error' => 'None of the files could be read.'], 400);
    }
    $body = '📎 ' . count($prior) . " files read:\n" . implode("\n\n", $prior);
    $reply = rrp_sipo_send($owner, $thread, mb_substr($body, 0, 3800));
    rrp_json($reply + ['upload' => ['kind' => 'batch']]);
}
if (empty($_FILES['file'])) {
    rrp_json(['ok' => false, 'error' => 'No file received (max 10 MB).'], 400);
}

$res = rrp_care_handle_upload($owner, $thread, $_FILES['file']);
if (empty($res['ok'])) {
    rrp_json($res, 400);
}
if (!empty($data['quiet'])) {
    rrp_json(['ok' => true, 'body' => $res['body'], 'kind' => $res['kind']]);
}
// The extracted summary stands in for the file in the chat, so SIPO remembers it.
$reply = rrp_sipo_send($owner, $thread, mb_substr($res['body'], 0, 3800));
rrp_json($reply + ['upload' => ['kind' => $res['kind']]]);
