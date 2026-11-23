<?php
declare(strict_types=1);

/**
 * Prices for people in other countries.
 *
 * Square can only take payments in ONE currency: the one of the seller's Square account (CAD for NAKILAB). So every card is
 * charged in that currency, and the customer's bank converts it at its own rate. What we can do, and do here, is show an
 * ESTIMATE in the customer's own currency before they pay, so there is no surprise. The estimate uses the daily European
 * Central Bank rates from frankfurter.app (free, no key), cached for 12 hours. If the rate is unavailable nothing is shown.
 */

/** Country (ISO code) to currency, for the countries SIPAW is likely to have. Others get no estimate. */
function rrp_currency_for_country(string $code): string
{
    static $map = [
        'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'AU' => 'AUD', 'NZ' => 'NZD', 'JP' => 'JPY', 'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK',
        'DK' => 'DKK', 'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'BG' => 'BGN', 'TR' => 'TRY', 'IL' => 'ILS', 'IN' => 'INR',
        'CN' => 'CNY', 'HK' => 'HKD', 'SG' => 'SGD', 'KR' => 'KRW', 'MY' => 'MYR', 'TH' => 'THB', 'PH' => 'PHP', 'ID' => 'IDR', 'BR' => 'BRL',
        'MX' => 'MXN', 'ZA' => 'ZAR', 'IS' => 'ISK',
        'AT' => 'EUR', 'BE' => 'EUR', 'CY' => 'EUR', 'EE' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'DE' => 'EUR', 'GR' => 'EUR', 'HR' => 'EUR',
        'IE' => 'EUR', 'IT' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR', 'MT' => 'EUR', 'NL' => 'EUR', 'PT' => 'EUR', 'SK' => 'EUR',
        'SI' => 'EUR', 'ES' => 'EUR',
    ];
    return $map[strtoupper($code)] ?? '';
}

/** Rate from $base to $to (1 base = x to), or null when unknown. */
function rrp_currency_rate(string $base, string $to): ?float
{
    if ($base === $to) {
        return 1.0;
    }
    $key = 'fx:' . $base;
    $data = function_exists('rrp_sipo_cache_get') ? rrp_sipo_cache_get($key, 12 * 3600) : null;
    if (!is_array($data) || empty($data['rates'])) {
        $data = function_exists('rrp_sipo_http_json') ? rrp_sipo_http_json('https://api.frankfurter.app/latest?from=' . rawurlencode($base), 5) : null;
        if (!is_array($data) || empty($data['rates'])) {
            return null;
        }
        rrp_sipo_cache_set($key, $data);
    }
    $r = $data['rates'][$to] ?? null;
    return is_numeric($r) && $r > 0 ? (float) $r : null;
}

/**
 * Estimate of an amount in the owner's own currency.
 * @return array{code:string, text:string}|null null when the owner already pays in that currency, has no country, or no rate is known
 */
function rrp_currency_estimate(int $cents, string $base, array $owner): ?array
{
    $code = function_exists('rrp_onb_country_code') ? rrp_onb_country_code((string) ($owner['country'] ?? '')) : '';
    $to = $code !== '' ? rrp_currency_for_country($code) : '';
    if ($to === '' || $to === $base || $cents <= 0) {
        return null;
    }
    $rate = rrp_currency_rate($base, $to);
    if ($rate === null) {
        return null;
    }
    $amount = $cents / 100 * $rate;
    $zeroDecimals = in_array($to, ['JPY', 'KRW', 'HUF', 'ISK', 'IDR'], true);
    return ['code' => $to, 'text' => $to . ' ' . number_format($amount, $zeroDecimals ? 0 : 2)];
}
