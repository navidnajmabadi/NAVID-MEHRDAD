<?php
declare(strict_types=1);

/**
 * Plus bought inside the iPhone app (Apple In-App Purchase, StoreKit 2).
 *
 * The app sends the signed transaction (a JWS) Apple produced; we never trust the app's word. The JWS is checked here:
 *  1. its x5c certificate chain ends in Apple Root CA - G3 (pinned by SHA-256) and every certificate is valid,
 *  2. its ES256 signature matches the leaf certificate,
 *  3. bundle id, product id and the account token (a UUID only this server can compute for an owner) are ours,
 * then Plus is extended to the transaction's expiry date. Apple's server notifications (api/apple/notify.php) use the same
 * checks to follow renewals, expiry and refunds.
 */

/**
 * True inside the SIPAW iPhone app (its web screens send a "SIPAWApp" user agent). Apple requires Plus to be bought there
 * with In-App Purchase, so the website's card and Apple Pay payment must not be offered or accepted in the app.
 */
function rrp_is_ios_app(): bool
{
    return stripos((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'SIPAWApp') !== false;
}

/** Apple Root CA - G3, SHA-256 of the DER certificate. A test config can replace it ('apple' => ['root_sha256' => ...]). */
const RRP_APPLE_ROOT_G3_SHA256 = '63343abfb89a6a03ebb57e9b3f5fa7be7c4f5c756f3017b3a8c488c3653e9179';

function rrp_apple_cfg(): array
{
    $c = rrp_config_ready() ? (rrp_load_config()['apple'] ?? []) : [];
    $products = array_values(array_filter(array_map('trim', (array) ($c['product_ids'] ?? ['com.nakilab.sipaw.plus.monthly']))));
    return [
        'bundle_id' => trim((string) ($c['bundle_id'] ?? 'com.nakilab.sipaw')),
        'product_ids' => $products,
        'root_sha256' => strtolower(trim((string) ($c['root_sha256'] ?? RRP_APPLE_ROOT_G3_SHA256))),
        'secret' => (string) ($c['account_secret'] ?? (rrp_config_ready() ? (rrp_load_config()['cron_secret'] ?? '') : '')),
    ];
}

/** The UUID the app must pass as appAccountToken when it buys for this owner (binds the purchase to the account). */
function rrp_apple_account_token(int $ownerId): string
{
    $h = hash_hmac('sha256', 'sipaw-apple-account-' . $ownerId, rrp_apple_cfg()['secret'] . '|sipaw');
    $b = hex2bin(substr($h, 0, 32));
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $x = bin2hex($b);
    return strtoupper(sprintf('%s-%s-%s-%s-%s', substr($x, 0, 8), substr($x, 8, 4), substr($x, 12, 4), substr($x, 16, 4), substr($x, 20)));
}

function rrp_apple_b64url_decode(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

function rrp_apple_der_to_pem(string $der): string
{
    return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
}

/** ES256 signature (raw R||S, 64 bytes) to the DER form OpenSSL wants. */
function rrp_apple_sig_to_der(string $raw): ?string
{
    if (strlen($raw) !== 64) {
        return null;
    }
    $int = static function (string $n): string {
        $n = ltrim($n, "\x00");
        if ($n === '' || (ord($n[0]) & 0x80)) {
            $n = "\x00" . $n;
        }
        return "\x02" . chr(strlen($n)) . $n;
    };
    $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
    return "\x30" . chr(strlen($body)) . $body;
}

/**
 * Verify an Apple-signed JWS and return its payload.
 * @return array<string,mixed>|null null when anything about it is wrong
 */
function rrp_apple_verify_jws(string $jws): ?array
{
    $p = explode('.', trim($jws));
    if (count($p) !== 3) {
        return null;
    }
    $header = json_decode(rrp_apple_b64url_decode($p[0]), true);
    $payload = json_decode(rrp_apple_b64url_decode($p[1]), true);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'ES256' || empty($header['x5c']) || !is_array($header['x5c']) || count($header['x5c']) < 2 || count($header['x5c']) > 4) {
        return null;
    }
    $ders = [];
    foreach ($header['x5c'] as $c) {
        $d = base64_decode((string) $c, true);
        if ($d === false || $d === '') {
            return null;
        }
        $ders[] = $d;
    }
    // 1) The last certificate must be Apple's root (pinned), and the chain must verify up to it.
    if (!hash_equals(rrp_apple_cfg()['root_sha256'], hash('sha256', end($ders)))) {
        return null;
    }
    $leaf = rrp_apple_der_to_pem($ders[0]);
    $root = rrp_apple_der_to_pem(end($ders));
    $mid = '';
    for ($i = 1; $i < count($ders) - 1; $i++) {
        $mid .= rrp_apple_der_to_pem($ders[$i]);
    }
    $rootFile = tempnam(sys_get_temp_dir(), 'apr');
    $midFile = tempnam(sys_get_temp_dir(), 'apm');
    try {
        file_put_contents($rootFile, $root);
        file_put_contents($midFile, $mid !== '' ? $mid : $root);
        if (openssl_x509_checkpurpose($leaf, X509_PURPOSE_ANY, [$rootFile], $midFile) !== true) {
            return null;
        }
    } finally {
        @unlink($rootFile);
        @unlink($midFile);
    }
    // 2) The signature is the leaf certificate's.
    $key = openssl_pkey_get_public($leaf);
    $der = rrp_apple_sig_to_der(rrp_apple_b64url_decode($p[2]));
    if (!$key || $der === null || openssl_verify($p[0] . '.' . $p[1], $der, $key, OPENSSL_ALGO_SHA256) !== 1) {
        return null;
    }
    return $payload;
}

function rrp_apple_ms(mixed $ms): ?string
{
    return is_numeric($ms) && (int) $ms > 0 ? gmdate('Y-m-d H:i:s', intdiv((int) $ms, 1000)) : null;
}

/**
 * Check one signed transaction and, if it is a live Plus subscription for this owner, extend Plus.
 * @return array{ok:bool, error?:string, expires?:?string, plan?:string}
 */
function rrp_apple_apply_transaction(int $ownerId, string $jws): array
{
    $t = rrp_apple_verify_jws($jws);
    if (!$t) {
        return ['ok' => false, 'error' => 'The purchase could not be verified.'];
    }
    $cfg = rrp_apple_cfg();
    if (($t['bundleId'] ?? '') !== $cfg['bundle_id'] || !in_array((string) ($t['productId'] ?? ''), $cfg['product_ids'], true)) {
        return ['ok' => false, 'error' => 'This purchase is not for SIPAW Plus.'];
    }
    if (strtoupper((string) ($t['appAccountToken'] ?? '')) !== rrp_apple_account_token($ownerId)) {
        return ['ok' => false, 'error' => 'This purchase belongs to another account.'];
    }
    return rrp_apple_store($ownerId, $t);
}

/** Save a verified transaction and set the owner's Plus period from it. */
function rrp_apple_store(int $ownerId, array $t): array
{
    $pdo = rrp_db();
    $tid = substr((string) ($t['transactionId'] ?? ''), 0, 40);
    $orig = substr((string) ($t['originalTransactionId'] ?? $tid), 0, 40);
    if ($tid === '') {
        return ['ok' => false, 'error' => 'The purchase has no number.'];
    }
    $expires = rrp_apple_ms($t['expiresDate'] ?? null);
    $revoked = rrp_apple_ms($t['revocationDate'] ?? null);
    $pdo->prepare(
        'INSERT INTO apple_purchases (transaction_id, owner_id, original_transaction_id, product_id, environment, purchased_at, expires_at, revoked_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), revoked_at = VALUES(revoked_at)'
    )->execute([$tid, $ownerId, $orig, substr((string) ($t['productId'] ?? ''), 0, 120), substr((string) ($t['environment'] ?? 'Production'), 0, 12),
        rrp_apple_ms($t['purchaseDate'] ?? null), $expires, $revoked]);
    $pdo->prepare('UPDATE owners SET apple_original_tx = ? WHERE id = ?')->execute([$orig, $ownerId]);
    return rrp_apple_recompute($ownerId);
}

/** Plus follows the latest verified, not-refunded Apple expiry; a refund or lapse never shortens a period paid another way. */
function rrp_apple_recompute(int $ownerId): array
{
    $pdo = rrp_db();
    $st = $pdo->prepare('SELECT MAX(expires_at) FROM apple_purchases WHERE owner_id = ? AND revoked_at IS NULL AND expires_at IS NOT NULL');
    $st->execute([$ownerId]);
    $exp = $st->fetchColumn() ?: null;
    $cur = $pdo->query('SELECT plan, plan_expires_at FROM owners WHERE id = ' . $ownerId)->fetch() ?: [];
    if ($exp && strtotime((string) $exp . ' UTC') > time()) {
        $curExp = $cur['plan_expires_at'] ?? null;
        $set = ($curExp && strtotime((string) $curExp . ' UTC') > strtotime((string) $exp . ' UTC')) ? (string) $curExp : (string) $exp;
        $pdo->prepare("UPDATE owners SET plan = 'plus', plan_expires_at = ?, subscription_status = 'active' WHERE id = ?")->execute([$set, $ownerId]);
        return ['ok' => true, 'plan' => 'plus', 'expires' => $set];
    }
    return ['ok' => true, 'plan' => (string) ($cur['plan'] ?? 'free'), 'expires' => $cur['plan_expires_at'] ?? null];
}

/** A refund (revoked transaction) takes the Apple-bought time away now. */
function rrp_apple_revoke(int $ownerId, string $originalTx): void
{
    $pdo = rrp_db();
    $pdo->prepare('UPDATE apple_purchases SET revoked_at = UTC_TIMESTAMP() WHERE owner_id = ? AND original_transaction_id = ? AND revoked_at IS NULL')->execute([$ownerId, $originalTx]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM billing_orders WHERE owner_id = ? AND status = \'paid\'');
    $st->execute([$ownerId]);
    if ((int) $st->fetchColumn() === 0) {   // nothing paid on the website: Plus ends now
        $pdo->prepare("UPDATE owners SET plan_expires_at = UTC_TIMESTAMP(), subscription_status = 'canceled' WHERE id = ?")->execute([$ownerId]);
    }
}
