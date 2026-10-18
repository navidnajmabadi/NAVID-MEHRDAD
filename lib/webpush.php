<?php
declare(strict_types=1);

/**
 * Pure-PHP Web Push (VAPID + aes128gcm) — no Composer.
 * Requires OpenSSL (ECDH P-256, AES-128-GCM) and curl.
 * Specs: RFC 8291 (Message Encryption), RFC 8292 (VAPID).
 */

function rrp_b64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function rrp_b64url_decode(string $b64): string
{
    $pad = 4 - (strlen($b64) % 4);
    if ($pad < 4) {
        $b64 .= str_repeat('=', $pad);
    }
    $raw = base64_decode(strtr($b64, '-_', '+/'), true);
    if ($raw === false) {
        throw new InvalidArgumentException('Invalid base64url');
    }
    return $raw;
}

function rrp_vapid_configured(): bool
{
    $vapid = rrp_load_config()['vapid'] ?? [];
    return !empty($vapid['public_key']) && !empty($vapid['private_key']);
}

function rrp_vapid_public_key(): string
{
    return (string) ((rrp_load_config()['vapid'] ?? [])['public_key'] ?? '');
}

/**
 * Build OpenSSL EC private key resource from VAPID base64url private (32 bytes) + public (65 bytes uncompressed).
 * @return \OpenSSLAsymmetricKey|resource
 */
function rrp_vapid_openssl_private(string $privateB64, string $publicB64)
{
    $d = rrp_b64url_decode($privateB64);
    $pub = rrp_b64url_decode($publicB64);
    if (strlen($d) !== 32) {
        throw new InvalidArgumentException('VAPID private key must be 32 bytes');
    }
    if (strlen($pub) !== 65 || $pub[0] !== "\x04") {
        throw new InvalidArgumentException('VAPID public key must be 65-byte uncompressed P-256');
    }

    // PKCS#8 EC private key for prime256v1 with public key included.
    $pkcs8 = hex2bin(
        '308187020100301306072a8648ce3d020106082a8648ce3d030107046d306b0201010420'
    );
    if ($pkcs8 === false) {
        throw new RuntimeException('hex2bin failed');
    }
    $pkcs8 .= $d;
    $pkcs8 .= hex2bin('a144034200');
    $pkcs8 .= $pub;

    $pem = "-----BEGIN PRIVATE KEY-----\n"
        . chunk_split(base64_encode($pkcs8), 64, "\n")
        . "-----END PRIVATE KEY-----\n";

    $key = openssl_pkey_get_private($pem);
    if ($key === false) {
        throw new RuntimeException('Could not load VAPID private key: ' . (openssl_error_string() ?: 'unknown'));
    }
    return $key;
}

/**
 * @return \OpenSSLAsymmetricKey|resource
 */
function rrp_ec_public_from_raw(string $uncompressed65)
{
    if (strlen($uncompressed65) !== 65 || $uncompressed65[0] !== "\x04") {
        throw new InvalidArgumentException('Expected uncompressed P-256 public key');
    }
    // SubjectPublicKeyInfo for prime256v1
    $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    if ($spki === false) {
        throw new RuntimeException('hex2bin failed');
    }
    $spki .= $uncompressed65;
    $pem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($spki), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
    $key = openssl_pkey_get_public($pem);
    if ($key === false) {
        throw new RuntimeException('Could not load EC public key');
    }
    return $key;
}

/**
 * Convert OpenSSL ECDSA DER signature to JWT raw r||s (64 bytes).
 */
function rrp_ecdsa_der_to_jose(string $der): string
{
    $offset = 0;
    $readLen = static function (string $bin, int &$off): int {
        $b = ord($bin[$off++]);
        if (($b & 0x80) === 0) {
            return $b;
        }
        $n = $b & 0x7f;
        $len = 0;
        for ($i = 0; $i < $n; $i++) {
            $len = ($len << 8) | ord($bin[$off++]);
        }
        return $len;
    };

    if (($der[$offset++] ?? '') !== "\x30") {
        throw new RuntimeException('Bad ECDSA DER');
    }
    $readLen($der, $offset);
    if (($der[$offset++] ?? '') !== "\x02") {
        throw new RuntimeException('Bad ECDSA DER r');
    }
    $rLen = $readLen($der, $offset);
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;
    if (($der[$offset++] ?? '') !== "\x02") {
        throw new RuntimeException('Bad ECDSA DER s');
    }
    $sLen = $readLen($der, $offset);
    $s = substr($der, $offset, $sLen);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
    if (strlen($r) !== 32 || strlen($s) !== 32) {
        throw new RuntimeException('ECDSA component wrong length');
    }
    return $r . $s;
}

function rrp_vapid_jwt(string $endpoint, array $vapid): string
{
    $parts = parse_url($endpoint);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        throw new InvalidArgumentException('Bad push endpoint');
    }
    $aud = $parts['scheme'] . '://' . $parts['host'];
    $sub = (string) ($vapid['subject'] ?? 'mailto:noreply@nakilab.com');
    if ($sub === '') {
        $sub = 'mailto:noreply@nakilab.com';
    }

    $header = rrp_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $claims = rrp_b64url_encode(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => $sub,
    ], JSON_UNESCAPED_SLASHES));
    $signingInput = $header . '.' . $claims;

    $key = rrp_vapid_openssl_private((string) $vapid['private_key'], (string) $vapid['public_key']);
    $derSig = '';
    if (!openssl_sign($signingInput, $derSig, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID openssl_sign failed');
    }
    $jose = rrp_ecdsa_der_to_jose($derSig);
    return $signingInput . '.' . rrp_b64url_encode($jose);
}

/**
 * Encrypt payload per RFC 8291 (aes128gcm) for a Push subscription.
 */
function rrp_webpush_encrypt(string $payload, string $p256dhB64, string $authB64): string
{
    $uaPublic = rrp_b64url_decode($p256dhB64);
    $authSecret = rrp_b64url_decode($authB64);
    if (strlen($authSecret) !== 16) {
        throw new InvalidArgumentException('auth must be 16 bytes');
    }

    $asKey = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($asKey === false) {
        throw new RuntimeException('Could not generate ephemeral ECDH key');
    }
    $details = openssl_pkey_get_details($asKey);
    if ($details === false || empty($details['ec']['x']) || empty($details['ec']['y'])) {
        throw new RuntimeException('Could not read ephemeral public key');
    }
    $asPublic = "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    $uaKey = rrp_ec_public_from_raw($uaPublic);
    $shared = openssl_pkey_derive($uaKey, $asKey, 32);
    if ($shared === false || strlen($shared) !== 32) {
        // Some OpenSSL builds return shorter; left-pad
        if (is_string($shared) && $shared !== '') {
            $shared = str_pad($shared, 32, "\x00", STR_PAD_LEFT);
        } else {
            throw new RuntimeException('ECDH derive failed: ' . (openssl_error_string() ?: 'unknown'));
        }
    }

    // RFC 8291: Extract(auth_secret, ecdh) → Expand("WebPush: info\0"+ua+as)
    // then Extract(salt, ikm) → Expand content-encoding labels for CEK/nonce.
    $keyInfo = "WebPush: info\0" . $uaPublic . $asPublic;
    $ikm = hash_hkdf('sha256', $shared, 32, $keyInfo, $authSecret);

    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

    // One record: plaintext + padding delimiter 0x02
    $plaintext = $payload . "\x02";
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('AES-GCM encrypt failed');
    }

    $rs = 4096;
    $idlen = strlen($asPublic); // 65
    $header = $salt . pack('N', $rs) . chr($idlen) . $asPublic;
    return $header . $cipher . $tag;
}

/**
 * Send one Web Push notification.
 * @return array{ok:bool, status?:int, error?:string, gone?:bool}
 */
function rrp_webpush_send(array $sub, string $title, string $body, ?string $url = null): array
{
    $vapid = rrp_load_config()['vapid'] ?? [];
    if (empty($vapid['public_key']) || empty($vapid['private_key'])) {
        return ['ok' => false, 'error' => 'vapid_not_configured'];
    }

    $endpoint = (string) ($sub['endpoint'] ?? '');
    $p256dh = (string) ($sub['p256dh'] ?? '');
    $auth = (string) ($sub['auth'] ?? '');
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return ['ok' => false, 'error' => 'incomplete_subscription'];
    }

    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url ?: rrp_app_url('dashboard.php'),
        'icon' => rrp_app_url('assets/icons/icon-192.png'),
        'badge' => rrp_app_url('assets/icons/icon-192.png'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'json_encode_failed'];
    }

    try {
        $encrypted = rrp_webpush_encrypt($payload, $p256dh, $auth);
        $jwt = rrp_vapid_jwt($endpoint, $vapid);
        $vapidPub = (string) $vapid['public_key'];
    } catch (Throwable $e) {
        error_log('rrp_webpush_send crypto: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'crypto_failed'];
    }

    $headers = [
        'Authorization: vapid t=' . $jwt . ', k=' . $vapidPub,
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
        'Urgency: high',
    ];

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl_missing'];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encrypted,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false && $status === 0) {
        error_log('rrp_webpush_send curl: ' . $cerr);
        return ['ok' => false, 'error' => 'curl_failed'];
    }

    // 201 Created / 200 OK = success; 410/404 = subscription gone
    if ($status === 201 || $status === 200) {
        return ['ok' => true, 'status' => $status];
    }
    if ($status === 410 || $status === 404) {
        return ['ok' => false, 'status' => $status, 'gone' => true, 'error' => 'subscription_gone'];
    }

    error_log('rrp_webpush_send HTTP ' . $status . ' body=' . substr((string) $resp, 0, 200));
    return ['ok' => false, 'status' => $status, 'error' => 'push_rejected'];
}

/**
 * Generate a new VAPID key pair (CLI / docs helper).
 * @return array{public_key:string, private_key:string}
 */
function rrp_vapid_generate_keys(): array
{
    $key = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($key === false) {
        throw new RuntimeException('openssl_pkey_new failed');
    }
    $details = openssl_pkey_get_details($key);
    if ($details === false || empty($details['ec']['d']) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
        throw new RuntimeException('Could not export EC key details');
    }
    $d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
    $pub = "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    return [
        'public_key' => rrp_b64url_encode($pub),
        'private_key' => rrp_b64url_encode($d),
    ];
}
