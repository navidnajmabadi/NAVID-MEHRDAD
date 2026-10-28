<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';

// "Continue with Google": send the browser to Google's account chooser.
if (!rrp_google_configured()) {
    rrp_flash_set('error', 'Google sign-in is not set up yet. Use your email and password.');
    rrp_redirect('/login.php');
}
header('Location: ' . rrp_google_start_url((string) ($_GET['next'] ?? '')), true, 302);
exit;
