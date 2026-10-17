<?php
declare(strict_types=1);

/**
 * Owner notifications: in-app inbox + email. Web Push is best-effort when VAPID is set.
 */
function rrp_notify_scan(array $pet, array $owner, int $scanId, string $mode = 'none'): void
{
    $petName = $pet['name'];
    $dashboard = rrp_app_url('dashboard.php');
    $title = "Someone scanned {$petName}'s tag";
    $body = "A finder opened {$petName}'s Ring Ring Paw page.";
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
    rrp_try_web_push((int) $owner['id'], $title, "Open dashboard to see details for {$petName}.");
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

function rrp_mark_notifications_read(int $ownerId): void
{
    $stmt = rrp_db()->prepare(
        'UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE owner_id = ? AND read_at IS NULL'
    );
    $stmt->execute([$ownerId]);
}

function rrp_save_push_subscription(int $ownerId, string $endpoint, string $p256dh, string $auth): void
{
    $del = rrp_db()->prepare('DELETE FROM push_subscriptions WHERE owner_id = ? AND endpoint = ?');
    $del->execute([$ownerId, $endpoint]);
    $ins = rrp_db()->prepare(
        'INSERT INTO push_subscriptions (owner_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$ownerId, $endpoint, $p256dh, $auth]);
}

/**
 * Minimal Web Push attempt without Composer.
 * Requires openssl + curl. If VAPID keys are empty or crypto fails, silently skip
 * (email + inbox remain the reliable Hostinger path).
 */
function rrp_try_web_push(int $ownerId, string $title, string $body): void
{
    $vapid = rrp_load_config()['vapid'] ?? [];
    if (empty($vapid['public_key']) || empty($vapid['private_key'])) {
        return;
    }
    // Full Web Push encryption needs a library (e.minishlink/web-push).
    // Documented fallback: email + in-app inbox. Stub hook for future Composer install.
    // We store subscriptions so Phase 1.1 can send once a push lib is available.
    unset($ownerId, $title, $body);
}
