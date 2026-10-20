<?php
// Nakilab — email templates for intake submissions.
// Each builder returns [subject, plain text, html]. Wording rule: never "AI" — always "SI" / "Super Intelligence".
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NK_FIELD_LABELS = [
    'title' => 'Idea name', 'idea' => 'Description', 'stage' => 'Stage', 'country' => 'Country', 'help' => 'Help needed',
    'skills' => 'Skills', 'interests' => 'Interests', 'budget' => 'Budget', 'time' => 'Time per week',
    'org' => 'Organisation', 'message' => 'Message',
];
const NK_TYPE_LABELS = ['have' => 'I have an idea', 'need' => 'I need an idea', 'invest' => 'Invest / partner'];

function nk_fields_list(array $details): array {
    $out = [];
    foreach (NK_FIELD_LABELS as $k => $label) {
        if (!isset($details[$k])) continue;
        $v = is_array($details[$k]) ? implode(', ', $details[$k]) : trim((string) $details[$k]);
        if ($v !== '') $out[$label] = $v;
    }
    return $out;
}

/** Wrap content in the Nakilab email layout (light body for readability, black header). */
function nk_email_layout(string $preheader, string $bodyHtml): string {
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"></head>'
        . '<body style="margin:0;padding:0;background:#f2f2f3;font-family:Helvetica,Arial,sans-serif;color:#111;">'
        . '<span style="display:none;max-height:0;overflow:hidden;opacity:0">' . nk_h($preheader) . '</span>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f3;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff">'
        . '<tr><td style="background:#000;padding:22px 32px"><img src="https://nakilab.com/assets/nakilab-logo-white.png" width="150" alt="NAKILAB" style="display:block;border:0;color:#fff;font-weight:bold;letter-spacing:2px"></td></tr>'
        . '<tr><td style="padding:36px 32px 28px;font-size:16px;line-height:1.6">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:20px 32px;border-top:1px solid #e6e6e8;font-size:12px;line-height:1.5;color:#77777d">'
        . 'Nakilab: We turn ideas into companies. Powered by SI, Super Intelligence.<br><a href="https://nakilab.com" style="color:#77777d">nakilab.com</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function nk_fields_html(array $fields): string {
    if (!$fields) return '';
    $rows = '';
    foreach ($fields as $label => $v) {
        $rows .= '<tr><td style="padding:8px 12px 8px 0;vertical-align:top;font-size:13px;color:#77777d;white-space:nowrap">' . nk_h($label) . '</td>'
            . '<td style="padding:8px 0;font-size:14px;color:#111">' . nl2br(nk_h($v)) . '</td></tr>';
    }
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:8px 0 0;border-top:1px solid #e6e6e8">' . $rows . '</table>';
}

function nk_fields_text(array $fields): string {
    $t = '';
    foreach ($fields as $label => $v) $t .= "$label: $v\n";
    return $t;
}

/** Confirmation sent to the person who filled in the form. */
function nk_email_confirmation(string $type, string $name, array $details): array {
    $first = trim(explode(' ', trim($name))[0] ?? '') ?: 'there';
    $fields = nk_fields_list($details);
    $copy = [
        'have' => [
            'subject' => 'Nakilab: We received your idea',
            'intro' => 'Thank you for sending us' . (!empty($details['title']) ? ' "' . $details['title'] . '"' : ' your idea') . '. It is now in our review queue.',
            'steps' => ['We evaluate your idea with SI, Super Intelligence: market, risks and numbers.', 'If it is a fit, we contact you to plan the build together.', 'Either way, we reply to you personally.'],
        ],
        'need' => [
            'subject' => 'Nakilab: Your idea request is in',
            'intro' => 'Thank you for asking us for an idea. We will look at your skills, interests and budget.',
            'steps' => ['We review your profile.', 'We look for an idea from our lab that fits you.', 'We contact you with a match or with questions.'],
        ],
        'invest' => [
            'subject' => 'Thank you for your interest in Nakilab',
            'intro' => 'Thank you for reaching out about investing or partnering with Nakilab.',
            'steps' => ['We read your message.', 'One of the founders replies to you personally.'],
        ],
    ][$type];

    $stepsHtml = '';
    foreach ($copy['steps'] as $i => $s) {
        $stepsHtml .= '<tr><td style="padding:6px 12px 6px 0;vertical-align:top;font-weight:bold;color:#000000;font-family:Menlo,Consolas,monospace;font-size:14px">0' . ($i + 1) . '</td><td style="padding:6px 0;font-size:15px">' . nk_h($s) . '</td></tr>';
    }
    $html = nk_email_layout($copy['intro'],
        '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.2;text-transform:uppercase;letter-spacing:.5px">Received</h1>'
        . '<p style="margin:0 0 18px">Hi ' . nk_h($first) . ',</p>'
        . '<p style="margin:0 0 24px">' . nk_h($copy['intro']) . '</p>'
        . '<p style="margin:0 0 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#77777d">What happens next</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px">' . $stepsHtml . '</table>'
        . ($fields ? '<p style="margin:0 0 4px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#77777d">What you sent</p>' . nk_fields_html($fields) : '')
        . '<p style="margin:28px 0 0">Want to add something? Just reply to this email.</p>'
        . '<p style="margin:18px 0 0">Mehrdad &amp; Navid<br><span style="color:#77777d">Co-Founders, Nakilab</span></p>');

    $text = "Hi $first,\n\n{$copy['intro']}\n\nWHAT HAPPENS NEXT\n";
    foreach ($copy['steps'] as $i => $s) $text .= '0' . ($i + 1) . "  $s\n";
    if ($fields) $text .= "\nWHAT YOU SENT\n" . nk_fields_text($fields);
    $text .= "\nWant to add something? Just reply to this email.\n\nMehrdad & Navid\nCo-Founders, Nakilab\nhttps://nakilab.com\n";
    return [$copy['subject'], $text, $html];
}

/** Internal alert to the Nakilab team. */
function nk_email_alert(int $id, string $type, string $name, string $email, array $details): array {
    $fields = ['Type' => NK_TYPE_LABELS[$type] ?? $type, 'Name' => $name, 'Email' => $email] + nk_fields_list($details);
    $headline = $details['title'] ?? ($details['org'] ?? $name);
    $subject = 'New submission #' . $id . ' / ' . (NK_TYPE_LABELS[$type] ?? $type) . ': ' . mb_substr((string) $headline, 0, 60);
    $html = nk_email_layout('New ' . (NK_TYPE_LABELS[$type] ?? $type) . ' submission from ' . $name,
        '<h1 style="margin:0 0 18px;font-size:22px;line-height:1.2;text-transform:uppercase">New submission #' . $id . '</h1>'
        . nk_fields_html($fields)
        . '<p style="margin:24px 0 0;font-size:14px;color:#77777d">Reply to this email to answer ' . nk_h($name) . ' directly. Saved in the database (submissions, id ' . $id . ').</p>');
    $text = "New submission #$id\n\n" . nk_fields_text($fields) . "\nReply to this email to answer $name directly.\n";
    return [$subject, $text, $html];
}
