<?php
declare(strict_types=1);

/**
 * Owner account security: change password, and delete account through an emailed
 * link + typed confirmation. The delete link is a signed token (no table): it embeds
 * the owner id and expiry and is signed with the current password hash, so it dies
 * after 30 minutes or as soon as the password changes.
 */

const RRP_ACCOUNT_DELETE_TTL = 1800;

/** @return array{ok:bool, error?:string} */
function rrp_owner_change_password(int $ownerId, string $current, string $new, string $repeat): array
{
    $st = rrp_db()->prepare('SELECT password_hash FROM owners WHERE id = ? LIMIT 1');
    $st->execute([$ownerId]);
    $hash = (string) $st->fetchColumn();
    if ($hash === '' || !password_verify($current, $hash)) {
        return ['ok' => false, 'error' => 'Current password is not right.'];
    }
    if (strlen($new) < 8) {
        return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
    }
    if ($new !== $repeat) {
        return ['ok' => false, 'error' => 'The two new passwords do not match.'];
    }
    if (password_verify($new, $hash)) {
        return ['ok' => false, 'error' => 'Choose a password you have not used here.'];
    }
    rrp_db()->prepare('UPDATE owners SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $ownerId]);
    rrp_auth_forget_all_devices($ownerId); // other phones must log in again
    rrp_auth_start_owner_session($ownerId, true);
    return ['ok' => true];
}

function rrp_account_secret(): string
{
    $c = rrp_load_config();
    return hash('sha256', (string) ($c['cron_secret'] ?? '') . '|' . (string) ($c['admin_setup_secret'] ?? '') . '|sipaw-account');
}

function rrp_account_delete_token(int $ownerId, ?int $now = null): string
{
    $exp = ($now ?? time()) + RRP_ACCOUNT_DELETE_TTL;
    $st = rrp_db()->prepare('SELECT password_hash FROM owners WHERE id = ? LIMIT 1');
    $st->execute([$ownerId]);
    $sig = hash_hmac('sha256', $ownerId . '|' . $exp . '|' . (string) $st->fetchColumn(), rrp_account_secret());
    return $ownerId . '.' . $exp . '.' . $sig;
}

/** @return int|null owner id when the token is valid and unexpired */
function rrp_account_delete_token_owner(string $token): ?int
{
    if (!preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $token, $m)) {
        return null;
    }
    [$_, $id, $exp, $sig] = $m;
    if ((int) $exp < time()) {
        return null;
    }
    $st = rrp_db()->prepare('SELECT password_hash FROM owners WHERE id = ? LIMIT 1');
    $st->execute([(int) $id]);
    $hash = $st->fetchColumn();
    if ($hash === false) {
        return null;
    }
    $want = hash_hmac('sha256', $id . '|' . $exp . '|' . (string) $hash, rrp_account_secret());
    return hash_equals($want, $sig) ? (int) $id : null;
}

/** Email the confirmation link. @return array{ok:bool, error?:string} */
function rrp_account_delete_request(array $owner): array
{
    $email = (string) ($owner['email'] ?? '');
    if ($email === '') {
        return ['ok' => false, 'error' => 'No email on this account.'];
    }
    $url = rtrim(rrp_app_url(), '/') . '/account-delete.php?t=' . rawurlencode(rrp_account_delete_token((int) $owner['id']));
    $body = "Hi,\n\nSomeone (hopefully you) asked to delete this SIPAW account and all its pets, chats and records.\n\n"
        . "To continue, open this link within 30 minutes and type DELETE to confirm:\n{$url}\n\n"
        . "If this was not you, ignore this email and change your password. Nothing is deleted without the confirmation.\n\nSIPAW";
    $r = rrp_send_mail_result($email, 'Confirm deleting your SIPAW account', $body);
    return !empty($r['ok']) ? ['ok' => true] : ['ok' => false, 'error' => 'Could not send the email: ' . ($r['error'] ?? 'mail failed')];
}

/** Remove the owner and everything that belongs to them (all FKs cascade). */
function rrp_account_delete(int $ownerId): bool
{
    try {
        $imgs = rrp_db()->prepare('SELECT image_path FROM pets WHERE owner_id = ? AND image_path IS NOT NULL');
        $imgs->execute([$ownerId]);
        $files = $imgs->fetchAll(PDO::FETCH_COLUMN);
        rrp_db()->prepare('DELETE FROM owners WHERE id = ?')->execute([$ownerId]);
        foreach ($files as $f) {
            $path = dirname(__DIR__) . '/' . ltrim((string) $f, '/');
            if (is_file($path) && str_contains(realpath($path) ?: '', '/uploads/')) {
                @unlink($path);
            }
        }
        error_log('Account deleted by owner request: owner=' . $ownerId);
        return true;
    } catch (Throwable $e) {
        error_log('rrp_account_delete: ' . $e->getMessage());
        return false;
    }
}
