<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';

/** The browser reports a JavaScript error or a failed (5xx) request; it is stored like a server error. */
[$owner, $data] = rrp_api_owner_post();

// Signed-in owners only, a handful per page load, so this cannot be used to fill the table.
rrp_start_session();
$_SESSION['client_errors'] = (int) ($_SESSION['client_errors'] ?? 0) + 1;
if ($_SESSION['client_errors'] > 200) {
    rrp_json(['ok' => true]);
}

$kind = ($data['kind'] ?? '') === 'http' ? 'http' : 'js';
$message = trim((string) ($data['message'] ?? ''));
if ($message === '') {
    rrp_json(['ok' => true]);
}
$page = mb_substr((string) parse_url((string) ($data['page'] ?? ''), PHP_URL_PATH), 0, 200);
$src = mb_substr((string) ($data['source'] ?? ''), 0, 200);
$line = (int) ($data['line'] ?? 0);
$trace = isset($data['stack']) ? mb_substr((string) $data['stack'], 0, 3000) : null;

// "where" is the page the person was on, so the same JS error on two pages is two rows.
$code = rrp_ops_record_error($kind, mb_substr($message, 0, 400), $page !== '' ? 'page:' . $page : $src, $line > 0 ? $line : null, $trace);
rrp_json(['ok' => true, 'code' => $code]);
