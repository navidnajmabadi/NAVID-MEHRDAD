<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Email one invoice PDF to the owner (or another address they type). At most 6 a hour. */
[$owner, $data] = rrp_api_owner_post();
$order = rrp_invoice_order((int) ($data['order_id'] ?? 0), (int) $owner['id']);
if (!$order) {
    rrp_json(['ok' => false, 'error' => 'Invoice not found.'], 404);
}
$to = trim((string) ($data['to'] ?? ''));
if ($to === '') {
    $to = (string) ($owner['email'] ?? '');
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL) || strlen($to) > 120) {
    rrp_json(['ok' => false, 'error' => 'That email address does not look right.']);
}
rrp_start_session();
$now = time();
$sent = array_values(array_filter((array) ($_SESSION['inv_mail'] ?? []), static fn($t) => $t > $now - 3600));
if (count($sent) >= 6) {
    rrp_json(['ok' => false, 'error' => 'That is enough emails for now. Please try again in a little while.']);
}
$r = rrp_invoice_email($owner, $order, $to);
if (empty($r['ok'])) {
    error_log('invoice email failed: ' . ($r['error'] ?? '?'));
    rrp_json(['ok' => false, 'error' => 'The email could not be sent. You can download the PDF instead.']);
}
$sent[] = $now;
$_SESSION['inv_mail'] = $sent;
rrp_json(['ok' => true, 'to' => $to]);
