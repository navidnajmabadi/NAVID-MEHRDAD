<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rrp_json(['ok' => false, 'error' => 'GET required'], 405);
}

$configured = rrp_vapid_configured();
rrp_json([
    'ok' => true,
    'configured' => $configured,
    'publicKey' => $configured ? rrp_vapid_public_key() : '',
    'hint' => $configured
        ? null
        : 'Add vapid.public_key and vapid.private_key to ringringpaw.php. Email + inbox still work.',
]);
