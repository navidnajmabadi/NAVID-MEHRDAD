<?php
declare(strict_types=1);

/**
 * Forgot-password via SMTP (config smtp.*). Tokens hashed at rest.
 * Failures surface Hostinger-facing errors (no silent “success” when mail did not send).
 */

function rrp_password_reset_request(string $email): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }

    try {
        $stmt = rrp_db()->prepare('SELECT id, name, email, status FROM owners WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $owner = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('rrp_password_reset_request: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not start reset. Try again shortly.'];
    }

    // Validate SMTP readiness first so Navid sees Hostinger errors (not silent success).
    $smtpReady = rrp_password_reset_smtp_ready();
    if (empty($smtpReady['ok'])) {
        return [
            'ok' => false,
            'error' => $smtpReady['error'] ?? 'SMTP not ready.',
            'gate' => 'smtp_config',
        ];
    }

    if (!$owner || ($owner['status'] ?? '') !== 'active') {
        // Safe generic after SMTP is known working — don't leak account existence.
        return [
            'ok' => true,
            'message' => 'If that email is registered, we sent a reset link. Check your inbox (and spam).',
        ];
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 hours')->format('Y-m-d H:i:s');

    try {
        $inv = rrp_db()->prepare(
            'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE owner_id = ? AND used_at IS NULL'
        );
        $inv->execute([(int) $owner['id']]);

        $ins = rrp_db()->prepare(
            'INSERT INTO password_reset_tokens (owner_id, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $ins->execute([(int) $owner['id'], $hash, $expires]);
    } catch (Throwable $e) {
        error_log('rrp_password_reset_request insert: ' . $e->getMessage());
        return [
            'ok' => false,
            'error' => 'Password reset tables missing — import sql/008_final_super_forgot_brain.sql in phpMyAdmin.',
            'gate' => 'missing_table',
        ];
    }

    $url = rtrim(rrp_app_url(), '/') . '/reset-password.php?token=' . rawurlencode($token);
    $name = trim((string) ($owner['name'] ?? 'there'));
    $body = "Hi {$name},\n\n"
        . "Reset your SIPAW password with this link (expires in 2 hours):\n\n"
        . "{$url}\n\n"
        . "If you did not ask for this, ignore this email.\n\n"
        . "— SIPAW by NAKILAB\n";

    $sent = rrp_send_mail_result($email, 'Reset your SIPAW password', $body);
    if (empty($sent['ok'])) {
        error_log('rrp_password_reset_request: mail failed for ' . $email . ' — ' . ($sent['error'] ?? ''));
        return [
            'ok' => false,
            'error' => $sent['error'] ?? 'Could not send reset email. Fix SMTP in Hostinger config/sipaw.php.',
            'gate' => 'smtp_send',
        ];
    }

    return [
        'ok' => true,
        'message' => 'Reset link sent. Check your inbox (and spam). Link expires in 2 hours.',
    ];
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_password_reset_smtp_ready(): array
{
    if (!rrp_config_ready()) {
        return ['ok' => false, 'error' => 'App config missing.'];
    }
    $cfg = rrp_load_config();
    $smtp = $cfg['smtp'] ?? [];
    if (empty($smtp['enabled'])) {
        return [
            'ok' => false,
            'error' => 'SMTP is off. In /home/u233722910/domains/nakilab.com/config/sipaw.php set smtp.enabled=true and fill host/user/pass (Hostinger), then retry. Or set a password via CLI: php scripts/set-owner-password.php you@email.com \'NewPass\'',
        ];
    }
    $host = trim((string) ($smtp['host'] ?? ''));
    $user = trim((string) ($smtp['user'] ?? ''));
    $pass = (string) ($smtp['pass'] ?? '');
    if ($host === '' || $user === '' || $pass === '' || str_starts_with($pass, 'CHANGE_ME')) {
        return [
            'ok' => false,
            'error' => 'SMTP enabled but credentials incomplete in config/sipaw.php (smtp.host / user / pass). Fill Hostinger SMTP, or use: php scripts/set-owner-password.php you@email.com \'NewPass\'',
        ];
    }
    return ['ok' => true];
}

/**
 * @return array{ok:bool, owner_id?:int, token_id?:int, error?:string}
 */
function rrp_password_reset_validate(string $token): array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 32) {
        return ['ok' => false, 'error' => 'Invalid or expired reset link.'];
    }
    $hash = hash('sha256', $token);
    try {
        $stmt = rrp_db()->prepare(
            'SELECT id, owner_id, expires_at, used_at FROM password_reset_tokens WHERE token_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Reset is not available yet. Import SQL 008.'];
    }
    if (!$row || $row['used_at'] !== null) {
        return ['ok' => false, 'error' => 'Invalid or expired reset link.'];
    }
    $exp = strtotime((string) $row['expires_at'] . ' UTC');
    if ($exp === false || $exp < time()) {
        return ['ok' => false, 'error' => 'This reset link has expired. Request a new one.'];
    }
    return ['ok' => true, 'owner_id' => (int) $row['owner_id'], 'token_id' => (int) $row['id']];
}

/**
 * @return array{ok:bool, error?:string}
 */
function rrp_password_reset_complete(string $token, string $password): array
{
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    $valid = rrp_password_reset_validate($token);
    if (empty($valid['ok'])) {
        return $valid;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $pdo = rrp_db();
        $pdo->beginTransaction();
        $upd = $pdo->prepare('UPDATE owners SET password_hash = ? WHERE id = ?');
        $upd->execute([$hash, (int) $valid['owner_id']]);
        $use = $pdo->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?');
        $use->execute([(int) $valid['token_id']]);
        $pdo->commit();
        rrp_auth_forget_all_devices((int) $valid['owner_id']);
    } catch (Throwable $e) {
        if (rrp_db()->inTransaction()) {
            rrp_db()->rollBack();
        }
        error_log('rrp_password_reset_complete: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not update password.'];
    }

    return ['ok' => true];
}

/**
 * Set owner password by email (used by CLI script). Never logs the password.
 *
 * @return array{ok:bool, owner_id?:int, error?:string}
 */
function rrp_set_owner_password_by_email(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    try {
        $stmt = rrp_db()->prepare('SELECT id FROM owners WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $owner = $stmt->fetch();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'DB error.'];
    }
    if (!$owner) {
        return ['ok' => false, 'error' => 'Owner not found.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        rrp_db()->prepare('UPDATE owners SET password_hash = ?, status = \'active\' WHERE id = ?')
            ->execute([$hash, (int) $owner['id']]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Update failed.'];
    }
    return ['ok' => true, 'owner_id' => (int) $owner['id']];
}
