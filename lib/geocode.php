<?php
declare(strict_types=1);

/**
 * Address geocoding via OpenStreetMap Nominatim (free, Hostinger-friendly).
 * Results cached in geocode_cache; lat/lng also stored on owners.
 *
 * Usage policy: identify app with a real User-Agent + contact email.
 * Cache aggressively; do not hammer the public API.
 */

function rrp_geocode_normalize_address(string $address): string
{
    $address = preg_replace('/\s+/u', ' ', trim($address)) ?? '';
    return mb_strtolower($address);
}

function rrp_geocode_hash(string $address): string
{
    return hash('sha256', rrp_geocode_normalize_address($address));
}

/**
 * @return array{ok:bool, lat?:float, lng?:float, city?:?string, country?:?string, cached?:bool, error?:string}
 */
function rrp_geocode_address(string $address, bool $forceRefresh = false): array
{
    $address = trim($address);
    if ($address === '') {
        return ['ok' => false, 'error' => 'Address is empty.'];
    }

    $hash = rrp_geocode_hash($address);

    if (!$forceRefresh) {
        try {
            $stmt = rrp_db()->prepare('SELECT lat, lng, city, country FROM geocode_cache WHERE address_hash = ? LIMIT 1');
            $stmt->execute([$hash]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'ok' => true,
                    'lat' => (float) $row['lat'],
                    'lng' => (float) $row['lng'],
                    'city' => $row['city'],
                    'country' => $row['country'],
                    'cached' => true,
                ];
            }
        } catch (Throwable $e) {
            error_log('rrp_geocode cache read: ' . $e->getMessage());
        }
    }

    $cfg = rrp_load_config()['geocode'] ?? [];
    $ua = (string) ($cfg['user_agent'] ?? 'siPaw/1 (https://sipaw.nakilab.com; noreply@nakilab.com)');
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $address,
        'format' => 'json',
        'limit' => 1,
        'addressdetails' => 1,
    ]);

    $json = rrp_http_get_json($url, $ua);
    if ($json === null) {
        return ['ok' => false, 'error' => 'Geocoding request failed. Try again later.'];
    }
    if (!is_array($json) || $json === []) {
        return ['ok' => false, 'error' => 'Could not find that address on the map.'];
    }

    $hit = $json[0];
    $lat = isset($hit['lat']) ? (float) $hit['lat'] : null;
    $lng = isset($hit['lon']) ? (float) $hit['lon'] : null;
    if ($lat === null || $lng === null) {
        return ['ok' => false, 'error' => 'Geocoder returned an incomplete result.'];
    }

    $addr = is_array($hit['address'] ?? null) ? $hit['address'] : [];
    $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? null;
    $country = $addr['country'] ?? null;

    try {
        $upsert = rrp_db()->prepare(
            'INSERT INTO geocode_cache (address_hash, address_raw, lat, lng, city, country, provider)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               address_raw = VALUES(address_raw),
               lat = VALUES(lat),
               lng = VALUES(lng),
               city = VALUES(city),
               country = VALUES(country),
               updated_at = CURRENT_TIMESTAMP'
        );
        $upsert->execute([$hash, $address, $lat, $lng, $city, $country, 'nominatim']);
    } catch (Throwable $e) {
        error_log('rrp_geocode cache write: ' . $e->getMessage());
    }

    return [
        'ok' => true,
        'lat' => $lat,
        'lng' => $lng,
        'city' => $city,
        'country' => $country,
        'cached' => false,
    ];
}

/**
 * Persist geocode onto an owner row. Best-effort — never blocks signup hard.
 */
function rrp_owner_apply_geocode(int $ownerId, string $address): void
{
    $geo = rrp_geocode_address($address);
    if (empty($geo['ok'])) {
        return;
    }
    try {
        $stmt = rrp_db()->prepare(
            'UPDATE owners SET lat = ?, lng = ?, city = ?, country = ?, geocoded_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $stmt->execute([
            $geo['lat'],
            $geo['lng'],
            $geo['city'] ?? null,
            $geo['country'] ?? null,
            $ownerId,
        ]);
    } catch (Throwable $e) {
        error_log('rrp_owner_apply_geocode: ' . $e->getMessage());
    }
}

/**
 * Haversine distance in km.
 */
function rrp_haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

/**
 * @return list<array{id:int,name:string,email:string,lat:float,lng:float,distance_km:float}>
 */
function rrp_owners_within_km(float $lat, float $lng, float $radiusKm, int $excludeOwnerId): array
{
    // Bounding box prefilter (~1 deg lat ≈ 111 km)
    $delta = max(0.05, ($radiusKm / 111.0) * 1.2);
    $stmt = rrp_db()->prepare(
        'SELECT id, name, email, lat, lng FROM owners
         WHERE status = \'active\'
           AND id <> ?
           AND lat IS NOT NULL AND lng IS NOT NULL
           AND lat BETWEEN ? AND ?
           AND lng BETWEEN ? AND ?'
    );
    $stmt->execute([
        $excludeOwnerId,
        $lat - $delta,
        $lat + $delta,
        $lng - $delta,
        $lng + $delta,
    ]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $dist = rrp_haversine_km($lat, $lng, (float) $row['lat'], (float) $row['lng']);
        if ($dist <= $radiusKm) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'distance_km' => round($dist, 3),
            ];
        }
    }
    usort($out, static fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);
    return $out;
}

function rrp_http_get_json(string $url, string $userAgent): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $userAgent,
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false || $code >= 400) {
            error_log("rrp_http_get_json HTTP $code for geocode");
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nUser-Agent: {$userAgent}\r\n",
            'timeout' => 12,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}
