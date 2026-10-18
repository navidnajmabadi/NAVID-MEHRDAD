<?php
declare(strict_types=1);

/**
 * Shared bootstrap: config, PDO, session, helpers.
 */

function rrp_config_paths(): array
{
    $root = dirname(__DIR__);
    return [
        // Absolute Hostinger paths first (preferred: outside public_html).
        '/home/u233722910/domains/nakilab.com/config/ringringpaw.php',
        '/home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php',
        // Relative fallbacks if the clone lives under public_html/.../ringringpaw
        $root . '/../../config/ringringpaw.php',
        $root . '/../config/ringringpaw.php',
        // Temporary: config/ inside the app/docroot (Navid File Browser layout).
        // Prefer outside-web-root long-term; .htaccess must deny /config/ over HTTP.
        $root . '/config/ringringpaw.php',
        // Local override (gitignored)
        $root . '/config.local.php',
        // Fallback for local docker/dev only
        $root . '/config.php',
    ];
}

function rrp_config_ready(): bool
{
    foreach (rrp_config_paths() as $path) {
        if (is_readable($path)) {
            return true;
        }
    }
    return false;
}

function rrp_load_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    foreach (rrp_config_paths() as $path) {
        if (is_readable($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;
                return $config;
            }
        }
    }

    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "RingRingPaw is not configured yet.\n\n";
    echo "Create MySQL DB, import sql/001_schema.sql, then place config at ONE of:\n";
    echo "  /home/u233722910/domains/nakilab.com/config/ringringpaw.php  (preferred)\n";
    echo "  /home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php\n";
    echo "  {app}/config/ringringpaw.php  (temporary in-docroot; HTTP blocked)\n";
    echo "(copy from config.example.php — never commit real passwords).\n";
    exit;
}

function rrp_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = rrp_load_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        (int) ($cfg['port'] ?? 3306),
        $cfg['name'],
        $cfg['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function rrp_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $cfg = rrp_load_config();
    session_name($cfg['session_name'] ?? 'rrp_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function rrp_app_url(string $path = ''): string
{
    $base = rtrim(rrp_load_config()['app_url'] ?? '', '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = ($https ? 'https' : 'http') . '://' . $host;
    }
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function rrp_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rrp_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rrp_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function rrp_csrf_token(): string
{
    rrp_start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function rrp_verify_csrf(?string $token): bool
{
    rrp_start_session();
    return is_string($token)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function rrp_public_id(int $bytes = 8): string
{
    return bin2hex(random_bytes($bytes));
}

function rrp_client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return is_string($ip) ? substr($ip, 0, 45) : null;
}

function rrp_client_ua(): ?string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return is_string($ua) ? substr($ua, 0, 512) : null;
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pets.php';
require_once __DIR__ . '/scans.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/notify.php';
