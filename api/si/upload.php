<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** POST multipart: csrf, thread_id, file → SIPO reads it, saves the data, replies. */

// Reading a multi-page lab PDF can take a minute or more. The host's default 30 s limit killed the
// request with a bare "error 500" (live bug: Probavet lab PDF), so allow time and always answer in JSON.
@set_time_limit(240);
@ini_set('memory_limit', '256M');
ignore_user_abort(true);

$uploadFail = static function (string $why): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => $why], JSON_UNESCAPED_UNICODE);
};
register_shutdown_function(static function () use ($uploadFail): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $slow = stripos((string) $err['message'], 'Maximum execution time') !== false;
        $mem = stripos((string) $err['message'], 'memory') !== false;
        $uploadFail($slow ? 'Reading this file took too long. Try sending one page at a time as photos, or a smaller PDF.'
            : ($mem ? 'This file is too large for me to read at once. Try fewer pages or a photo.'
                : 'I could not read this file because of a problem on my side. It has been logged; please try again in a moment.'));
    }
});

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

try {
    $res = rrp_care_handle_upload($owner, $thread, $_FILES['file']);
} catch (Throwable $e) {
    rrp_ops_record_error('exception', get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
    error_log('upload: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $why = 'I could not read this file because of a problem on my side. It has been logged; please try again in a moment.';
    if (rrp_is_super_owner($owner)) {
        $why .= ' [admin: ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 160) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
    }
    rrp_json(['ok' => false, 'error' => $why], 500);
}
if (empty($res['ok'])) {
    rrp_json($res, 400);
}
if (!empty($data['quiet'])) {
    rrp_json(['ok' => true, 'body' => $res['body'], 'kind' => $res['kind']]);
}
// The extracted summary stands in for the file in the chat, so SIPO remembers it.
$reply = rrp_sipo_send($owner, $thread, mb_substr($res['body'], 0, 3800));
rrp_json($reply + ['upload' => ['kind' => $res['kind']]]);
