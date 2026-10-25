<?php
/**
 * siPaw config template.
 *
 * On Hostinger, copy this file OUTSIDE the web root to ONE of:
 *   /home/u233722910/domains/nakilab.com/config/sipaw.php          (preferred)
 *   /home/u233722910/domains/nakilab.com/config/ringringpaw.php    (legacy filename OK)
 *   /home/u233722910/domains/sipaw.nakilab.com/config/sipaw.php
 *
 * lib/bootstrap.php checks sipaw.php first, then legacy ringringpaw.php,
 * then relative ../../config/ fallbacks. Never commit a real copy with passwords.
 *
 * After the Hostinger folder rename (ringringpaw → sipaw), set:
 *   app_url => 'https://sipaw.nakilab.com'
 * and point the subdomain document root at public_html/sipaw.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u233722910_sipaw', // Hostinger MySQL DB (legacy ringringpaw name OK if unchanged)
        'user' => 'u233722910_rrp',   // change to your MySQL user
        'pass' => 'CHANGE_ME',        // set a strong password
        'charset' => 'utf8mb4',
    ],

    // Public site origin (no trailing slash). Used in emails + QR URLs.
    'app_url' => 'https://sipaw.nakilab.com',

    // OpenAI (SI chatbot) — set api_key only in live sipaw.php outside web root. Never commit secrets.
    // v1: pay-as-you-go; start with mini models. App enforces Free/Plus gates + rate limits.
    'openai' => [
        'api_key' => '',
        'model' => 'gpt-4o-mini',
        'base_url' => 'https://api.openai.com/v1',
    ],

    // From-address for scan alert emails (must be allowed on Hostinger mail).
    'mail_from' => 'noreply@nakilab.com',
    'mail_from_name' => 'siPaw',

    // Optional SMTP (leave 'enabled' => false to use PHP mail()).
    'smtp' => [
        'enabled' => false,
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl', // ssl | tls
        'user' => '',
        'pass' => '',
    ],

    // Session cookie settings
    'session_name' => 'rrp_session',

    // Live location TTL (seconds) when finder shares live.
    'live_ttl_seconds' => 30 * 60,

    // Web Push (VAPID) for installed PWA notifications.
    // Leave keys empty to skip push (no crash; email + inbox still work).
    // Generate: php scripts/generate-vapid.php  OR https://vapidkeys.com
    // Full steps: docs/WEB_PUSH.md
    'vapid' => [
        'subject' => 'mailto:you@nakilab.com',
        'public_key' => '',
        'private_key' => '',
    ],

    // LOST HELP ME broadcast radius (km) from owner's registered address.
    'lost_radius_km' => 25,

    // Shared secret for Hostinger cron hitting cron/lost-escalate.php?key=...
    // Generate a long random string and keep it only in this config file.
    'cron_secret' => 'CHANGE_ME_CRON_SECRET_LONG_RANDOM',

    // Admin bootstrap (admin/setup.php) — only used when admin_users is empty.
    // Set to a long random string; remove or blank after creating Navid + Mehrdad.
    'admin_setup_secret' => 'CHANGE_ME_ADMIN_SETUP_SECRET',

    // Geocoding: OpenStreetMap Nominatim (free). Cache in geocode_cache table.
    // Must send a valid User-Agent identifying the app + contact (Nominatim policy).
    'geocode' => [
        'provider' => 'nominatim',
        'user_agent' => 'siPaw/1 (https://sipaw.nakilab.com; noreply@nakilab.com)',
    ],
];
