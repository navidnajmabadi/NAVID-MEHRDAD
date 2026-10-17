<?php
/**
 * RingRingPaw config template.
 *
 * On Hostinger, copy this file OUTSIDE the web root to:
 *   /home/u233722910/domains/nakilab.com/config/ringringpaw.php
 *
 * The app loads it via lib/bootstrap.php (looks for ../../config/ringringpaw.php
 * relative to public_html/ringringpaw). Never commit a real copy with passwords.
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
];
