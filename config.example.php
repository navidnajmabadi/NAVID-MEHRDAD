<?php
/**
 * SIPAW config template (match docs/sipaw-hostinger-config-template.php).
 *
 * On Hostinger, copy OUTSIDE the web root to:
 *   /home/u233722910/domains/nakilab.com/config/sipaw.php
 *
 * Fill every CHANGE_ME / empty secret yourself. Never commit this file with real keys.
 * Models: gpt-6-luna (product) · gpt-6.1-sol (heavier) · gpt-6-astra (SI Brain / Red Team).
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u233722910_sipaw',
        'user' => 'u233722910_rrp',
        'pass' => 'CHANGE_ME_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    'app_url' => 'https://sipaw.nakilab.com',

    // -------------------------------------------------------------------------
    // OpenAI — TWO keys
    // 1) api_key        → SIPO / product SI (chat, vision, mod, register flow)
    // 2) brain_api_key  → NAKILAB Red Team / Admin SI Brain (eval, improve, KPI)
    // Leave empty until ready; app must degrade gracefully without 500s.
    // -------------------------------------------------------------------------
    'openai' => [
        'api_key' => '',              // KEY 1 — sk-… SIPO / SI product traffic
        'brain_api_key' => '',        // KEY 2 — sk-… NAKILAB Red Team + Admin SI Brain
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-6-luna',      // default fallback = product chat
        'models' => [
            'si_chat' => 'gpt-6-luna',
            'food_vision' => 'gpt-6-luna',
            'forum_mod' => 'gpt-6-luna',
            'places_copy' => 'gpt-6-luna',
            'landing_copy' => 'gpt-6-luna',
            'template_research' => 'gpt-6.1-sol',
            'medical_explain' => 'gpt-6.1-sol',
            'kpi_weekly' => 'gpt-6.1-sol',
            'si_brain' => 'gpt-6-astra',
            'pet_register' => 'gpt-6-luna',
        ],
        'si_transcript' => [
            // Navid-only master for encrypt/decrypt SI logs (Admin SI Brain)
            // Generate: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
            'master_key' => '',
            'cipher' => 'aes-256-gcm',
        ],
    ],

    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'https://sipaw.nakilab.com/oauth/google-callback.php',
    ],

    'mail_from' => 'noreply@nakilab.com',
    'mail_from_name' => 'SIPAW',
    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl',
        'user' => 'CHANGE_ME_SMTP_USER',
        'pass' => 'CHANGE_ME_SMTP_PASS',
    ],

    'session_name' => 'rrp_session',
    'live_ttl_seconds' => 30 * 60,

    'vapid' => [
        'subject' => 'mailto:navid.najmabadi@gmail.com',
        'public_key' => '',
        'private_key' => '',
    ],

    'lost_radius_km' => 25,
    'cron_secret' => 'CHANGE_ME_CRON_SECRET_LONG_RANDOM',
    'admin_setup_secret' => 'CHANGE_ME_ADMIN_SETUP_SECRET',

    // Super / unlimited owners (emails, lowercased). Bypass Free/Plus pet caps; full Admin.
    'super_owners' => [
        'navid.najmabadi@gmail.com',
        'm.kiazade@gmail.com',
    ],

    'geocode' => [
        'provider' => 'nominatim',
        'user_agent' => 'SIPAW/1 (https://sipaw.nakilab.com; noreply@nakilab.com)',
    ],
];
