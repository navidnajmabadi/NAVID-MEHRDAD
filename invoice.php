<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// One invoice as a PDF (only for the signed-in owner's own paid orders). ?inline=1 opens it in the browser.
$owner = rrp_require_owner();
$order = rrp_invoice_order((int) ($_GET['id'] ?? 0), (int) $owner['id']);
if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Invoice not found.\n");
}
$inv = rrp_invoice_data($order, $owner);
$pdf = rrp_invoice_pdf($inv);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['inline']) ? 'inline' : 'attachment') . '; filename="Invoice-' . $inv['number'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
