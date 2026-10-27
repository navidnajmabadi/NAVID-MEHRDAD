<?php
declare(strict_types=1);

/**
 * Live context for SIPO: owner's local clock, day part, area, weather.
 * Weather: Open-Meteo (free, no key), cached 30 min per ~1 km cell.
 */

const RRP_SIPO_DEFAULT_TZ = 'America/Toronto';
const RRP_SIPO_QUIET_START = 21; // 21:00 local
const RRP_SIPO_QUIET_END = 9;    // 09:00 local

function rrp_sipo_owner_tz(array $owner): DateTimeZone
{
    $tz = (string) ($owner['timezone'] ?? '');
    try {
        return new DateTimeZone($tz !== '' ? $tz : RRP_SIPO_DEFAULT_TZ);
    } catch (Throwable $e) {
        return new DateTimeZone(RRP_SIPO_DEFAULT_TZ);
    }
}

function rrp_sipo_local_now(array $owner): DateTimeImmutable
{
    return new DateTimeImmutable('now', rrp_sipo_owner_tz($owner));
}

function rrp_sipo_day_part(int $hour): string
{
    return match (true) {
        $hour >= 5 && $hour < 12 => 'morning',
        $hour >= 12 && $hour < 17 => 'afternoon',
        $hour >= 17 && $hour < 21 => 'evening',
        default => 'night',
    };
}

function rrp_sipo_is_quiet_hour(int $hour): bool
{
    return $hour >= RRP_SIPO_QUIET_START || $hour < RRP_SIPO_QUIET_END;
}

/** @return array{lat:?float,lng:?float} */
function rrp_sipo_owner_coords(array $owner): array
{
    $area = rrp_owner_area($owner);
    return ['lat' => $area['lat'] ?: null, 'lng' => $area['lng'] ?: null];
}

/**
 * @return array{local_time:string, hour:int, day_part:string, quiet:bool, area:?string, weather:?string, weather_raw:?array}
 */
function rrp_sipo_live_context(array $owner, bool $withWeather = true): array
{
    $now = rrp_sipo_local_now($owner);
    $hour = (int) $now->format('G');
    $area = rrp_owner_area($owner);
    $label = $area['label'] ?? null;
    if (!$label && !empty($owner['city'])) {
        $label = trim($owner['city'] . (!empty($owner['country']) ? ', ' . $owner['country'] : ''));
    }
    $weather = null;
    if ($withWeather) {
        $c = rrp_sipo_owner_coords($owner);
        if ($c['lat'] !== null && $c['lng'] !== null) {
            $weather = rrp_sipo_weather($c['lat'], $c['lng']);
        }
    }
    return [
        'local_time' => $now->format('l j M Y, H:i') . ' ' . $now->getTimezone()->getName(),
        'hour' => $hour,
        'day_part' => rrp_sipo_day_part($hour),
        'quiet' => rrp_sipo_is_quiet_hour($hour),
        'area' => $label ?: null,
        'weather' => $weather['text'] ?? null,
        'weather_raw' => $weather,
    ];
}

/** @return array{text:string,temp_c:float,code:int,precip_mm:float,wind_kmh:float}|null */
function rrp_sipo_weather(float $lat, float $lng): ?array
{
    $key = sprintf('%.2f,%.2f', $lat, $lng);
    $cached = rrp_sipo_cache_get('wx:' . $key, 1800);
    if (is_array($cached)) {
        return $cached;
    }
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => round($lat, 2),
        'longitude' => round($lng, 2),
        'current' => 'temperature_2m,precipitation,weather_code,wind_speed_10m',
        'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max',
        'forecast_days' => 1,
        'timezone' => 'auto',
    ]);
    $data = rrp_sipo_http_json($url, 6);
    if (!is_array($data) || empty($data['current'])) {
        return null;
    }
    $cur = $data['current'];
    $code = (int) ($cur['weather_code'] ?? 0);
    $temp = (float) ($cur['temperature_2m'] ?? 0);
    $out = [
        'temp_c' => $temp,
        'code' => $code,
        'precip_mm' => (float) ($cur['precipitation'] ?? 0),
        'wind_kmh' => (float) ($cur['wind_speed_10m'] ?? 0),
        'max_c' => (float) ($data['daily']['temperature_2m_max'][0] ?? $temp),
        'min_c' => (float) ($data['daily']['temperature_2m_min'][0] ?? $temp),
        'rain_chance' => (int) ($data['daily']['precipitation_probability_max'][0] ?? 0),
    ];
    $out['text'] = sprintf(
        '%s, %d°C (today %d–%d°C, %d%% chance of rain/snow, wind %d km/h)',
        rrp_sipo_weather_label($code),
        (int) round($temp),
        (int) round($out['min_c']),
        (int) round($out['max_c']),
        $out['rain_chance'],
        (int) round($out['wind_kmh'])
    );
    rrp_sipo_cache_set('wx:' . $key, $out);
    return $out;
}

function rrp_sipo_weather_label(int $code): string
{
    return match (true) {
        $code === 0 => 'clear',
        $code <= 3 => 'partly cloudy',
        $code <= 48 => 'foggy',
        $code <= 57 => 'drizzle',
        $code <= 67 => 'rain',
        $code <= 77 => 'snow',
        $code <= 82 => 'rain showers',
        $code <= 86 => 'snow showers',
        default => 'thunderstorm',
    };
}

function rrp_sipo_http_json(string $url, int $timeout = 8, ?string $postBody = null): ?array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'SIPAW/1 (https://sipaw.nakilab.com; noreply@nakilab.com)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($postBody !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $postBody;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false || $status >= 400) {
        return null;
    }
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : null;
}

// Tiny file cache (shared hosting: no Redis/APCu guarantee)
function rrp_sipo_cache_path(string $key): string
{
    $dir = sys_get_temp_dir() . '/sipaw-cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir . '/' . hash('sha256', $key) . '.json';
}

function rrp_sipo_cache_get(string $key, int $ttl): mixed
{
    $p = rrp_sipo_cache_path($key);
    if (!is_readable($p) || (time() - (int) @filemtime($p)) > $ttl) {
        return null;
    }
    $data = json_decode((string) @file_get_contents($p), true);
    return $data ?? null;
}

function rrp_sipo_cache_set(string $key, mixed $value): void
{
    @file_put_contents(rrp_sipo_cache_path($key), json_encode($value), LOCK_EX);
}

/** Count one owner visit in their local hour (feeds proactive timing). */
function rrp_sipo_record_activity(array $owner): void
{
    $hour = (int) rrp_sipo_local_now($owner)->format('G');
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO owner_activity_hours (owner_id, local_hour, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        );
        $stmt->execute([(int) $owner['id'], $hour]);
    } catch (Throwable $e) {
    }
}
