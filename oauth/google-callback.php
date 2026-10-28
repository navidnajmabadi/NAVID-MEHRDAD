<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';

// Google sends the owner back here (authorised redirect URI in Google Cloud Console).
if (!rrp_google_configured()) {
    rrp_redirect('/login.php');
}
if (!empty($_GET['error'])) {
    // Owner pressed Cancel on Google's screen, or Google refused.
    rrp_flash_set('error', $_GET['error'] === 'access_denied' ? 'Google sign-in was cancelled.' : 'Google sign-in failed. Please try again.');
    rrp_redirect('/login.php');
}
$res = rrp_google_finish((string) ($_GET['code'] ?? ''), (string) ($_GET['state'] ?? ''));
if (empty($res['ok'])) {
    rrp_flash_set('error', $res['error'] ?? 'Google sign-in failed.');
    rrp_redirect('/login.php');
}
if (!empty($res['new'])) {
    rrp_redirect('/si-chat.php?new=1'); // brand-new owner: SIPO starts onboarding
}
rrp_redirect('/' . ltrim(($res['next'] ?? '') ?: 'dashboard.php', '/'));
