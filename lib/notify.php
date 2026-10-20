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

function rrp_notifications_for_owner(int $ownerId, int $limit = 30): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM notifications WHERE owner_id = ? ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $ownerId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_notifications_unread_count(int $ownerId): int
{
    $stmt = rrp_db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE owner_id = ? AND read_at IS NULL'
    );
    $stmt->execute([$ownerId]);
    return (int) $stmt->fetchColumn();
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
