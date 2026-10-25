#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Generate VAPID keys for siPaw Web Push.
 * Usage: php scripts/generate-vapid.php
 * Paste public_key + private_key into domains/.../config/sipaw.php under 'vapid'.
 */

require_once dirname(__DIR__) . '/lib/webpush.php';

// Minimal stubs so webpush.php helpers work without full bootstrap/config.
if (!function_exists('rrp_load_config')) {
    // webpush.php only needs encode helpers + openssl for generate
}

try {
    $keys = rrp_vapid_generate_keys();
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Add these to domains/.../config/sipaw.php under 'vapid':\n\n";
echo "    'vapid' => [\n";
echo "        'subject' => 'mailto:you@nakilab.com',\n";
echo "        'public_key' => '{$keys['public_key']}',\n";
echo "        'private_key' => '{$keys['private_key']}',\n";
echo "    ],\n\n";
echo "Keep private_key secret. Never commit sipaw.php.\n";
