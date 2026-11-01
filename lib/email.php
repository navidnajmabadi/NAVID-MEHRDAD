<?php
declare(strict_types=1);

/**
 * Designed emails: one shared layout (SIPAW black and white, SI Blue accent, SIPO on top) and the
 * templates built on it. Every template returns ['subject', 'text', 'html'] and is sent with
 * rrp_email_send(). Tables and inline styles only: that is what mail apps render reliably.
 */

const RRP_EMAIL_INK = '#0B0B0F';
const RRP_EMAIL_MUTED = '#5B6270';
const RRP_EMAIL_LINE = '#E4E7EE';
const RRP_EMAIL_BG = '#F3F5F9';
const RRP_EMAIL_BLUE = '#2E6AFF';
const RRP_EMAIL_SKY = '#E3EEF6';
const RRP_EMAIL_AMBER = '#B76E00';
const RRP_EMAIL_RED = '#D12F3A';
const RRP_EMAIL_GREEN = '#17805A';

function rrp_email_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rrp_email_abs(string $path): string
{
    return rtrim(rrp_app_url(), '/') . '/' . ltrim($path, '/');
}

/**
 * The shared frame.
 *
 * @param array{preheader?:string, mood?:string, headline:string, intro?:string, body?:string,
 *              button?:array{0:string,1:string}, note?:string, footer?:string} $p
 *        intro/body/note/footer are HTML you already escaped.
 */
function rrp_email_layout(array $p): string
{
    $ink = RRP_EMAIL_INK;
    $muted = RRP_EMAIL_MUTED;
    $mood = preg_replace('/[^a-z]/', '', (string) ($p['mood'] ?? 'happy'));
    $mascot = rrp_email_abs('assets/mascot/v2/' . $mood . '-mail.png');
    $pre = rrp_email_h((string) ($p['preheader'] ?? ''));
    $button = '';
    if (!empty($p['button'])) {
        [$label, $url] = $p['button'];
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:26px auto 6px"><tr>'
            . '<td align="center" bgcolor="' . RRP_EMAIL_BLUE . '" style="border-radius:12px">'
            . '<a href="' . rrp_email_h($url) . '" style="display:inline-block;padding:15px 34px;font:600 16px/1 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#ffffff;text-decoration:none;border-radius:12px">'
            . rrp_email_h($label) . '</a></td></tr></table>';
    }
    $intro = !empty($p['intro']) ? '<p style="margin:0 0 6px;font:400 16px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . $muted . '">' . $p['intro'] . '</p>' : '';
    $note = !empty($p['note']) ? '<p style="margin:22px 0 0;padding-top:18px;border-top:1px solid ' . RRP_EMAIL_LINE . ';font:400 13px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . $muted . '">' . $p['note'] . '</p>' : '';
    $footer = $p['footer'] ?? '';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="x-apple-disable-message-reformatting"><title>' . rrp_email_h(strip_tags($p['headline'])) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . RRP_EMAIL_BG . '">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">' . $pre . str_repeat('&nbsp;&zwnj;', 40) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . RRP_EMAIL_BG . '"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:20px;overflow:hidden">'
        // brand bar
        . '<tr><td align="center" bgcolor="' . $ink . '" style="padding:22px 24px"><span style="font:800 26px/1 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;letter-spacing:.14em;color:' . RRP_EMAIL_BLUE . '">SI</span>'
        . '<span style="font:300 26px/1 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;letter-spacing:.34em;color:#ffffff">&nbsp;PAW</span></td></tr>'
        // SIPO
        . '<tr><td align="center" bgcolor="' . RRP_EMAIL_SKY . '" style="padding:26px 24px 8px"><img src="' . rrp_email_h($mascot) . '" width="132" alt="SIPO" style="display:block;border:0;height:auto;width:132px"></td></tr>'
        . '<tr><td bgcolor="' . RRP_EMAIL_SKY . '" style="height:20px;line-height:20px;font-size:0">&nbsp;</td></tr>'
        // content
        . '<tr><td style="padding:30px 34px 34px">'
        . '<h1 style="margin:0 0 12px;font:700 26px/1.25 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . $ink . '">' . $p['headline'] . '</h1>'
        . $intro . ($p['body'] ?? '') . $button . $note
        . '</td></tr>'
        . '</table>'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px"><tr><td align="center" style="padding:18px 20px;font:400 12px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . $muted . '">'
        . $footer . '<br>SIPAW by NAKILAB &middot; looking after the little ones 🐾</td></tr></table>'
        . '</td></tr></table></body></html>';
}

/** @return array{subject:string, text:string, html:string} */
function rrp_email_password_reset(string $name, string $url, int $hours = 2): array
{
    $first = rrp_email_h($name !== '' ? $name : 'there');
    $html = rrp_email_layout([
        'preheader' => 'Use this link to choose a new password. It works for ' . $hours . ' hours.',
        'mood' => 'happy',
        'headline' => 'Let&rsquo;s get you back in',
        'intro' => "Hi {$first}, someone (hopefully you) asked to reset the password for your SIPAW account. Tap the button and choose a new one.",
        'button' => ['Reset my password', $url],
        'note' => 'This link works for ' . $hours . ' hours and can be used once. If the button does not open, copy this address into your browser:<br>'
            . '<a href="' . rrp_email_h($url) . '" style="color:' . RRP_EMAIL_BLUE . ';word-break:break-all">' . rrp_email_h($url) . '</a><br><br>'
            . '<strong style="color:' . RRP_EMAIL_INK . '">Did not ask for this?</strong> You can ignore this email. Your password stays the same and nobody can change it without this link.',
    ]);
    $text = "Hi " . ($name !== '' ? $name : 'there') . ",\n\n"
        . "Someone (hopefully you) asked to reset your SIPAW password. Use this link to choose a new one (it works for {$hours} hours, once):\n\n"
        . "{$url}\n\n"
        . "Did not ask for this? Ignore this email: your password stays the same.\n\n"
        . "SIPAW by NAKILAB\n";
    return ['subject' => 'Reset your SIPAW password', 'text' => $text, 'html' => $html];
}

/** Small coloured status pill. */
function rrp_email_pill(string $label, string $kind): string
{
    $map = ['red' => [RRP_EMAIL_RED, '#FDECEE'], 'amber' => [RRP_EMAIL_AMBER, '#FFF3DC'], 'green' => [RRP_EMAIL_GREEN, '#E6F6EF'], 'blue' => [RRP_EMAIL_BLUE, '#E8EFFF']];
    [$fg, $bg] = $map[$kind] ?? $map['blue'];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:99px;background:' . $bg . ';color:' . $fg . ';font:600 12px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">' . rrp_email_h($label) . '</span>';
}

function rrp_email_stat(string $value, string $label): string
{
    return '<td align="center" width="25%" style="padding:12px 4px;background:' . RRP_EMAIL_BG . ';border-radius:12px">'
        . '<div style="font:700 22px/1.2 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . rrp_email_h($value) . '</div>'
        . '<div style="font:400 11px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_MUTED . '">' . rrp_email_h($label) . '</div></td>';
}

/** Weight trend as horizontal bars (works in every mail app, no images). */
function rrp_email_weight_block(array $series, string $trend): string
{
    if (count($series) < 1) {
        return '';
    }
    $min = min(array_column($series, 'kg'));
    $max = max(array_column($series, 'kg'));
    $rows = '';
    foreach ($series as $pt) {
        // Bars start at 35% so small changes are visible without exaggerating a flat line.
        $pct = $max > $min ? 35 + (int) round(($pt['kg'] - $min) / ($max - $min) * 65) : 70;
        $rows .= '<tr><td width="58" style="padding:3px 8px 3px 0;font:400 12px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_MUTED . '">' . rrp_email_h(date('M j', strtotime($pt['date'] . ' UTC'))) . '</td>'
            . '<td style="padding:3px 0"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="' . $pct . '%"><tr><td height="14" bgcolor="' . RRP_EMAIL_BLUE . '" style="border-radius:7px;font-size:0;line-height:0">&nbsp;</td></tr></table></td>'
            . '<td width="62" align="right" style="padding:3px 0 3px 8px;font:600 13px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . rrp_email_h(rtrim(rtrim(number_format($pt['kg'], 2, '.', ''), '0'), '.')) . ' kg</td></tr>';
    }
    $badge = $trend === 'up' ? rrp_email_pill('Going up', 'amber') : ($trend === 'down' ? rrp_email_pill('Going down', 'amber') : rrp_email_pill('Steady', 'green'));
    return '<div style="margin:18px 0 6px;font:600 14px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">Weight trend &nbsp;' . (count($series) > 1 ? $badge : '') . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>';
}

function rrp_email_section_title(string $t): string
{
    return '<div style="margin:20px 0 8px;font:600 14px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . rrp_email_h($t) . '</div>';
}

/** @return array{subject:string, text:string, html:string} */
function rrp_email_weekly_report(array $r, ?string $manageUrl = null): array
{
    $first = rrp_email_h($r['owner_name']);
    $names = array_map(static fn($p) => $p['name'], $r['pets']);
    $period = date('M j', strtotime($r['from'] . ' UTC')) . ' to ' . date('M j', strtotime($r['to'] . ' UTC'));
    $cards = '';
    $text = "Weekly health report ({$period})\n\nHi {$r['owner_name']},\n";

    foreach ($r['pets'] as $p) {
        $pn = rrp_email_h($p['name']);
        $c = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 0;border:1px solid ' . RRP_EMAIL_LINE . ';border-radius:16px"><tr><td style="padding:20px 20px 22px">'
            . '<div style="font:700 20px/1.3 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . $pn . ' <span style="font:400 13px/1 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_MUTED . '">&middot; ' . rrp_email_h(ucfirst($p['type'])) . '</span></div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="6" border="0" style="margin:12px -6px 0"><tr>'
            . rrp_email_stat((string) $p['walks'], 'walks')
            . rrp_email_stat((string) $p['walk_minutes'], 'walk minutes')
            . rrp_email_stat((string) $p['bathroom'], 'potty logs')
            . rrp_email_stat((string) $p['checkins'], 'check-ins')
            . '</tr></table>';
        $text .= "\n== {$p['name']} ==\nWalks: {$p['walks']} ({$p['walk_minutes']} min), potty logs: {$p['bathroom']}, check-ins: {$p['checkins']}\n";

        if ($p['weight']) {
            $c .= rrp_email_weight_block($p['weight'], $p['weight_trend']);
            $last = end($p['weight']);
            $text .= "Weight: {$last['kg']} kg (" . $p['weight_trend'] . ")\n";
        }
        if ($p['due']) {
            $c .= rrp_email_section_title('Vaccines and check-ups');
            foreach ($p['due'] as $d) {
                $pill = $d['status'] === 'overdue' ? rrp_email_pill('Overdue ' . abs($d['days']) . ' d', 'red') : rrp_email_pill('In ' . $d['days'] . ' d', 'amber');
                $c .= '<div style="padding:6px 0;font:400 14px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . rrp_email_h($d['label']) . ' &nbsp;' . $pill
                    . ' <span style="color:' . RRP_EMAIL_MUTED . ';font-size:12px">' . rrp_email_h(date('M j, Y', strtotime($d['date'] . ' UTC'))) . '</span></div>';
                $text .= "Due: {$d['label']} on {$d['date']} (" . ($d['status'] === 'overdue' ? 'overdue' : 'soon') . ")\n";
            }
        }
        if ($p['meds']) {
            $c .= rrp_email_section_title('Medicines and supplements');
            foreach ($p['meds'] as $m) {
                $when = $m['days_left'] === null ? rrp_email_pill('Ongoing', 'blue')
                    : ($m['days_left'] <= 3 ? rrp_email_pill('Course ends in ' . $m['days_left'] . ' d', 'amber') : rrp_email_pill('Ends ' . date('M j', strtotime($m['ends_on'] . ' UTC')), 'blue'));
                $c .= '<div style="padding:6px 0;font:400 14px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . rrp_email_h($m['name']) . ' &nbsp;' . $when . '</div>';
                $text .= "Medicine: {$m['name']}" . ($m['ends_on'] ? " (ends {$m['ends_on']})" : '') . "\n";
            }
        }
        if ($p['labs']) {
            $c .= rrp_email_section_title('Lab values outside the usual range');
            foreach ($p['labs'] as $l) {
                $pill = rrp_email_pill($l['flag'] === 'high' ? 'High' : 'Low', 'red');
                $c .= '<div style="padding:6px 0;font:400 14px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '">' . rrp_email_h($l['label']) . ': <strong>' . rrp_email_h($l['value'] . ' ' . $l['unit']) . '</strong> &nbsp;' . $pill
                    . ($l['range'] !== '' ? ' <span style="color:' . RRP_EMAIL_MUTED . ';font-size:12px">usual ' . rrp_email_h($l['range']) . '</span>' : '') . '</div>';
                $text .= "Lab: {$l['label']} {$l['value']} {$l['unit']} ({$l['flag']})\n";
            }
        }
        // SIPO's note
        $c .= '<div style="margin-top:18px;padding:14px 16px;background:' . RRP_EMAIL_SKY . ';border-radius:12px;font:400 14px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '"><strong>SIPO says:</strong> ' . rrp_email_h($p['note']) . '</div>'
            . '</td></tr></table>';
        $text .= "SIPO says: {$p['note']}\n";
        $cards .= $c;
    }

    $spend = '';
    if ($r['spend']) {
        $parts = [];
        foreach ($r['spend'] as $cur => $amt) {
            $parts[] = rrp_email_h($cur . ' ' . number_format($amt, 2));
        }
        $spend = '<p style="margin:22px 0 0;font:400 14px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_INK . '"><strong>Spent on your pets this week:</strong> ' . implode(' &middot; ', $parts) . '</p>';
        $text .= "\nSpent this week: " . strip_tags(implode(', ', $parts)) . "\n";
    }
    if (!$r['pets']) {
        $cards = '<p style="margin:18px 0 0;font:400 15px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:' . RRP_EMAIL_MUTED . '">You have not added a pet yet. Tell SIPO about your pet and your weekly report will start filling in.</p>';
    }

    $manage = $manageUrl ?: rrp_email_abs('settings');
    $footer = 'You get this once a week. <a href="' . rrp_email_h($manage) . '" style="color:' . RRP_EMAIL_BLUE . '">Turn it off in Settings</a>';
    $html = rrp_email_layout([
        'preheader' => $r['has_activity'] ? 'How ' . implode(' and ', $names) . ' did this week, and what is coming up.' : 'A quiet week. Here is where things stand.',
        'mood' => $r['has_activity'] ? 'happy' : 'love',
        'headline' => 'Your weekly health report',
        'intro' => "Hi {$first}, here is how " . rrp_email_h(implode(' and ', $names) ?: 'your pet') . " did from {$period}.",
        'body' => $cards . $spend,
        'button' => ['Open SIPO', rrp_email_abs('si-chat')],
        'note' => 'Want your vet to see it all? In Health you can create a private link to share the pet&rsquo;s health folder.',
        'footer' => $footer,
    ]);
    $text .= "\nOpen SIPO: " . rrp_email_abs('si-chat') . "\nTurn this off in Settings: {$manage}\n\nSIPAW by NAKILAB\n";
    return ['subject' => 'Weekly health report: ' . (implode(' & ', $names) ?: 'your pet') . ' (' . $period . ')', 'text' => $text, 'html' => $html];
}

/**
 * Send a template result to an address.
 *
 * @param array{subject:string, text:string, html:string} $mail
 * @return array{ok:bool, error?:string, via?:string}
 */
function rrp_email_send(string $to, array $mail): array
{
    return rrp_send_mail_result($to, $mail['subject'], $mail['text'], $mail['html']);
}
