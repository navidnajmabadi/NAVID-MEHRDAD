<?php
declare(strict_types=1);
/**
 * Google SSO callback stub — wiring lands after RRP-1 style confirm + T03.
 * Put client_id / client_secret / redirect_uri in Hostinger sipaw.php → google.*.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

http_response_code(501);
header('Content-Type: text/plain; charset=utf-8');
echo "Google SSO is not enabled yet.\n";
echo "1) Confirm button style (RRP-1)\n";
echo "2) Add google.client_id / client_secret in config/sipaw.php\n";
echo "3) Authorized redirect: https://sipaw.nakilab.com/oauth/google-callback.php\n";
