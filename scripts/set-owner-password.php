<?php
/**
 * Hostinger CLI only — set an owner's login password (never expose on the web).
 *
 * Usage (from app root on the server):
 *   php scripts/set-owner-password.php navid.najmabadi@gmail.com 'YourNewPasswordHere'
 *
 * Exit codes: 0 ok, 1 usage/error, 2 owner not found.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
$password = (string) ($argv[2] ?? '');

if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: php scripts/set-owner-password.php <email> <new-password>\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

try {
    $stmt = rrp_db()->prepare('SELECT id, email, status FROM owners WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $owner = $stmt->fetch();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB error: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$owner) {
    fwrite(STDERR, "Owner not found: {$email}\n");
    exit(2);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $upd = rrp_db()->prepare('UPDATE owners SET password_hash = ?, status = \'active\' WHERE id = ?');
    $upd->execute([$hash, (int) $owner['id']]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Update failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// Keep super flag if listed; never overwrite password again.
if (in_array($email, rrp_super_owner_emails(), true)) {
    try {
        rrp_db()->prepare('UPDATE owners SET is_super = 1, plan = \'plus\' WHERE id = ?')
            ->execute([(int) $owner['id']]);
    } catch (Throwable $e) {
        try {
            rrp_db()->prepare('UPDATE owners SET plan = \'plus\' WHERE id = ?')
                ->execute([(int) $owner['id']]);
        } catch (Throwable $e2) {
        }
    }
}

echo json_encode([
    'ok' => true,
    'owner_id' => (int) $owner['id'],
    'email' => $email,
    'message' => 'Password updated. Log in at /login.php — do not commit this password.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
