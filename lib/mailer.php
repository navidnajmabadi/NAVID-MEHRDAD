<?php
// Nakilab — minimal SMTP mailer (SSL on port 465), no external libraries.
// Settings come from config/mail.php (outside the web root):
//   ['host' => 'smtp.hostinger.com', 'port' => 465, 'user' => 'support@nakilab.com', 'pass_b64' => '…',
//    'from_email' => 'support@nakilab.com', 'from_name' => 'Nakilab', 'alert_to' => 'support@nakilab.com']
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function nk_mail_configured(): bool {
    $c = nk_config('mail');
    return !empty($c['host']) && !empty($c['user']) && !empty($c['pass_b64']);
}

/**
 * Send one email (plain text + HTML). Throws RuntimeException on any SMTP failure.
 * $opts: reply_to (email), reply_to_name.
 */
function nk_send_mail(string $toEmail, string $toName, string $subject, string $text, string $html, array $opts = []): void {
    $c = nk_config('mail');
    if (!nk_mail_configured()) throw new RuntimeException('Mail is not configured.');
    $clean = fn(string $s) => trim(str_replace(["\r", "\n"], ' ', $s));
    $toEmail = $clean($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid recipient.');
    $from = $clean((string) ($c['from_email'] ?? $c['user']));
    $fromName = $clean((string) ($c['from_name'] ?? 'Nakilab'));
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $addr = fn(string $name, string $email) => ($name !== '' ? $enc($clean($name)) . ' ' : '') . "<$email>";

    $boundary = 'nk-' . bin2hex(random_bytes(12));
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . $addr($fromName, $from),
        'To: ' . $addr($toName, $toEmail),
        'Subject: ' . $enc($clean($subject)),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@nakilab.com>',
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
    ];
    if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $addr((string) ($opts['reply_to_name'] ?? ''), $clean($opts['reply_to']));
    }
    $part = fn(string $type, string $body) => "--$boundary\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $part('text/plain', $text) . $part('text/html', $html) . "--$boundary--\r\n";

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $host = (string) $c['host']; $port = (int) ($c['port'] ?? 465);
    $fp = @stream_socket_client("ssl://$host:$port", $errno, $err, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $err");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): string {
        $all = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $all .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break; // last line of a (multi-line) reply
        }
        return $all;
    };
    $expect = function (string $reply, array $codes, string $step) use ($fp) {
        if (!in_array((int) substr($reply, 0, 3), $codes, true)) {
            fclose($fp);
            throw new RuntimeException("SMTP $step failed: " . trim(substr($reply, 0, 200)));
        }
    };
    $cmd = function (string $line) use ($fp, $read): string { fwrite($fp, $line . "\r\n"); return $read(); };

    $expect($read(), [220], 'greeting');
    $expect($cmd('EHLO nakilab.com'), [250], 'EHLO');
    $expect($cmd('AUTH LOGIN'), [334], 'AUTH');
    $expect($cmd(base64_encode((string) $c['user'])), [334], 'AUTH user');
    $expect($cmd(base64_encode(base64_decode((string) $c['pass_b64']))), [235], 'AUTH password');
    $expect($cmd("MAIL FROM:<$from>"), [250], 'MAIL FROM');
    $expect($cmd("RCPT TO:<$toEmail>"), [250, 251], 'RCPT TO');
    $expect($cmd('DATA'), [354], 'DATA');
    // dot-stuffing: a line starting with "." must be doubled
    $expect($cmd(preg_replace('/^\./m', '..', rtrim($message, "\r\n")) . "\r\n."), [250], 'send');
    $cmd('QUIT');
    fclose($fp);
}
