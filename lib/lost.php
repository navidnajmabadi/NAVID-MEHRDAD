<?php
declare(strict_types=1);

/**
 * LOST HELP ME — 25 km broadcast + escalation schedule.
 * Pings at t0, +1h, +3h, +8h (offsets in seconds), then stop.
 */

/** Escalation offsets from started_at (seconds). Index = ping_count after send. */
const RRP_LOST_PING_OFFSETS = [0, 3600, 10800, 28800];

function rrp_lost_radius_km(): float
{
    return (float) (rrp_load_config()['lost_radius_km'] ?? 25);
}

function rrp_lost_medical_snapshot(array $pet): ?string
{
    $parts = [];
    if (!empty($pet['medical_conditions'])) {
        $parts[] = 'Conditions: ' . $pet['medical_conditions'];
    }
    if (!empty($pet['medical_pills'])) {
        $parts[] = 'Meds: ' . $pet['medical_pills'];
    }
    if (!empty($pet['medical_vaccines'])) {
        $parts[] = 'Vaccines: ' . $pet['medical_vaccines'];
    }
    if (!empty($pet['medical_next_due'])) {
        $parts[] = 'Next due: ' . $pet['medical_next_due'];
    }
    return $parts ? implode("\n", $parts) : null;
}

function rrp_open_lost_for_pet(int $petId): ?array
{
    $stmt = rrp_db()->prepare(
        "SELECT * FROM lost_alerts WHERE pet_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$petId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rrp_lost_by_id(int $id): ?array
{
    $stmt = rrp_db()->prepare('SELECT * FROM lost_alerts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rrp_lost_for_owner(int $ownerId): array
{
    $stmt = rrp_db()->prepare(
        'SELECT * FROM lost_alerts WHERE owner_id = ? ORDER BY started_at DESC LIMIT 20'
    );
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

function rrp_lost_inbox_for_owner(int $ownerId, int $limit = 20): array
{
    $stmt = rrp_db()->prepare(
        "SELECT la.*, lar.distance_km
         FROM lost_alert_recipients lar
         JOIN lost_alerts la ON la.id = lar.lost_alert_id
         WHERE lar.recipient_owner_id = ? AND la.status = 'open'
         ORDER BY la.started_at DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $ownerId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * @return array{ok:bool, alert_id?:int, recipients?:int, error?:string}
 */
function rrp_trigger_lost(int $ownerId, int $petId): array
{
    $pet = rrp_pet_by_id($petId, $ownerId);
    if (!$pet) {
        return ['ok' => false, 'error' => 'Pet not found.'];
    }
    if (rrp_open_lost_for_pet($petId)) {
        return ['ok' => false, 'error' => 'An open LOST alert already exists for this pet. Mark Found first.'];
    }

    $stmt = rrp_db()->prepare('SELECT id, name, email, address, lat, lng, status FROM owners WHERE id = ? LIMIT 1');
    $stmt->execute([$ownerId]);
    $owner = $stmt->fetch();
    if (!$owner || $owner['status'] !== 'active') {
        return ['ok' => false, 'error' => 'Account not active.'];
    }

    $lat = $owner['lat'] !== null ? (float) $owner['lat'] : null;
    $lng = $owner['lng'] !== null ? (float) $owner['lng'] : null;

    if ($lat === null || $lng === null) {
        rrp_owner_apply_geocode($ownerId, (string) $owner['address']);
        $stmt->execute([$ownerId]);
        $owner = $stmt->fetch();
        $lat = $owner['lat'] !== null ? (float) $owner['lat'] : null;
        $lng = $owner['lng'] !== null ? (float) $owner['lng'] : null;
    }

    if ($lat === null || $lng === null) {
        return [
            'ok' => false,
            'error' => 'We could not map your registered address. Update your address on the Account page, then try again.',
        ];
    }

    $radius = rrp_lost_radius_km();
    $medical = rrp_lost_medical_snapshot($pet);
    $pdo = rrp_db();
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO lost_alerts (
                pet_id, owner_id, status, center_lat, center_lng, radius_km,
                pet_name, pet_type, image_path, identifying_marks, magic_word, medical_notes,
                started_at, ping_count, last_ping_at, next_ping_at
             ) VALUES (?, ?, \'open\', ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), 0, NULL, UTC_TIMESTAMP())'
        );
        $ins->execute([
            $petId,
            $ownerId,
            $lat,
            $lng,
            $radius,
            $pet['name'],
            rrp_pet_type_label($pet),
            $pet['image_path'],
            $pet['identifying_marks'],
            $pet['magic_word'],
            $medical,
        ]);
        $alertId = (int) $pdo->lastInsertId();

        $nearby = rrp_owners_within_km($lat, $lng, $radius, $ownerId);
        $recip = $pdo->prepare(
            'INSERT INTO lost_alert_recipients (lost_alert_id, recipient_owner_id, distance_km)
             VALUES (?, ?, ?)'
        );
        foreach ($nearby as $n) {
            $recip->execute([$alertId, $n['id'], $n['distance_km']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('rrp_trigger_lost: ' . $e->getMessage());
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not start LOST alert')];
    }

    $alert = rrp_lost_by_id($alertId);
    if ($alert) {
        rrp_lost_send_ping($alert, true);
    }

    return ['ok' => true, 'alert_id' => $alertId, 'recipients' => count($nearby)];
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_mark_lost_found(int $ownerId, int $alertId): array
{
    $alert = rrp_lost_by_id($alertId);
    if (!$alert || (int) $alert['owner_id'] !== $ownerId) {
        return ['ok' => false, 'error' => 'Alert not found.'];
    }
    if ($alert['status'] !== 'open') {
        return ['ok' => false, 'error' => 'This alert is already closed.'];
    }

    $stmt = rrp_db()->prepare(
        "UPDATE lost_alerts SET status = 'found', found_at = UTC_TIMESTAMP(), next_ping_at = NULL WHERE id = ?"
    );
    $stmt->execute([$alertId]);

    // Notify helpers that the search is over (good vibe)
    $title = 'Yoho rescued — ' . $alert['pet_name'] . ' is home!';
    $body = $alert['pet_name'] . ' was marked Found. Thank you for watching out. 🐾';
    $recip = rrp_db()->prepare(
        'SELECT o.id, o.email FROM lost_alert_recipients lar
         JOIN owners o ON o.id = lar.recipient_owner_id
         WHERE lar.lost_alert_id = ? AND o.status = \'active\''
    );
    $recip->execute([$alertId]);
    foreach ($recip->fetchAll() as $r) {
        rrp_notify_owner_inbox((int) $r['id'], 'lost_found', $title, $body, null, $alertId);
        rrp_send_mail((string) $r['email'], $title, $body . "\n\n" . rrp_app_url('dashboard.php'));
        rrp_try_web_push(
            (int) $r['id'],
            $title,
            $alert['pet_name'] . ' is safe.',
            rrp_app_url('dashboard.php')
        );
    }

    return ['ok' => true];
}

/**
 * Send one escalation wave to all recipients. Advances ping_count / next_ping_at.
 */
function rrp_lost_send_ping(array $alert, bool $isImmediate = false): void
{
    if (($alert['status'] ?? '') !== 'open') {
        return;
    }

    $alertId = (int) $alert['id'];
    $pingIndex = (int) $alert['ping_count']; // 0..3
    if ($pingIndex >= count(RRP_LOST_PING_OFFSETS)) {
        $stop = rrp_db()->prepare(
            "UPDATE lost_alerts SET status = 'expired', next_ping_at = NULL WHERE id = ? AND status = 'open'"
        );
        $stop->execute([$alertId]);
        return;
    }

    $waveLabel = match ($pingIndex) {
        0 => 'LOST HELP ME',
        1 => 'Still missing (1h)',
        2 => 'Still missing (3h)',
        default => 'Final alert (8h)',
    };

    $title = "{$waveLabel}: {$alert['pet_name']}";
    $viewUrl = rrp_app_url('lost-alert.php?id=' . $alertId);
    $bodyParts = [
        "A nearby siPaw owner needs help finding {$alert['pet_name']} ({$alert['pet_type']}).",
        '',
        'Distinctive marks: ' . ($alert['identifying_marks'] ?: '—'),
        'Magic word: ' . ($alert['magic_word'] ?: '—'),
    ];
    if (!empty($alert['medical_notes'])) {
        $bodyParts[] = 'Medical: ' . $alert['medical_notes'];
    }
    $bodyParts[] = '';
    $bodyParts[] = 'Open alert: ' . $viewUrl;
    $body = implode("\n", $bodyParts);

    $recip = rrp_db()->prepare(
        'SELECT lar.id AS rid, o.id, o.email, o.name
         FROM lost_alert_recipients lar
         JOIN owners o ON o.id = lar.recipient_owner_id
         WHERE lar.lost_alert_id = ? AND o.status = \'active\''
    );
    $recip->execute([$alertId]);
    $rows = $recip->fetchAll();

    foreach ($rows as $r) {
        rrp_notify_owner_inbox((int) $r['id'], 'lost_help', $title, $body, null, $alertId);
        rrp_send_mail((string) $r['email'], $title, $body);
        rrp_try_web_push(
            (int) $r['id'],
            $title,
            "Help find {$alert['pet_name']} nearby — open the alert.",
            $viewUrl
        );
        $updR = rrp_db()->prepare(
            'UPDATE lost_alert_recipients SET
                notified_at = COALESCE(notified_at, UTC_TIMESTAMP()),
                last_ping_at = UTC_TIMESTAMP(),
                ping_count = ping_count + 1
             WHERE id = ?'
        );
        $updR->execute([(int) $r['rid']]);
    }

    $nextIndex = $pingIndex + 1;
    if ($nextIndex >= count(RRP_LOST_PING_OFFSETS)) {
        $upd = rrp_db()->prepare(
            'UPDATE lost_alerts SET
                ping_count = ?,
                last_ping_at = UTC_TIMESTAMP(),
                next_ping_at = NULL
             WHERE id = ?'
        );
        $upd->execute([$nextIndex, $alertId]);
    } else {
        $offset = RRP_LOST_PING_OFFSETS[$nextIndex];
        $upd = rrp_db()->prepare(
            'UPDATE lost_alerts SET
                ping_count = ?,
                last_ping_at = UTC_TIMESTAMP(),
                next_ping_at = DATE_ADD(started_at, INTERVAL ? SECOND)
             WHERE id = ?'
        );
        $upd->execute([$nextIndex, $offset, $alertId]);
    }

    unset($isImmediate);
}

/**
 * Cron worker: send due escalation pings; expire when schedule complete.
 * @return array{processed:int, pinged:int, expired:int}
 */
function rrp_lost_cron_tick(int $limit = 50): array
{
    $stats = ['processed' => 0, 'pinged' => 0, 'expired' => 0];

    // Expire open alerts that finished all pings and next_ping is null past grace
    $expire = rrp_db()->prepare(
        "UPDATE lost_alerts SET status = 'expired'
         WHERE status = 'open' AND next_ping_at IS NULL AND ping_count >= ?"
    );
    $expire->execute([count(RRP_LOST_PING_OFFSETS)]);
    $stats['expired'] = $expire->rowCount();

    $stmt = rrp_db()->prepare(
        "SELECT * FROM lost_alerts
         WHERE status = 'open'
           AND next_ping_at IS NOT NULL
           AND next_ping_at <= UTC_TIMESTAMP()
         ORDER BY next_ping_at ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $due = $stmt->fetchAll();

    foreach ($due as $alert) {
        $stats['processed']++;
        rrp_lost_send_ping($alert);
        $stats['pinged']++;
    }

    return $stats;
}

function rrp_notify_owner_inbox(
    int $ownerId,
    string $type,
    string $title,
    string $body,
    ?int $scanId = null,
    ?int $lostAlertId = null
): void {
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO notifications (owner_id, scan_id, lost_alert_id, type, title, body)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ownerId, $scanId, $lostAlertId, $type, $title, $body]);
    } catch (Throwable $e) {
        // Fallback if lost_alert_id column missing (pre-migration)
        error_log('rrp_notify_owner_inbox: ' . $e->getMessage());
        $stmt = rrp_db()->prepare(
            'INSERT INTO notifications (owner_id, scan_id, type, title, body) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ownerId, $scanId, $type, $title, $body]);
    }
}
