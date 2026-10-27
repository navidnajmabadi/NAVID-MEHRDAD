<?php
declare(strict_types=1);

function rrp_current_owner(): ?array
{
    rrp_start_session();
    if (empty($_SESSION['owner_id']) && !rrp_auth_remember_restore()) {
        return null;
    }
    try {
        // Prefer v1 columns (006+); fall back if not imported yet.
        try {
            $stmt = rrp_db()->prepare(
                'SELECT id, name, email, phone, address, status, created_at,
                        plan, plan_expires_at, locale, lat, lng, city, country,
                        home_lat, home_lng, home_radius_km, home_area_label,
                        first100_promo, first100_promo_ends_at, sip_balance,
                        nickname, timezone, sipo_cooldown_until, current_pet_id, is_super
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
        rrp_auth_remember_clear();
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

function rrp_login(string $email, string $password, bool $remember = true): array
{
    $email = strtolower(trim($email));
    try {
        if (rrp_auth_throttled($email)) {
            return ['ok' => false, 'error' => 'Too many tries. Wait 15 minutes, or reset your password.'];
        }
        $stmt = rrp_db()->prepare('SELECT * FROM owners WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $owner = $stmt->fetch();
        if (!$owner || !password_verify($password, $owner['password_hash'])) {
            rrp_auth_record_attempt($email, false);
            return ['ok' => false, 'error' => 'Email or password is incorrect.'];
        }
        if ($owner['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account is paused for review because several posts were removed. Our Support team will email you within 7 days. Your pets\' data is safe.'];
        }
        rrp_auth_record_attempt($email, true);
        if (password_needs_rehash($owner['password_hash'], PASSWORD_DEFAULT)) {
            $upd = rrp_db()->prepare('UPDATE owners SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($password, PASSWORD_DEFAULT), (int) $owner['id']]);
        }
        rrp_auth_start_owner_session((int) $owner['id'], $remember);
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
    rrp_auth_remember_clear();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

/**
 * Signup asks only what SIPO should call the owner, email and password.
 * No legal name, phone or address (brief §4: no human PII).
 */
function rrp_register_owner(array $data): array
{
    $nickname = trim(preg_replace('/\s+/u', ' ', (string) ($data['nickname'] ?? $data['name'] ?? '')));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $acceptTerms = !empty($data['accept_terms']);
    $timezone = rrp_auth_clean_timezone((string) ($data['timezone'] ?? ''));

    if ($nickname === '' || $email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Fill in a nickname, email and password.'];
    }
    if (mb_strlen($nickname) > 60) {
        return ['ok' => false, 'error' => 'Nickname is too long (max 60).'];
    }
    if (!$acceptTerms) {
        return ['ok' => false, 'error' => 'Accept the Terms and Privacy Policy to continue.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO owners (name, nickname, email, phone, address, password_hash, plan, locale, timezone, terms_accepted_at, privacy_accepted_at)
             VALUES (?, ?, ?, NULL, NULL, ?, \'free\', \'en\', ?, NOW(), NOW())'
        );
        $stmt->execute([$nickname, $nickname, $email, $hash, $timezone]);
        $id = (int) rrp_db()->lastInsertId();
        rrp_auth_start_owner_session($id, true);
        return ['ok' => true, 'owner_id' => $id];
    } catch (Throwable $e) {
        error_log('rrp_register_owner: ' . $e->getMessage());
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'An account with that email already exists. Try logging in.'];
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
    $nickname = trim((string) ($data['nickname'] ?? $data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));

    if ($nickname === '' || mb_strlen($nickname) > 60) {
        return ['ok' => false, 'error' => 'Tell SIPO what to call you (max 60 characters).'];
    }

    try {
        $prev = rrp_db()->prepare('SELECT address FROM owners WHERE id = ? LIMIT 1');
        $prev->execute([$ownerId]);
        $old = $prev->fetch();
        if (!$old) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }

        $stmt = rrp_db()->prepare(
            'UPDATE owners SET name = ?, nickname = ?, phone = ?, address = ? WHERE id = ?'
        );
        $stmt->execute([$nickname, $nickname, $phone !== '' ? $phone : null, $address !== '' ? $address : null, $ownerId]);

        if ($address !== '' && rrp_geocode_normalize_address($address) !== rrp_geocode_normalize_address((string) $old['address'])) {
            rrp_owner_apply_geocode($ownerId, $address);
        }

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not update profile')];
    }
}

// -----------------------------------------------------------------------------
// Session + remember-me + throttle
// -----------------------------------------------------------------------------

const RRP_REMEMBER_COOKIE = 'sipaw_remember';
const RRP_REMEMBER_DAYS = 60;
const RRP_LOGIN_MAX_FAILS = 5;
const RRP_LOGIN_WINDOW_MIN = 15;

function rrp_auth_start_owner_session(int $ownerId, bool $remember): void
{
    rrp_start_session();
    session_regenerate_id(true);
    $_SESSION['owner_id'] = $ownerId;
    // Fresh CSRF per login so a token from before login can't be replayed.
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if ($remember) {
        rrp_auth_remember_issue($ownerId);
    }
}

/**
 * iOS/Android PWAs drop session cookies when closed, and Hostinger GC kills idle
 * sessions after ~24 min. A rotating 60-day token keeps owners logged in.
 */
function rrp_auth_remember_issue(int $ownerId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + RRP_REMEMBER_DAYS * 86400;
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO auth_remember_tokens (owner_id, selector, validator_hash, expires_at, user_agent)
             VALUES (?, ?, ?, FROM_UNIXTIME(?), ?)'
        );
        $stmt->execute([$ownerId, $selector, hash('sha256', $validator), $expires, mb_substr((string) rrp_client_ua(), 0, 255)]);
    } catch (Throwable $e) {
        error_log('remember issue (run sql/009?): ' . $e->getMessage());
        return;
    }
    rrp_auth_set_remember_cookie($selector . ':' . $validator, $expires);
}

function rrp_auth_remember_restore(): bool
{
    $raw = (string) ($_COOKIE[RRP_REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
        return false;
    }
    try {
        $stmt = rrp_db()->prepare(
            'SELECT id, owner_id, validator_hash FROM auth_remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$m[1]]);
        $row = $stmt->fetch();
        if (!$row || !hash_equals((string) $row['validator_hash'], hash('sha256', $m[2]))) {
            rrp_auth_set_remember_cookie('', time() - 3600);
            return false;
        }
        // Rotate: one token, one use.
        rrp_db()->prepare('DELETE FROM auth_remember_tokens WHERE id = ?')->execute([(int) $row['id']]);
    } catch (Throwable $e) {
        return false;
    }
    rrp_auth_start_owner_session((int) $row['owner_id'], true);
    return true;
}

function rrp_auth_remember_clear(): void
{
    $raw = (string) ($_COOKIE[RRP_REMEMBER_COOKIE] ?? '');
    if (preg_match('/^([a-f0-9]{24}):/', $raw, $m)) {
        try {
            rrp_db()->prepare('DELETE FROM auth_remember_tokens WHERE selector = ?')->execute([$m[1]]);
        } catch (Throwable $e) {
        }
    }
    if ($raw !== '') {
        rrp_auth_set_remember_cookie('', time() - 3600);
    }
}

/** Password reset / suspension: log out every device. */
function rrp_auth_forget_all_devices(int $ownerId): void
{
    try {
        rrp_db()->prepare('DELETE FROM auth_remember_tokens WHERE owner_id = ?')->execute([$ownerId]);
    } catch (Throwable $e) {
    }
}

function rrp_auth_set_remember_cookie(string $value, int $expires): void
{
    if (headers_sent()) {
        return;
    }
    setcookie(RRP_REMEMBER_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => rrp_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($value === '') {
        unset($_COOKIE[RRP_REMEMBER_COOKIE]);
    } else {
        $_COOKIE[RRP_REMEMBER_COOKIE] = $value;
    }
}

function rrp_auth_throttled(string $email): bool
{
    try {
        $stmt = rrp_db()->prepare(
            'SELECT COUNT(*) FROM auth_login_attempts
             WHERE succeeded = 0 AND created_at > (UTC_TIMESTAMP() - INTERVAL ' . RRP_LOGIN_WINDOW_MIN . ' MINUTE)
               AND (email_hash = ? OR ip = ?)'
        );
        $stmt->execute([hash('sha256', $email), (string) rrp_client_ip()]);
        return (int) $stmt->fetchColumn() >= RRP_LOGIN_MAX_FAILS;
    } catch (Throwable $e) {
        return false; // 009 not imported yet
    }
}

function rrp_auth_record_attempt(string $email, bool $ok): void
{
    try {
        if ($ok) {
            rrp_db()->prepare('DELETE FROM auth_login_attempts WHERE email_hash = ?')->execute([hash('sha256', $email)]);
            return;
        }
        $stmt = rrp_db()->prepare(
            'INSERT INTO auth_login_attempts (email_hash, ip, succeeded, created_at) VALUES (?, ?, 0, UTC_TIMESTAMP())'
        );
        $stmt->execute([hash('sha256', $email), (string) rrp_client_ip()]);
    } catch (Throwable $e) {
    }
}

/**
 * Only same-site page paths: "dashboard.php", "/si-chat.php?pet=2".
 * Rejects "//evil.com/x.php", "https://…", backslashes.
 */
function rrp_safe_next(mixed $next, string $fallback = 'si-chat.php'): string
{
    if (!is_string($next) || $next === '' || strlen($next) > 300) {
        return $fallback;
    }
    if (str_contains($next, '\\') || str_starts_with($next, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $next)) {
        return $fallback;
    }
    if (!preg_match('#^/?[a-z0-9_\-]+(/[a-z0-9_\-]+)*\.php(\?[^\s]*)?$#i', $next)) {
        return $fallback;
    }
    return $next;
}

function rrp_auth_clean_timezone(string $tz): ?string
{
    $tz = trim($tz);
    if ($tz === '' || !in_array($tz, DateTimeZone::listIdentifiers(), true)) {
        return null;
    }
    return $tz;
}
