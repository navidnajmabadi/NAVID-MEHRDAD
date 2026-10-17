<?php
declare(strict_types=1);

function rrp_record_scan(int $petId, string $mode = 'none', ?float $lat = null, ?float $lng = null, ?int $ttlSeconds = null): array
{
    $cfg = rrp_load_config();
    $ttl = $ttlSeconds ?? (int) ($cfg['live_ttl_seconds'] ?? 1800);
    $expiresAt = null;
    $active = 0;

    if ($mode === 'live') {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl);
        $active = 1;
    } elseif ($mode === 'one-time') {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);
    }

    $stmt = rrp_db()->prepare(
        'INSERT INTO scans (pet_id, finder_ip, finder_ua, mode, lat, lng, expires_at, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $petId,
        rrp_client_ip(),
        rrp_client_ua(),
        $mode,
        $lat,
        $lng,
        $expiresAt,
        $active,
    ]);

    $scanId = (int) rrp_db()->lastInsertId();
    return ['ok' => true, 'scan_id' => $scanId, 'expires_at' => $expiresAt];
}

function rrp_update_live_scan(int $scanId, float $lat, float $lng): bool
{
    rrp_expire_live_scans();
    $stmt = rrp_db()->prepare(
        'UPDATE scans SET lat = ?, lng = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND mode = "live" AND active = 1
           AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())'
    );
    $stmt->execute([$lat, $lng, $scanId]);
    return $stmt->rowCount() > 0;
}

function rrp_stop_live_scan(int $scanId): void
{
    $stmt = rrp_db()->prepare('UPDATE scans SET active = 0 WHERE id = ?');
    $stmt->execute([$scanId]);
}

function rrp_expire_live_scans(): void
{
    rrp_db()->exec(
        'UPDATE scans SET active = 0
         WHERE mode = "live" AND active = 1
           AND expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()'
    );
}

function rrp_mark_scan_notified(int $scanId): void
{
    $stmt = rrp_db()->prepare('UPDATE scans SET notified_owner = 1 WHERE id = ?');
    $stmt->execute([$scanId]);
}

function rrp_scans_for_owner(int $ownerId, int $limit = 50): array
{
    rrp_expire_live_scans();
    $stmt = rrp_db()->prepare(
        'SELECT s.*, p.name AS pet_name, p.public_id
         FROM scans s
         INNER JOIN pets p ON p.id = s.pet_id
         WHERE p.owner_id = ?
         ORDER BY s.scanned_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $ownerId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_live_scan_for_owner(int $ownerId): ?array
{
    rrp_expire_live_scans();
    $stmt = rrp_db()->prepare(
        'SELECT s.*, p.name AS pet_name, p.public_id
         FROM scans s
         INNER JOIN pets p ON p.id = s.pet_id
         WHERE p.owner_id = ? AND s.mode = "live" AND s.active = 1
           AND s.lat IS NOT NULL AND s.lng IS NOT NULL
         ORDER BY s.updated_at DESC
         LIMIT 1'
    );
    $stmt->execute([$ownerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rrp_scan_by_id(int $scanId): ?array
{
    $stmt = rrp_db()->prepare('SELECT * FROM scans WHERE id = ? LIMIT 1');
    $stmt->execute([$scanId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
