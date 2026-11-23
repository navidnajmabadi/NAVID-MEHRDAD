<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/**
 * Sign in for the iPhone app: JSON {email, password} -> session cookie (+ the 60 day remember cookie) and the CSRF token
 * the app puts in every later POST. Same checks and throttling as login.php.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rrp_json(['ok' => false, 'error' => 'POST required'], 405);
}
$d = json_decode((string) file_get_contents('php://input') ?: '[]', true);
$d = is_array($d) ? $d : [];
$r = rrp_login((string) ($d['email'] ?? ''), (string) ($d['password'] ?? ''), true);
if (empty($r['ok'])) {
    rrp_json(['ok' => false, 'error' => $r['error'] ?? 'Email or password is incorrect.'], 401);
}
$owner = $r['owner'] ?? rrp_current_owner();
rrp_json(['ok' => true, 'csrf' => rrp_csrf_token(), 'name' => (string) ($owner['nickname'] ?? $owner['name'] ?? '')]);
