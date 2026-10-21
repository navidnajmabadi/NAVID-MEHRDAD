<?php
declare(strict_types=1);

/** Cookie name for anonymous finder chat sessions. */
const RRP_FINDER_COOKIE = 'rrp_finder';

/**
 * Stable guest token for finder chat (cookie + session fallback).
 */
function rrp_finder_token(): string
{
    rrp_start_session();
    $cookie = isset($_COOKIE[RRP_FINDER_COOKIE]) ? (string) $_COOKIE[RRP_FINDER_COOKIE] : '';
    if (preg_match('/^[a-f0-9]{64}$/', $cookie)) {
        $_SESSION['finder_token'] = $cookie;
        return $cookie;
    }
    if (!empty($_SESSION['finder_token']) && preg_match('/^[a-f0-9]{64}$/', (string) $_SESSION['finder_token'])) {
        $token = (string) $_SESSION['finder_token'];
        rrp_set_finder_cookie($token);
        return $token;
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['finder_token'] = $token;
    rrp_set_finder_cookie($token);
    return $token;
}

function rrp_set_finder_cookie(string $token): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(RRP_FINDER_COOKIE, $token, [
        'expires' => time() + 60 * 60 * 24 * 90,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[RRP_FINDER_COOKIE] = $token;
}

function rrp_chat_sanitize_body(string $body): string
{
    $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
    if (mb_strlen($body) > 2000) {
        $body = mb_substr($body, 0, 2000);
    }
    return $body;
}

function rrp_chat_thread_by_id(int $threadId): ?array
{
    $stmt = rrp_db()->prepare(
        'SELECT t.*, p.name AS pet_name, p.public_id AS pet_public_id, p.image_path AS pet_image
         FROM chat_threads t
         INNER JOIN pets p ON p.id = t.pet_id
         WHERE t.id = ?
         LIMIT 1'
    );
    $stmt->execute([$threadId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rrp_chat_thread_for_scan(int $scanId): ?array
{
    $stmt = rrp_db()->prepare('SELECT * FROM chat_threads WHERE scan_id = ? LIMIT 1');
    $stmt->execute([$scanId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Owner inbox: real threads only (no demos).
 * @return list<array>
 */
function rrp_chat_threads_for_owner(int $ownerId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT t.*, p.name AS pet_name, p.image_path AS pet_image,
                (SELECT body FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                (SELECT sender_role FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_role
         FROM chat_threads t
         INNER JOIN pets p ON p.id = t.pet_id
         WHERE t.owner_id = ?
         ORDER BY COALESCE(t.last_message_at, t.created_at) DESC, t.id DESC'
    );
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array>
 */
function rrp_chat_messages(int $threadId, int $afterId = 0, int $limit = 200): array
{
    $stmt = rrp_db()->prepare(
        'SELECT id, thread_id, sender_role, body, created_at
         FROM chat_messages
         WHERE thread_id = ? AND id > ?
         ORDER BY id ASC
         LIMIT ?'
    );
    $stmt->bindValue(1, $threadId, PDO::PARAM_INT);
    $stmt->bindValue(2, $afterId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Start or resume a thread for a scan (finder side).
 * @return array{ok:bool,thread?:array,error?:string,created?:bool}
 */
function rrp_chat_start_for_scan(int $scanId, ?string $finderName = null): array
{
    $scan = rrp_scan_by_id($scanId);
    if (!$scan) {
        return ['ok' => false, 'error' => 'Scan not found.'];
    }
    $pet = rrp_pet_by_id((int) $scan['pet_id']);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    $ownerStmt = rrp_db()->prepare('SELECT id, name, email, status FROM owners WHERE id = ? LIMIT 1');
    $ownerStmt->execute([(int) $pet['owner_id']]);
    $owner = $ownerStmt->fetch();
    if (!$owner || $owner['status'] !== 'active') {
        return ['ok' => false, 'error' => 'Owner unavailable.'];
    }

    $existing = rrp_chat_thread_for_scan($scanId);
    $token = rrp_finder_token();
    $name = $finderName !== null ? trim($finderName) : null;
    if ($name === '') {
        $name = null;
    }
    if ($name !== null) {
        $name = mb_substr($name, 0, 80);
    }

    if ($existing) {
        // Only the same finder guest (or owner later) may use this thread.
        if (!hash_equals((string) $existing['finder_token'], $token)) {
            return ['ok' => false, 'error' => 'This chat belongs to another finder session.'];
        }
        if ($name !== null && $name !== (string) ($existing['finder_name'] ?? '')) {
            $upd = rrp_db()->prepare('UPDATE chat_threads SET finder_name = ? WHERE id = ?');
            $upd->execute([$name, (int) $existing['id']]);
            $existing['finder_name'] = $name;
        }
        return ['ok' => true, 'thread' => $existing, 'created' => false];
    }

    $ins = rrp_db()->prepare(
        'INSERT INTO chat_threads (pet_id, owner_id, scan_id, finder_token, finder_name)
         VALUES (?, ?, ?, ?, ?)'
    );
    try {
        $ins->execute([
            (int) $pet['id'],
            (int) $owner['id'],
            $scanId,
            $token,
            $name,
        ]);
    } catch (PDOException $e) {
        // Race: another request created the thread for this scan.
        $again = rrp_chat_thread_for_scan($scanId);
        if ($again && hash_equals((string) $again['finder_token'], $token)) {
            return ['ok' => true, 'thread' => $again, 'created' => false];
        }
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not start chat')];
    }

    $thread = rrp_chat_thread_by_id((int) rrp_db()->lastInsertId());
    return ['ok' => true, 'thread' => $thread ?: [], 'created' => true];
}

/**
 * @return array{ok:bool,message?:array,error?:string}
 */
function rrp_chat_send(int $threadId, string $role, string $body, ?string $finderToken = null, ?int $ownerId = null): array
{
    if (!in_array($role, ['owner', 'finder'], true)) {
        return ['ok' => false, 'error' => 'Invalid sender.'];
    }
    $body = rrp_chat_sanitize_body($body);
    if ($body === '') {
        return ['ok' => false, 'error' => 'Message is empty.'];
    }

    $thread = rrp_chat_thread_by_id($threadId);
    if (!$thread) {
        return ['ok' => false, 'error' => 'Thread not found.'];
    }

    if ($role === 'owner') {
        if ($ownerId === null || (int) $thread['owner_id'] !== $ownerId) {
            return ['ok' => false, 'error' => 'Not allowed.'];
        }
    } else {
        $token = $finderToken ?? rrp_finder_token();
        if (!hash_equals((string) $thread['finder_token'], $token)) {
            return ['ok' => false, 'error' => 'Not allowed.'];
        }
    }

    $ins = rrp_db()->prepare(
        'INSERT INTO chat_messages (thread_id, sender_role, body) VALUES (?, ?, ?)'
    );
    $ins->execute([$threadId, $role, $body]);
    $msgId = (int) rrp_db()->lastInsertId();

    $touch = rrp_db()->prepare(
        'UPDATE chat_threads SET last_message_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $touch->execute([$threadId]);

    if ($role === 'finder') {
        rrp_chat_notify_owner($thread, $body);
    }

    $msg = [
        'id' => $msgId,
        'thread_id' => $threadId,
        'sender_role' => $role,
        'body' => $body,
        'created_at' => gmdate('Y-m-d H:i:s'),
    ];
    return ['ok' => true, 'message' => $msg];
}

function rrp_chat_notify_owner(array $thread, string $bodyPreview): void
{
    $petName = (string) ($thread['pet_name'] ?? 'your pet');
    $finder = trim((string) ($thread['finder_name'] ?? ''));
    $who = $finder !== '' ? $finder : 'A finder';
    $title = "{$who} messaged about {$petName}";
    $clip = rrp_clip($bodyPreview, 120);
    $url = rrp_app_url('chat.php?thread=' . (int) $thread['id']);
    $body = "{$clip}\n\nOpen chat: {$url}";
    $ownerId = (int) $thread['owner_id'];
    $scanId = !empty($thread['scan_id']) ? (int) $thread['scan_id'] : null;

    rrp_notify_owner_inbox($ownerId, 'chat', $title, $body, $scanId, null);

    try {
        $st = rrp_db()->prepare('SELECT email FROM owners WHERE id = ? LIMIT 1');
        $st->execute([$ownerId]);
        $email = (string) ($st->fetchColumn() ?: '');
        if ($email !== '') {
            rrp_send_mail($email, $title, $body);
        }
    } catch (Throwable $e) {
        error_log('rrp_chat_notify_owner mail: ' . $e->getMessage());
    }

    rrp_try_web_push($ownerId, $title, $clip, $url);
}

/**
 * Can this owner open the thread?
 */
function rrp_chat_owner_can_access(array $thread, int $ownerId): bool
{
    return (int) $thread['owner_id'] === $ownerId;
}

function rrp_chat_finder_can_access(array $thread, ?string $token = null): bool
{
    $token = $token ?? rrp_finder_token();
    return hash_equals((string) $thread['finder_token'], $token);
}
