<?php
declare(strict_types=1);

function rrp_current_owner(): ?array
{
    rrp_start_session();
    if (empty($_SESSION['owner_id'])) {
        return null;
    }
    $stmt = rrp_db()->prepare('SELECT id, name, email, phone, address, status, created_at FROM owners WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['owner_id']]);
    $owner = $stmt->fetch();
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
    return ['ok' => true, 'owner' => $owner];
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

    if ($name === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
        return ['ok' => false, 'error' => 'All fields are required.'];
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
            'INSERT INTO owners (name, email, phone, address, password_hash) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $phone, $address, $hash]);
        $id = (int) rrp_db()->lastInsertId();
        rrp_start_session();
        session_regenerate_id(true);
        $_SESSION['owner_id'] = $id;
        return ['ok' => true, 'owner_id' => $id];
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            return ['ok' => false, 'error' => 'An account with that email already exists.'];
        }
        throw $e;
    }
}
