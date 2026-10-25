<?php
declare(strict_types=1);

/**
 * Email delivery: PHP mail() by default; optional SMTP via sockets (no Composer).
 */
function rrp_send_mail(string $to, string $subject, string $bodyText): bool
{
    $cfg = rrp_load_config();
    $from = $cfg['mail_from'] ?? 'noreply@nakilab.com';
    $fromName = $cfg['mail_from_name'] ?? 'siPaw';
    $smtp = $cfg['smtp'] ?? ['enabled' => false];

    if (!empty($smtp['enabled'])) {
        return rrp_smtp_send($smtp, $from, $fromName, $to, $subject, $bodyText);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . rrp_mail_encode_address($fromName, $from),
        'Reply-To: ' . $from,
        'X-Mailer: siPaw/1',
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyText, implode("\r\n", $headers));
}

function rrp_mail_encode_address(string $name, string $email): string
{
    return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
}

function rrp_smtp_send(array $smtp, string $from, string $fromName, string $to, string $subject, string $body): bool
{
    $host = $smtp['host'] ?? '';
    $port = (int) ($smtp['port'] ?? 465);
    $enc = $smtp['encryption'] ?? 'ssl';
    $user = $smtp['user'] ?? '';
    $pass = $smtp['pass'] ?? '';

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20);
    if (!$fp) {
        error_log("RRP SMTP connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $line) use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        return $read();
    };

    $read();
    $ehloHost = parse_url(rrp_app_url(), PHP_URL_HOST) ?: 'localhost';
    $cmd('EHLO ' . $ehloHost);

    if ($enc === 'tls') {
        $cmd('STARTTLS');
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $cmd('EHLO ' . $ehloHost);
    }

    if ($user !== '') {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($user));
        $cmd(base64_encode($pass));
    }

    $cmd('MAIL FROM:<' . $from . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');

    $msg = 'From: ' . rrp_mail_encode_address($fromName, $from) . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . $body . "\r\n.";

    $cmd($msg);
    $cmd('QUIT');
    fclose($fp);
    return true;
}
