<?php
declare(strict_types=1);

function rrp_owner_area(array $owner): array
{
    return [
        'lat' => isset($owner['home_lat']) ? (float) $owner['home_lat'] : (isset($owner['lat']) ? (float) $owner['lat'] : null),
        'lng' => isset($owner['home_lng']) ? (float) $owner['home_lng'] : (isset($owner['lng']) ? (float) $owner['lng'] : null),
        'radius_km' => isset($owner['home_radius_km']) ? (float) $owner['home_radius_km'] : 25.0,
        'label' => $owner['home_area_label'] ?? ($owner['city'] ?? null),
        'has_home' => !empty($owner['home_lat']) && !empty($owner['home_lng']),
    ];
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_save_owner_area(int $ownerId, float $lat, float $lng, float $radiusKm, ?string $label): array
{
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return ['ok' => false, 'error' => 'Invalid coordinates.'];
    }
    $radiusKm = max(5.0, min(50.0, $radiusKm));
    $label = $label !== null ? trim($label) : null;
    if ($label === '') {
        $label = null;
    }
    if ($label !== null && mb_strlen($label) > 160) {
        $label = mb_substr($label, 0, 160);
    }
    try {
        $stmt = rrp_db()->prepare(
            'UPDATE owners SET home_lat = ?, home_lng = ?, home_radius_km = ?, home_area_label = ? WHERE id = ?'
        );
        $stmt->execute([$lat, $lng, $radiusKm, $label, $ownerId]);
        return ['ok' => true];
    } catch (Throwable $e) {
        error_log('rrp_save_owner_area: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save area. Import sql/007_t03_t15_deltas.sql if columns are missing.'];
    }
}
