<?php
/**
 * RingRingPaw config template.
 *
 * On Hostinger, copy this file OUTSIDE the web root to ONE of:
 *   /home/u233722910/domains/nakilab.com/config/ringringpaw.php
 *   /home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php
 *
 * lib/bootstrap.php checks both absolute paths (subdomain may be its own site),
 * then relative ../../config/ fallbacks. Never commit a real copy with passwords.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u233722910_ringringpaw', // change to your MySQL database name
        'user' => 'u233722910_rrp',         // change to your MySQL user
        'pass' => 'CHANGE_ME',              // set a strong password
        'charset' => 'utf8mb4',
    ],

    // Public site origin (no trailing slash). Used in emails + QR URLs.
    'app_url' => 'https://ringringpaw.nakilab.com',

    // From-address for scan alert emails (must be allowed on Hostinger mail).
    'mail_from' => 'noreply@nakilab.com',
    'mail_from_name' => 'Ring Ring Paw',

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

    // Optional Web Push (VAPID). Leave empty to skip push; email + inbox still work.
    // Generate keys once (e.g. https://vapidkeys.com) and paste here.
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
        'user_agent' => 'RingRingPaw/1 (https://ringringpaw.nakilab.com; noreply@nakilab.com)',
    ],
];
