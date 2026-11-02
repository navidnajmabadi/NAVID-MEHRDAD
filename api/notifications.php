<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

/** GET: the latest notifications for the bell. POST act=read_all: mark them all read. */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    [$owner, $data] = rrp_api_owner_post();
    if (($data['act'] ?? '') === 'read_all') {
        rrp_mark_notifications_read((int) $owner['id']);
    }
    rrp_json(['ok' => true, 'unread' => rrp_notifications_unread_count((int) $owner['id'])]);
}

$owner = rrp_api_owner();
$rows = array_values(array_filter(rrp_notifications_for_owner((int) $owner['id'], 30), static fn($n) => ($n['type'] ?? '') !== 'scan'));
$items = array_map(static function (array $n): array {
    $type = (string) ($n['type'] ?? '');
    if (!empty($n['link'])) {
        $href = (string) $n['link'];
    } elseif (!empty($n['lost_alert_id'])) {
        $href = 'lost-alert.php?id=' . (int) $n['lost_alert_id'];
    } elseif ($type === 'health') {
        $href = 'health-care.php';
    } elseif ($type === 'chat' || $type === 'message') {
        $href = preg_match('/chat\.php\?thread=(\d+)/', (string) $n['body'], $m) ? 'chat.php?thread=' . (int) $m[1] : 'chat.php';
    } else {
        $href = 'si-chat.php';
    }
    return [
        'id' => (int) $n['id'],
        'type' => $type,
        'title' => (string) $n['title'],
        'body' => rrp_clip(trim(preg_replace('/\s+/', ' ', (string) $n['body']) ?? ''), 120),
        'href' => $href,
        'at' => gmdate('c', (int) strtotime((string) $n['created_at'] . ' UTC')),
        'read' => $n['read_at'] !== null,
    ];
}, array_slice($rows, 0, 20));
rrp_json(['ok' => true, 'unread' => rrp_notifications_unread_count((int) $owner['id']), 'items' => $items, 'push' => ['configured' => rrp_vapid_configured()]]);
