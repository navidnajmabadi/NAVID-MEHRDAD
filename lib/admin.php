<?php
declare(strict_types=1);

/**
 * A logged-in super owner (super_owners in config, e.g. navid.najmabadi@gmail.com) is let into the
 * Admin panel without the separate admin password: we link (or create) the admin_users row with the
 * same email. A created row gets a random unusable password, so it can't be logged into directly.
 */
function rrp_admin_from_super_owner(): void
{
    try {
        $owner = rrp_current_owner();
        if (!$owner || !rrp_is_super_owner($owner) || trim((string) ($owner['email'] ?? '')) === '') {
            return;
        }
        $email = strtolower(trim((string) $owner['email']));
        $st = rrp_db()->prepare('SELECT id, status FROM admin_users WHERE LOWER(email) = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && $row['status'] !== 'active') {
            return; // a disabled admin stays disabled
        }
        if (!$row) {
            rrp_db()->prepare("INSERT INTO admin_users (name, email, password_hash, status) VALUES (?, ?, ?, 'active')")
                ->execute([(string) ($owner['name'] ?: 'Owner admin'), $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
            $id = (int) rrp_db()->lastInsertId();
        } else {
            $id = (int) $row['id'];
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $id;
        error_log('Admin access via super owner session: owner=' . (int) $owner['id'] . ' admin=' . $id);
    } catch (Throwable $e) {
        error_log('rrp_admin_from_super_owner: ' . $e->getMessage());
    }
}

function rrp_current_admin(): ?array
{
    rrp_start_session();
    if (empty($_SESSION['admin_id'])) {
        rrp_admin_from_super_owner();
    }
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    try {
        $stmt = rrp_db()->prepare(
            'SELECT id, name, email, status, last_login_at, created_at FROM admin_users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('rrp_current_admin: ' . $e->getMessage());
        return null;
    }
    if (!$admin || $admin['status'] !== 'active') {
        unset($_SESSION['admin_id']);
        return null;
    }
    return $admin;
}

function rrp_require_admin(): array
{
    $admin = rrp_current_admin();
    if (!$admin) {
        rrp_redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }
    return $admin;
}

function rrp_admin_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    try {
        $stmt = rrp_db()->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return ['ok' => false, 'error' => 'Email or password is incorrect.'];
        }
        if ($admin['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This admin account is disabled.'];
        }
        rrp_start_session();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        // Keep owner session separate; clear owner to avoid confusion
        unset($_SESSION['owner_id']);
        $upd = rrp_db()->prepare('UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?');
        $upd->execute([(int) $admin['id']]);
        rrp_admin_log((int) $admin['id'], 'admin_login', 'admin_users', (int) $admin['id'], 'Login ok');
        return ['ok' => true, 'admin' => $admin];
    } catch (Throwable $e) {
        error_log('rrp_admin_login: ' . $e->getMessage());
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not log in')];
    }
}

function rrp_admin_logout(): void
{
    rrp_start_session();
    unset($_SESSION['admin_id']);
}

function rrp_admin_count(): int
{
    try {
        return (int) rrp_db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Create an admin user. Used by setup bootstrap + CLI script.
 * @return array{ok:bool, admin_id?:int, error?:string}
 */
function rrp_admin_create(string $name, string $email, string $password): array
{
    $name = trim($name);
    $email = strtolower(trim($email));
    if ($name === '' || $email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Name, email, and password are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email.'];
    }
    if (strlen($password) < 10) {
        return ['ok' => false, 'error' => 'Admin password must be at least 10 characters.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO admin_users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash]);
        return ['ok' => true, 'admin_id' => (int) rrp_db()->lastInsertId()];
    } catch (Throwable $e) {
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'An admin with that email already exists.'];
        }
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not create admin')];
    }
}

function rrp_admin_log(?int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $detail = null): void
{
    try {
        $stmt = rrp_db()->prepare(
            'INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, detail, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            substr($action, 0, 80),
            $targetType,
            $targetId,
            $detail,
            rrp_client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('rrp_admin_log: ' . $e->getMessage());
    }
}

function rrp_admin_stats(): array
{
    $pdo = rrp_db();
    $users = (int) $pdo->query('SELECT COUNT(*) FROM owners')->fetchColumn();
    $active = (int) $pdo->query("SELECT COUNT(*) FROM owners WHERE status = 'active'")->fetchColumn();
    $suspended = (int) $pdo->query("SELECT COUNT(*) FROM owners WHERE status = 'suspended'")->fetchColumn();
    $pets = (int) $pdo->query('SELECT COUNT(*) FROM pets')->fetchColumn();
    $types = ['dog' => 0, 'cat' => 0, 'rabbit' => 0, 'custom' => 0];
    foreach ($pdo->query('SELECT type, COUNT(*) AS c FROM pets GROUP BY type') as $row) {
        $types[$row['type']] = (int) $row['c'];
    }
    $openLost = (int) $pdo->query("SELECT COUNT(*) FROM lost_alerts WHERE status = 'open'")->fetchColumn();
    $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')")->fetchColumn();
    $scans24h = (int) $pdo->query(
        'SELECT COUNT(*) FROM scans WHERE scanned_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)'
    )->fetchColumn();

    return [
        'users_total' => $users,
        'users_active' => $active,
        'users_suspended' => $suspended,
        'pets_total' => $pets,
        'pets_by_type' => $types,
        'open_lost' => $openLost,
        'open_tickets' => $openTickets,
        'scans_24h' => $scans24h,
    ];
}

/**
 * Best-effort city/country aggregates from owner address geocode fields.
 * @return list<array{city:?string,country:?string,owners:int}>
 */
function rrp_admin_geo_list(int $limit = 100): array
{
    $stmt = rrp_db()->prepare(
        "SELECT COALESCE(NULLIF(TRIM(city), ''), '(unknown)') AS city,
                COALESCE(NULLIF(TRIM(country), ''), '(unknown)') AS country,
                COUNT(*) AS owners
         FROM owners
         GROUP BY city, country
         ORDER BY owners DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Owners with coordinates for a simple map.
 * @return list<array{id:int,name:string,city:?string,country:?string,lat:float,lng:float}>
 */
function rrp_admin_geo_points(int $limit = 500): array
{
    $stmt = rrp_db()->prepare(
        'SELECT id, name, city, country, lat, lng FROM owners
         WHERE lat IS NOT NULL AND lng IS NOT NULL
         ORDER BY id DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'city' => $r['city'],
            'country' => $r['country'],
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lng'],
        ];
    }
    return $rows;
}

function rrp_admin_search_owners(string $q, int $limit = 50): array
{
    $q = trim($q);
    if ($q === '') {
        $stmt = rrp_db()->prepare(
            'SELECT id, name, email, phone, address, city, country, status, subscription_status,
                    wallet_balance, lat, lng, created_at
             FROM owners ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    $like = '%' . $q . '%';
    $stmt = rrp_db()->prepare(
        'SELECT id, name, email, phone, address, city, country, status, subscription_status,
                wallet_balance, lat, lng, created_at
         FROM owners
         WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ? OR city LIKE ? OR country LIKE ?
         ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $like);
    $stmt->bindValue(4, $like);
    $stmt->bindValue(5, $like);
    $stmt->bindValue(6, $like);
    $stmt->bindValue(7, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_admin_set_owner_status(int $adminId, int $ownerId, string $status): array
{
    if (!in_array($status, ['active', 'suspended'], true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }
    $stmt = rrp_db()->prepare('UPDATE owners SET status = ? WHERE id = ?');
    $stmt->execute([$status, $ownerId]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Owner not found.'];
    }
    rrp_admin_log($adminId, 'owner_' . $status, 'owners', $ownerId, "status=$status");
    return ['ok' => true];
}

function rrp_admin_recent_scans(int $limit = 40): array
{
    $stmt = rrp_db()->prepare(
        'SELECT s.*, p.name AS pet_name, p.public_id, o.name AS owner_name, o.email AS owner_email
         FROM scans s
         JOIN pets p ON p.id = s.pet_id
         JOIN owners o ON o.id = p.owner_id
         ORDER BY s.scanned_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_admin_recent_lost(int $limit = 40): array
{
    $stmt = rrp_db()->prepare(
        'SELECT la.*, o.name AS owner_name, o.email AS owner_email,
                (SELECT COUNT(*) FROM lost_alert_recipients r WHERE r.lost_alert_id = la.id) AS recipient_count
         FROM lost_alerts la
         JOIN owners o ON o.id = la.owner_id
         ORDER BY la.started_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function rrp_admin_recent_actions(int $limit = 50): array
{
    $stmt = rrp_db()->prepare(
        'SELECT l.*, a.name AS admin_name, a.email AS admin_email
         FROM admin_action_logs l
         LEFT JOIN admin_users a ON a.id = l.admin_id
         ORDER BY l.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
