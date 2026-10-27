<?php
declare(strict_types=1);

/**
 * Email delivery: optional SMTP via sockets (no Composer).
 * When smtp.enabled, failures return structured detail for Hostinger ops.
 */

/**
 * @return array{ok:bool, error?:string, via?:string}
 */
function rrp_send_mail_result(string $to, string $subject, string $bodyText): array
{
    $cfg = rrp_load_config();
    $from = (string) ($cfg['mail_from'] ?? 'noreply@nakilab.com');
    $fromName = (string) ($cfg['mail_from_name'] ?? 'SIPAW');
    $smtp = $cfg['smtp'] ?? ['enabled' => false];

    if (!empty($smtp['enabled'])) {
        $host = trim((string) ($smtp['host'] ?? ''));
        $user = trim((string) ($smtp['user'] ?? ''));
        $pass = (string) ($smtp['pass'] ?? '');
        if ($host === '' || $user === '' || $pass === '' || str_starts_with($pass, 'CHANGE_ME')) {
            return [
                'ok' => false,
                'error' => 'SMTP enabled but host/user/pass incomplete in config/sipaw.php. Fill Hostinger SMTP credentials, then retry.',
                'via' => 'smtp',
            ];
        }

        $sent = rrp_smtp_send($smtp, $from, $fromName, $to, $subject, $bodyText);
        if (empty($sent['ok'])) {
            return [
                'ok' => false,
                'error' => $sent['error'] ?? 'SMTP send failed. Check Hostinger SMTP host/port/user/pass and mail_from.',
                'via' => 'smtp',
            ];
        }
        return ['ok' => true, 'via' => 'smtp'];
    }

    // Legacy PHP mail() for non-reset alerts when SMTP is off.
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . rrp_mail_encode_address($fromName, $from),
        'Reply-To: ' . $from,
        'X-Mailer: SIPAW/1',
    ];
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyText, implode("\r\n", $headers));
    if (!$ok) {
        return [
            'ok' => false,
            'error' => 'PHP mail() failed. Prefer smtp.enabled=true with Hostinger SMTP in config/sipaw.php.',
            'via' => 'mail',
        ];
    }
    return ['ok' => true, 'via' => 'mail'];
}

/** @deprecated prefer rrp_send_mail_result — bool for legacy callers */
function rrp_send_mail(string $to, string $subject, string $bodyText): bool
{
    return !empty(rrp_send_mail_result($to, $subject, $bodyText)['ok']);
}

function rrp_mail_encode_address(string $name, string $email): string
{
    return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_smtp_send(array $smtp, string $from, string $fromName, string $to, string $subject, string $body): array
{
    $host = $smtp['host'] ?? '';
    $port = (int) ($smtp['port'] ?? 465);
    $enc = strtolower((string) ($smtp['encryption'] ?? 'ssl'));
    $user = $smtp['user'] ?? '';
    $pass = $smtp['pass'] ?? '';

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20);
    if (!$fp) {
        error_log("RRP SMTP connect failed: $errstr ($errno)");
        return ['ok' => false, 'error' => "SMTP connect failed: {$errstr} ({$errno}). Check smtp.host/port/encryption."];
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
    $expectOk = static function (string $resp, string $step): ?string {
        $code = (int) substr($resp, 0, 3);
        if ($code >= 200 && $code < 400) {
            return null;
        }
        $snip = trim(preg_replace('/\s+/', ' ', $resp) ?? $resp);
        return "SMTP {$step} failed ({$code}): " . substr($snip, 0, 180);
    };

    $banner = $read();
    if ($err = $expectOk($banner, 'banner')) {
        fclose($fp);
        return ['ok' => false, 'error' => $err];
    }

    $ehloHost = parse_url(rrp_app_url(), PHP_URL_HOST) ?: 'localhost';
    $ehlo = $cmd('EHLO ' . $ehloHost);
    if ($err = $expectOk($ehlo, 'EHLO')) {
        fclose($fp);
        return ['ok' => false, 'error' => $err];
    }

    if ($enc === 'tls') {
        $tls = $cmd('STARTTLS');
        if ($err = $expectOk($tls, 'STARTTLS')) {
            fclose($fp);
            return ['ok' => false, 'error' => $err];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'SMTP STARTTLS crypto failed.'];
        }
        $ehlo2 = $cmd('EHLO ' . $ehloHost);
        if ($err = $expectOk($ehlo2, 'EHLO after TLS')) {
            fclose($fp);
            return ['ok' => false, 'error' => $err];
        }
    }

    if ($user !== '') {
        $a1 = $cmd('AUTH LOGIN');
        if ($err = $expectOk($a1, 'AUTH LOGIN')) {
            fclose($fp);
            return ['ok' => false, 'error' => $err];
        }
        $a2 = $cmd(base64_encode($user));
        if ($err = $expectOk($a2, 'AUTH user')) {
            fclose($fp);
            return ['ok' => false, 'error' => $err];
        }
        $a3 = $cmd(base64_encode($pass));
        if ($err = $expectOk($a3, 'AUTH pass')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'SMTP auth failed — check smtp.user / smtp.pass in config/sipaw.php.'];
        }
    }

    $mf = $cmd('MAIL FROM:<' . $from . '>');
    if ($err = $expectOk($mf, 'MAIL FROM')) {
        fclose($fp);
        return ['ok' => false, 'error' => $err . ' — set mail_from to an allowed Hostinger mailbox.'];
    }
    $rt = $cmd('RCPT TO:<' . $to . '>');
    if ($err = $expectOk($rt, 'RCPT TO')) {
        fclose($fp);
        return ['ok' => false, 'error' => $err];
    }
    $data = $cmd('DATA');
    if ($err = $expectOk($data, 'DATA')) {
        fclose($fp);
        return ['ok' => false, 'error' => $err];
    }

    $msg = 'From: ' . rrp_mail_encode_address($fromName, $from) . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . $body . "\r\n.";

    $bodyResp = $cmd($msg);
    if ($err = $expectOk($bodyResp, 'message')) {
        fclose($fp);
        return ['ok' => false, 'error' => $err];
    }
    $cmd('QUIT');
    fclose($fp);
    return ['ok' => true];
}
