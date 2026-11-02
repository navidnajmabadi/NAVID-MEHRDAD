<?php
declare(strict_types=1);

/**
 * Owner notifications: in-app inbox + email. Web Push when VAPID is configured.
 */
function rrp_notify_scan(array $pet, array $owner, int $scanId, string $mode = 'none'): void
{
    $petName = $pet['name'];
    $dashboard = rrp_app_url('dashboard.php');
    $title = "Someone scanned {$petName}'s tag";
    $body = "A finder opened {$petName}'s siPaw page.";
    if ($mode === 'one-time') {
        $body .= ' They shared a one-time location — open your dashboard for the map.';
    } elseif ($mode === 'live') {
        $body .= ' They are sharing live location — open your dashboard for the map.';
    } else {
        $body .= ' Ask them to share location if they can.';
    }
    $body .= "\n\nDashboard: {$dashboard}";

    $stmt = rrp_db()->prepare(
        'INSERT INTO notifications (owner_id, scan_id, type, title, body) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([(int) $owner['id'], $scanId, 'scan', $title, $body]);

    rrp_send_mail((string) $owner['email'], $title, $body);
    rrp_try_web_push(
        (int) $owner['id'],
        $title,
        "Open dashboard to see details for {$petName}.",
        rrp_app_url('dashboard.php')
    );
    rrp_mark_scan_notified($scanId);
}

/** True while the owner is talking to SIPO right now (a message from them in the last 3 minutes). */
function rrp_owner_in_chat(int $ownerId): bool
{
    try {
        $q = rrp_db()->prepare(
            "SELECT 1 FROM chat_messages m JOIN chat_threads t ON t.id = m.thread_id
             WHERE t.owner_id = ? AND t.thread_kind = 'si' AND m.sender_role = 'user' AND m.created_at > (UTC_TIMESTAMP() - INTERVAL 3 MINUTE) LIMIT 1"
        );
        $q->execute([$ownerId]);
        return (bool) $q->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * SIPO's own messages: always in the chat and the bell; the phone push is skipped while the owner
 * is already in the chat reading them. Reminders the owner set themselves always push.
 */
function rrp_sipo_push(int $ownerId, string $title, string $body, ?string $url = null): void
{
    if (rrp_owner_in_chat($ownerId)) {
        rrp_notify_record($ownerId, $title, function_exists('rrp_sipo_clean_text') ? rrp_sipo_clean_text($body) : $body, $url);
        return;
    }
    rrp_try_web_push($ownerId, $title, $body, $url);
}

/** What kind of notification a link points at (drives the icon and the filter). */
function rrp_notify_type_for_url(?string $url): string
{
    $u = (string) $url;
    return match (true) {
        str_contains($u, 'lost') => 'lost',
        str_contains($u, 'health') || str_contains($u, 'care.php') || str_contains($u, 'routines') => 'health',
        str_contains($u, 'si-chat') => 'sipo',
        str_contains($u, 'chat.php') => 'chat',
        str_contains($u, 'plus') || str_contains($u, 'rewards') => 'account',
        default => 'other',
    };
}

/**
 * Every notification that goes out as a push also lands in the in-app list (the bell), once:
 * the same title and text within two minutes is not written twice (Lost mode writes its own row).
 */
function rrp_notify_record(int $ownerId, string $title, string $body, ?string $url = null): void
{
    try {
        $dup = rrp_db()->prepare('SELECT 1 FROM notifications WHERE owner_id = ? AND title = ? AND LEFT(body, 190) = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 2 MINUTE) LIMIT 1');
        $dup->execute([$ownerId, mb_substr($title, 0, 200), mb_substr($body, 0, 190)]);
        if ($dup->fetchColumn()) {
            return;
        }
        $path = $url ? (string) (parse_url($url, PHP_URL_PATH) ?: '') : '';
        $q = $url ? (string) parse_url($url, PHP_URL_QUERY) : '';
        $link = $path !== '' ? ltrim(substr($path, (int) strrpos($path, '/')), '/') . ($q !== '' ? '?' . $q : '') : null;
        $type = rrp_notify_type_for_url($url);
        try {
            rrp_db()->prepare("INSERT INTO notifications (owner_id, type, title, body, channel, link, created_at) VALUES (?, ?, ?, ?, 'push', ?, UTC_TIMESTAMP())")
                ->execute([$ownerId, $type, mb_substr($title, 0, 200), $body, $link ? mb_substr($link, 0, 255) : null]);
        } catch (Throwable $e) {
            // Deployed before migration 023: still keep the row, just without the link.
            rrp_db()->prepare("INSERT INTO notifications (owner_id, type, title, body, channel, created_at) VALUES (?, ?, ?, ?, 'push', UTC_TIMESTAMP())")
                ->execute([$ownerId, $type, mb_substr($title, 0, 200), $body]);
        }
    } catch (Throwable $e) {
        error_log('rrp_notify_record: ' . $e->getMessage());
    }
}

/** Types the owner never sees while their feature is switched off (the Pack board is hidden, so its removals and comments are too). */
function rrp_notify_hidden_sql(): string
{
    return rrp_social_enabled() ? '' : " AND type NOT IN ('moderation','forum_comment')";
}

function rrp_notifications_for_owner(int $ownerId, int $limit = 30): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM notifications WHERE owner_id = ?' . rrp_notify_hidden_sql() . ' ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $ownerId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_notifications_unread_count(int $ownerId): int
{
    $stmt = rrp_db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE owner_id = ? AND read_at IS NULL' . rrp_notify_hidden_sql()
    );
    $stmt->execute([$ownerId]);
    return (int) $stmt->fetchColumn();
}

/** Remove the notifications the owner has already read (their own list only). */
function rrp_clear_read_notifications(int $ownerId): int
{
    $stmt = rrp_db()->prepare('DELETE FROM notifications WHERE owner_id = ? AND read_at IS NOT NULL');
    $stmt->execute([$ownerId]);
    return $stmt->rowCount();
}

function rrp_mark_notifications_read(int $ownerId): void
{
    $stmt = rrp_db()->prepare(
        'UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE owner_id = ? AND read_at IS NULL'
    );
    $stmt->execute([$ownerId]);
}

function rrp_save_push_subscription(
    int $ownerId,
    string $endpoint,
    string $p256dh,
    string $auth,
    ?string $userAgent = null
): void {
    $hash = hash('sha256', $endpoint);
    $ua = $userAgent !== null ? substr($userAgent, 0, 512) : null;

    // Prefer new columns from sql/003; fall back if migration not applied yet.
    try {
        $del = rrp_db()->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ? OR (owner_id = ? AND endpoint = ?)');
        $del->execute([$hash, $ownerId, $endpoint]);
        $ins = rrp_db()->prepare(
            'INSERT INTO push_subscriptions (owner_id, endpoint, endpoint_hash, p256dh, auth, user_agent, fail_count)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        $ins->execute([$ownerId, $endpoint, $hash, $p256dh, $auth, $ua]);
    } catch (Throwable $e) {
        error_log('rrp_save_push_subscription migrate-fallback: ' . $e->getMessage());
        $del = rrp_db()->prepare('DELETE FROM push_subscriptions WHERE owner_id = ? AND endpoint = ?');
        $del->execute([$ownerId, $endpoint]);
        $ins = rrp_db()->prepare(
            'INSERT INTO push_subscriptions (owner_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$ownerId, $endpoint, $p256dh, $auth]);
    }
}

function rrp_delete_push_subscription_by_endpoint(string $endpoint): void
{
    $hash = hash('sha256', $endpoint);
    try {
        $stmt = rrp_db()->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ? OR endpoint = ?');
        $stmt->execute([$hash, $endpoint]);
    } catch (Throwable $e) {
        $stmt = rrp_db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
        $stmt->execute([$endpoint]);
    }
}

function rrp_push_subscriptions_for_owner(int $ownerId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT id, owner_id, endpoint, p256dh, auth FROM push_subscriptions WHERE owner_id = ?'
    );
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

/**
 * Best-effort Web Push to all stored subscriptions for an owner.
 * Never throws — email + inbox remain the reliable path when VAPID is empty or crypto fails.
 */
function rrp_try_web_push(int $ownerId, string $title, string $body, ?string $url = null): void
{
    if (function_exists('rrp_sipo_clean_text')) {
        $body = rrp_sipo_clean_text($body);
    }
    rrp_notify_record($ownerId, $title, $body, $url);
    if (!rrp_vapid_configured()) {
        return;
    }

    $subs = rrp_push_subscriptions_for_owner($ownerId);
    if (!$subs) {
        return;
    }

    foreach ($subs as $sub) {
        try {
            $result = rrp_webpush_send($sub, $title, $body, $url);
            if (!empty($result['gone'])) {
                rrp_delete_push_subscription_by_endpoint((string) $sub['endpoint']);
                continue;
            }
            if (!empty($result['ok'])) {
                try {
                    $upd = rrp_db()->prepare(
                        'UPDATE push_subscriptions SET last_success_at = UTC_TIMESTAMP(), fail_count = 0 WHERE id = ?'
                    );
                    $upd->execute([(int) $sub['id']]);
                } catch (Throwable $e) {
                    // Column may be missing pre-003
                }
            } else {
                try {
                    $upd = rrp_db()->prepare(
                        'UPDATE push_subscriptions SET fail_count = LEAST(fail_count + 1, 255) WHERE id = ?'
                    );
                    $upd->execute([(int) $sub['id']]);
                } catch (Throwable $e) {
                    // ignore
                }
            }
        } catch (Throwable $e) {
            error_log('rrp_try_web_push: ' . $e->getMessage());
        }
    }
}
