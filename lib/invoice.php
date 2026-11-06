<?php
declare(strict_types=1);

/**
 * Invoices for paid Plus orders: the data, the PDF and the email. One invoice per paid order, numbered
 * SIPAW-<year>-<order id, 6 digits> (the order id never repeats, so the number never does).
 * Seller details (name, address, tax number) come from Admin → Pricing.
 */

function rrp_invoice_number(array $order): string
{
    $year = (int) gmdate('Y', strtotime((string) ($order['paid_at'] ?: $order['created_at']) . ' UTC'));
    return sprintf('SIPAW-%d-%06d', $year, (int) $order['id']);
}

/** Who the invoice is from (set in Admin → Pricing). @return array{name:string, address:string, tax_no:string, email:string} */
function rrp_invoice_seller(): array
{
    $cfg = rrp_config_ready() ? rrp_load_config() : [];
    return [
        'name' => (string) rrp_setting('biz_name', 'NAKILAB'),
        'address' => (string) rrp_setting('biz_address', ''),
        'tax_no' => (string) rrp_setting('biz_tax_no', ''),
        'email' => (string) rrp_setting('biz_email', (string) ($cfg['mail_from'] ?? '')),
    ];
}

/**
 * A paid order of this owner that has an invoice (amount above 0), or null.
 * @return array<string,mixed>|null
 */
function rrp_invoice_order(int $orderId, int $ownerId): ?array
{
    $st = rrp_db()->prepare("SELECT * FROM billing_orders WHERE id = ? AND owner_id = ? AND status = 'paid' AND amount_cents > 0");
    $st->execute([$orderId, $ownerId]);
    $o = $st->fetch();
    return $o ?: null;
}

/**
 * Everything printed on an invoice, as lines.
 * @return array{number:string, date:string, owner_name:string, owner_email:string, country:string, currency:string,
 *   lines:list<array{label:string, cents:int, kind:string}>, subtotal:int, tax_label:string, tax_pct:float, tax:int, total:int,
 *   method:string, payment_id:string, seller:array<string,string>, auto:bool}
 */
function rrp_invoice_data(array $order, array $owner): array
{
    $tz = function_exists('rrp_sipo_owner_tz') ? rrp_sipo_owner_tz($owner) : new DateTimeZone('UTC');
    $paid = new DateTimeImmutable((string) ($order['paid_at'] ?: $order['created_at']), new DateTimeZone('UTC'));
    $d = json_decode((string) ($order['discount_json'] ?? ''), true) ?: [];
    $lines = [['label' => 'SIPAW Plus, ' . ((int) $order['months'] === 12 ? '12 months' : '1 month'), 'cents' => (int) $order['list_cents'], 'kind' => 'item']];
    $coupon = (int) ($d['coupon_cents'] ?? 0);
    $week = (int) ($d['week_cents'] ?? 0);
    $coin = (int) ($d['coin_cents'] ?? $order['coin_discount_cents'] ?? 0);
    if (!isset($d['coupon_cents']) && !isset($d['week_cents'])) {
        // Older orders did not keep each discount on its own: show what is known.
        $coupon = 0;
        $week = max(0, (int) $order['discount_cents'] - $coin);
    }
    if ($coupon > 0) {
        $lines[] = ['label' => 'Coupon ' . (string) ($order['coupon_code'] ?? ''), 'cents' => -$coupon, 'kind' => 'discount'];
    }
    if ($week > 0) {
        $pct = (int) ($d['week_pct'] ?? 0);
        $lines[] = ['label' => 'Perfect care weeks' . ($pct ? " ({$pct}%)" : ''), 'cents' => -$week, 'kind' => 'discount'];
    }
    if ($coin > 0) {
        $lines[] = ['label' => 'Paw coins (' . (int) ($order['coins_used'] ?? 0) . ' used)', 'cents' => -$coin, 'kind' => 'discount'];
    }
    $tax = (int) ($order['tax_cents'] ?? 0);
    $total = (int) $order['amount_cents'];
    $brand = ucwords(strtolower(str_replace('_', ' ', (string) ($order['card_brand'] ?? ''))));
    $name = trim((string) ($owner['name'] ?? ''));
    return [
        'number' => rrp_invoice_number($order),
        'date' => $paid->setTimezone($tz)->format('j M Y'),
        'owner_name' => $name !== '' ? $name : (string) ($owner['nickname'] ?? ''),
        'owner_email' => (string) ($owner['email'] ?? ''),
        'country' => (string) ($owner['country'] ?? ''),
        'currency' => (string) $order['currency'],
        'lines' => $lines,
        'subtotal' => $total - $tax,
        'tax_label' => (string) ($order['tax_label'] ?: 'Tax'),
        'tax_pct' => (float) ($order['tax_pct'] ?? 0),
        'tax' => $tax,
        'total' => $total,
        'method' => $order['card_last4'] ? ($brand !== '' ? $brand : 'Card') . ' ending ' . $order['card_last4'] : 'Card',
        'payment_id' => (string) ($order['provider_payment_id'] ?? ''),
        'seller' => rrp_invoice_seller(),
        'auto' => !empty($order['auto_renewal']),
    ];
}

/**
 * Draw the SI PAW "by NAKILAB" logo from the brand SVG as vector shapes (sharp at any zoom) plus the NAKILAB mark as a
 * high-resolution JPEG. Fits a box $width points wide with its top-left at ($x, $top). Returns the height used, or 0
 * when the logo files are missing (the caller then writes the name as text).
 */
function rrp_invoice_draw_logo(RrpPdf $pdf, float $x, float $top, float $width): float
{
    $root = dirname(__DIR__) . '/assets/img/logos/';
    $svg = @file_get_contents($root . 'sipaw-wordmark-endorsed-on-white.svg');
    if (!is_string($svg) || !preg_match_all('/<path\b[^>]*>/', $svg, $tags)) {
        return 0.0;
    }
    $paths = [];
    $minX = $minY = INF;
    $maxX = $maxY = -INF;
    foreach ($tags[0] as $tag) {
        if (!preg_match('/\bd="([^"]+)"/', $tag, $d)) {
            continue;
        }
        $rgb = [0.0, 0.0, 0.0];
        if (preg_match('/\bfill="#([0-9A-Fa-f]{6})"/', $tag, $f)) {
            $rgb = [hexdec(substr($f[1], 0, 2)) / 255, hexdec(substr($f[1], 2, 2)) / 255, hexdec(substr($f[1], 4, 2)) / 255];
        }
        if (preg_match('/fill-opacity="([0-9.]+)"/', $tag, $o)) {
            $a = (float) $o[1]; // the page is white, so a see-through colour is that colour mixed with white
            $rgb = array_map(static fn($c) => $c * $a + (1 - $a), $rgb);
        }
        $paths[] = [$d[1], $rgb];
        foreach (RrpPdf::parseSvgPath($d[1]) as $seg) {
            for ($i = 1; $i + 1 < count($seg); $i += 2) {
                $minX = min($minX, $seg[$i]);
                $maxX = max($maxX, $seg[$i]);
                $minY = min($minY, $seg[$i + 1]);
                $maxY = max($maxY, $seg[$i + 1]);
            }
        }
    }
    $img = null;
    if (preg_match('/<image\b[^>]*\bx="([0-9.]+)"[^>]*\by="([0-9.]+)"[^>]*\bwidth="([0-9.]+)"[^>]*\bheight="([0-9.]+)"/', $svg, $m)) {
        $img = array_map('floatval', array_slice($m, 1));
        $minX = min($minX, $img[0]);
        $minY = min($minY, $img[1]);
        $maxX = max($maxX, $img[0] + $img[2]);
        $maxY = max($maxY, $img[1] + $img[3]);
    }
    if (!$paths || !is_finite($minX) || $maxX <= $minX) {
        return 0.0;
    }
    $k = $width / ($maxX - $minX);
    $ox = $x - $minX * $k;
    $oy = $top - $minY * $k;
    foreach ($paths as [$d, $rgb]) {
        $pdf->svgPath($d, $k, $ox, $oy, $rgb);
    }
    $jpg = $img ? @file_get_contents($root . 'nakilab-mark-on-white.jpg') : false;
    if ($img && is_string($jpg)) {
        $pdf->jpeg($jpg, $ox + $img[0] * $k, $oy + $img[1] * $k, $img[2] * $k, $img[3] * $k);
    }
    return ($maxY - $minY) * $k;
}

/** "CAD 19.99", "-CAD 2.55": plain text amount for the PDF. */
function rrp_invoice_amount(int $cents, string $currency): string
{
    return ($cents < 0 ? '-' : '') . $currency . ' ' . number_format(abs($cents) / 100, 2);
}

/** The invoice as a PDF file (string). */
function rrp_invoice_pdf(array $inv): string
{
    $blue = [0.18, 0.416, 1.0];
    $grey = [0.42, 0.45, 0.5];
    $ink = [0.08, 0.09, 0.11];
    $pdf = new RrpPdf();
    $L = 50.0;
    $R = 545.0;
    $cur = $inv['currency'];

    if (rrp_invoice_draw_logo($pdf, $L, 40, 104) <= 0) {
        $pdf->text($L, 48, 'SIPAW', 28, 'b', $blue); // logo files missing: write the name instead
        $pdf->text($L, 80, 'by NAKILAB', 9, 'r', $grey);
    }
    $pdf->text(330, 52, 'INVOICE', 22, 'b', $ink);
    $pdf->text(330, 82, 'Invoice no.  ' . $inv['number'], 10, 'r', $ink);
    $pdf->text(330, 97, 'Date  ' . $inv['date'], 10, 'r', $ink);
    $pdf->text(330, 112, 'Status  PAID', 10, 'b', [0.1, 0.55, 0.35]);

    // From / Bill to
    $pdf->text($L, 150, 'FROM', 8, 'b', $grey);
    $y = 164.0;
    $seller = $inv['seller'];
    $addr = array_values(array_filter(array_map('trim', explode(',', $seller['address'])), static fn($s) => $s !== ''));
    $country = trim((string) rrp_setting('biz_country', 'Canada'));
    if ($country !== '' && !preg_grep('/' . preg_quote($country, '/') . '/i', $addr)) {
        $addr[] = $country;
    }
    $addrLines = $addr ? [array_shift($addr), ...($addr ? [implode(', ', $addr)] : [])] : []; // street, then the rest on one line
    $from = array_values(array_filter([$seller['name'], ...$addrLines, $seller['tax_no'] !== '' ? 'Tax no. ' . $seller['tax_no'] : '', $seller['email']], static fn($s) => $s !== ''));
    foreach ($from as $line) {
        $pdf->text($L, $y, $line, 10, 'r', $ink);
        $y += 14;
    }
    $pdf->text(330, 150, 'BILLED TO', 8, 'b', $grey);
    $name = RrpPdf::drawable($inv['owner_name']) ? $inv['owner_name'] : '';
    $to = array_values(array_filter([$name, $inv['owner_email'], RrpPdf::drawable($inv['country']) ? $inv['country'] : ''], static fn($s) => $s !== ''));
    $y2 = 164.0;
    foreach ($to as $line) {
        $pdf->text(330, $y2, $line, 10, 'r', $ink);
        $y2 += 14;
    }

    // Table
    $top = max($y, $y2) + 24;
    $pdf->box($L, $top, $R - $L, 24, [0.94, 0.95, 0.97]);
    $pdf->text($L + 10, $top + 7, 'Description', 9, 'b', $grey);
    $pdf->text($R - 10 - RrpPdf::monoWidth('Amount', 9), $top + 7, 'Amount', 9, 'mb', $grey);
    $y = $top + 38;
    foreach ($inv['lines'] as $l) {
        $pdf->text($L + 10, $y, $l['label'], 10.5, 'r', $ink);
        $pdf->textRight($R - 10, $y, rrp_invoice_amount($l['cents'], $cur), 10, false, $l['cents'] < 0 ? [0.1, 0.55, 0.35] : $ink);
        $y += 22;
    }
    $pdf->line($L, $y - 6, $R, $y - 6);
    $pdf->text($L + 10, $y + 4, 'Subtotal', 10, 'r', $grey);
    $pdf->textRight($R - 10, $y + 4, rrp_invoice_amount($inv['subtotal'], $cur), 10);
    $y += 22;
    if ($inv['tax'] > 0) {
        $taxText = $inv['tax_label'] . ' ' . rtrim(rtrim(number_format($inv['tax_pct'], 2), '0'), '.') . '%';
        $pdf->text($L + 10, $y, $taxText, 10, 'r', $grey);
        $pdf->textRight($R - 10, $y, rrp_invoice_amount($inv['tax'], $cur), 10);
        $y += 14;
    } else {
        $pdf->text($L + 10, $y, 'No GST/HST charged (small supplier)', 9, 'r', $grey);
        $y += 14;
    }
    $pdf->line($L + 260, $y + 6, $R, $y + 6, 1.0, [0.2, 0.22, 0.28]);
    $y += 18;
    $pdf->text($L + 260, $y, 'Total paid', 13, 'b', $ink);
    $pdf->textRight($R - 10, $y, rrp_invoice_amount($inv['total'], $cur), 13, true, $ink);

    // Payment
    $y += 56;
    $pdf->text($L, $y, 'PAYMENT', 8, 'b', $grey);
    $pdf->text($L, $y + 14, 'Paid by ' . $inv['method'] . ' on ' . $inv['date'] . ($inv['auto'] ? ' (automatic monthly renewal)' : ''), 10, 'r', $ink);
    if ($inv['payment_id'] !== '') {
        $pdf->text($L, $y + 29, 'Square payment ID  ' . $inv['payment_id'], 9, 'r', $grey);
    }
    $pdf->line($L, 770, $R, 770);
    $pdf->text($L, 780, 'Thank you for looking after your pet with SIPAW.', 9, 'r', $grey);
    $pdf->text($L, 794, 'Questions about this invoice? Reply to the email it came with or write to ' . ($seller['email'] !== '' ? $seller['email'] : 'support') . '.', 9, 'r', $grey);
    return $pdf->output('Invoice ' . $inv['number']);
}

/**
 * Email the invoice PDF. @return array{ok:bool, error?:string}
 */
function rrp_invoice_email(array $owner, array $order, string $to): array
{
    $inv = rrp_invoice_data($order, $owner);
    $pdf = rrp_invoice_pdf($inv);
    $name = (string) ($owner['nickname'] ?? '') ?: 'there';
    $amount = rrp_invoice_amount($inv['total'], $inv['currency']);
    $mail = [
        'subject' => 'Your SIPAW invoice ' . $inv['number'],
        'text' => "Hi {$name},\n\nYour invoice {$inv['number']} for {$amount} (paid {$inv['date']}) is attached as a PDF.\n\nThank you for looking after your pet with SIPAW.\nSIPAW by NAKILAB\n",
        'html' => rrp_email_layout([
            'preheader' => 'Invoice ' . $inv['number'] . ', ' . $amount,
            'mood' => 'happy',
            'headline' => 'Your invoice is ready',
            'intro' => 'Hi ' . rrp_email_h($name) . ', invoice <strong>' . rrp_email_h($inv['number']) . '</strong> for <strong>' . rrp_email_h($amount) . '</strong> (paid ' . rrp_email_h($inv['date']) . ') is attached as a PDF.',
            'button' => ['Open Billing', rrp_email_abs('billing.php')],
            'note' => 'You can download any invoice again from Menu, Billing.',
        ]),
    ];
    $r = rrp_email_send($to, $mail, [['name' => 'Invoice-' . $inv['number'] . '.pdf', 'type' => 'application/pdf', 'data' => $pdf]]);
    if (!empty($r['ok'])) {
        rrp_db()->prepare('UPDATE billing_orders SET invoice_sent_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $order['id']]);
    }
    return $r;
}
