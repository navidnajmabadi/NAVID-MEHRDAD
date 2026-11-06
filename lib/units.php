<?php
declare(strict_types=1);

/**
 * Units follow the owner's country: United States (and Liberia, Myanmar) use pounds and miles, everyone
 * else kilograms and kilometres; Fahrenheit and mph where those countries use them. The database always
 * stores kilograms and kilometres; only what people see and type is converted.
 */

const RRP_KG_TO_LB = 2.20462262;
const RRP_KM_TO_MI = 0.621371192;

/**
 * @return array{imperial:bool, weight:string, distance:string, pace:string, temp:string, speed:string, country:string}
 */
function rrp_units(?array $owner = null): array
{
    static $cache = [];
    if ($owner === null && PHP_SAPI !== 'cli' && function_exists('rrp_current_owner')) {
        try {
            $owner = rrp_current_owner();
        } catch (Throwable $e) {
            $owner = null;
        }
    }
    $key = (string) ($owner['id'] ?? 0) . '|' . (string) ($owner['country'] ?? '');
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $code = $owner && function_exists('rrp_onb_country_code') ? rrp_onb_country_code((string) ($owner['country'] ?? '')) : '';
    $imperial = in_array($code, ['US', 'LR', 'MM'], true);
    $fahrenheit = $imperial || in_array($code, ['BS', 'BZ', 'KY', 'PW'], true);
    return $cache[$key] = [
        'imperial' => $imperial,
        'weight' => $imperial ? 'lb' : 'kg',
        'distance' => $imperial ? 'mi' : 'km',
        'pace' => $imperial ? 'min/mi' : 'min/km',
        'temp' => $fahrenheit ? 'F' : 'C',
        'speed' => $imperial ? 'mph' : 'km/h',
        'country' => $code,
    ];
}

/** A number without trailing zeros: 12.50 → "12.5". */
function rrp_units_num(float $v, int $dec = 1): string
{
    return rtrim(rtrim(number_format($v, $dec, '.', ''), '0'), '.') ?: '0';
}

/** Kilograms as the owner's number (pounds for the US). */
function rrp_weight_show(float $kg, ?array $owner = null): float
{
    return rrp_units($owner)['imperial'] ? $kg * RRP_KG_TO_LB : $kg;
}

/** "25.4 kg" or "56 lb". */
function rrp_fmt_weight(float $kg, ?array $owner = null, int $dec = 1): string
{
    $u = rrp_units($owner);
    return rrp_units_num($u['imperial'] ? $kg * RRP_KG_TO_LB : $kg, $dec) . ' ' . $u['weight'];
}

/** What the owner typed (in their unit) back to kilograms. */
function rrp_weight_to_kg(float $value, ?array $owner = null): float
{
    return rrp_units($owner)['imperial'] ? $value / RRP_KG_TO_LB : $value;
}

/** "3.2 km" or "2 mi". */
function rrp_fmt_distance(float $km, ?array $owner = null, int $dec = 1): string
{
    $u = rrp_units($owner);
    return rrp_units_num($u['imperial'] ? $km * RRP_KM_TO_MI : $km, $dec) . ' ' . $u['distance'];
}

/** Just the number in the owner's distance unit. */
function rrp_distance_show(float $km, ?array $owner = null): float
{
    return rrp_units($owner)['imperial'] ? $km * RRP_KM_TO_MI : $km;
}

/** One line for SIPO so it speaks (and converts) in the owner's units. */
function rrp_units_prompt_line(array $owner): string
{
    $u = rrp_units($owner);
    if (!$u['imperial']) {
        return 'Units: kilograms, kilometres, °' . $u['temp'] . ', ' . $u['speed'] . '. Save weights in kg.';
    }
    return 'Units: this owner uses pounds (lb), miles, °' . $u['temp'] . ' and mph. Speak in those units: convert stored kg to lb, km to miles, '
        . '°C to °F and km/h to mph. When they give a weight in lb, convert it to kg before saving (weight_kg) and when they give miles, convert to km (log_walk km).';
}
