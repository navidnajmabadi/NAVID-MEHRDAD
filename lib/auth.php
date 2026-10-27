<?php
declare(strict_types=1);

function rrp_current_owner(): ?array
{
    rrp_start_session();
    if (empty($_SESSION['owner_id'])) {
        return null;
    }
    try {
        // Prefer v1 columns (006+); fall back if not imported yet.
        try {
            $stmt = rrp_db()->prepare(
                'SELECT id, name, email, phone, address, status, created_at,
                        plan, plan_expires_at, locale, lat, lng, city, country,
                        home_lat, home_lng, home_radius_km, home_area_label,
                        first100_promo, first100_promo_ends_at, sip_balance
                 FROM owners WHERE id = ? LIMIT 1'
            );
            $stmt->execute([(int) $_SESSION['owner_id']]);
            $owner = $stmt->fetch();
        } catch (Throwable $inner) {
            $stmt = rrp_db()->prepare('SELECT id, name, email, phone, address, status, created_at, lat, lng, city, country FROM owners WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['owner_id']]);
            $owner = $stmt->fetch();
            if ($owner) {
                $owner['plan'] = $owner['plan'] ?? 'free';
                $owner['locale'] = $owner['locale'] ?? 'en';
            }
        }
    } catch (Throwable $e) {
        error_log('rrp_current_owner: ' . $e->getMessage());
        return null;
    }
    if (!$owner || $owner['status'] !== 'active') {
        unset($_SESSION['owner_id']);
        return null;
    }
    return $owner;
}

function rrp_require_owner(): array
{
    $owner = rrp_current_owner();
    if (!$owner) {
        rrp_redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php'));
    }
    return $owner;
}

function rrp_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    try {
        $stmt = rrp_db()->prepare('SELECT * FROM owners WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $owner = $stmt->fetch();
        if (!$owner || !password_verify($password, $owner['password_hash'])) {
            return ['ok' => false, 'error' => 'Email or password is incorrect.'];
        }
        if ($owner['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account is suspended.'];
        }
        rrp_start_session();
        session_regenerate_id(true);
        $_SESSION['owner_id'] = (int) $owner['id'];
        // Keep super emails flagged + Admin rows present (idempotent).
        // Never touches owners.password_hash.
        if (in_array(strtolower((string) $owner['email']), rrp_super_owner_emails(), true)) {
            try {
                rrp_elevate_super_owners(null);
            } catch (Throwable $e) {
                error_log('rrp_login elevate: ' . $e->getMessage());
            }
        }
        return ['ok' => true, 'owner' => $owner];
    } catch (Throwable $e) {
        error_log('rrp_login: ' . $e->getMessage());
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not log in')];
    }
}

function rrp_logout(): void
{
    rrp_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function rrp_register_owner(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $acceptTerms = !empty($data['accept_terms']);
    $acceptPrivacy = !empty($data['accept_privacy']);

    if ($name === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
        return ['ok' => false, 'error' => 'All fields are required.'];
    }
    if (!$acceptTerms || !$acceptPrivacy) {
        return ['ok' => false, 'error' => 'Accept Terms and Privacy to continue.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        try {
            $stmt = rrp_db()->prepare(
                'INSERT INTO owners (name, email, phone, address, password_hash, plan, locale, terms_accepted_at, privacy_accepted_at)
                 VALUES (?, ?, ?, ?, ?, \'free\', \'en\', NOW(), NOW())'
            );
            $stmt->execute([$name, $email, $phone, $address, $hash]);
        } catch (Throwable $inner) {
            $stmt = rrp_db()->prepare(
                'INSERT INTO owners (name, email, phone, address, password_hash) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $email, $phone, $address, $hash]);
        }
        $id = (int) rrp_db()->lastInsertId();
        // Best-effort Nominatim geocode for LOST HELP ME radius (cached).
        rrp_owner_apply_geocode($id, $address);
        rrp_start_session();
        session_regenerate_id(true);
        $_SESSION['owner_id'] = $id;
        return ['ok' => true, 'owner_id' => $id];
    } catch (Throwable $e) {
        error_log('rrp_register_owner: ' . $e->getMessage());
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'An account with that email already exists.'];
        }
        $prev = $e->getPrevious();
        if ($prev instanceof PDOException && (int) ($prev->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'An account with that email already exists.'];
        }
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not create account')];
    }
}

/**
 * Update owner profile fields used by LOST HELP ME (address → re-geocode).
 * @return array{ok:bool, error?:string}
 */
function rrp_update_owner_profile(int $ownerId, array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));

    if ($name === '' || $phone === '' || $address === '') {
        return ['ok' => false, 'error' => 'Name, phone, and address are required.'];
    }

    try {
        $prev = rrp_db()->prepare('SELECT address FROM owners WHERE id = ? LIMIT 1');
        $prev->execute([$ownerId]);
        $old = $prev->fetch();
        if (!$old) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }

        $stmt = rrp_db()->prepare(
            'UPDATE owners SET name = ?, phone = ?, address = ? WHERE id = ?'
        );
        $stmt->execute([$name, $phone, $address, $ownerId]);

        if (rrp_geocode_normalize_address($address) !== rrp_geocode_normalize_address((string) $old['address'])) {
            rrp_owner_apply_geocode($ownerId, $address);
        }

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not update profile')];
    }
}
