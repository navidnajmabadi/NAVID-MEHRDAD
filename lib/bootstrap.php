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
        // sipaw.php preferred; ringringpaw.php kept as legacy filename fallback.
        '/home/u233722910/domains/nakilab.com/config/sipaw.php',
        '/home/u233722910/domains/nakilab.com/config/ringringpaw.php',
        '/home/u233722910/domains/sipaw.nakilab.com/config/sipaw.php',
        '/home/u233722910/domains/sipaw.nakilab.com/config/ringringpaw.php',
        // Relative fallbacks if the clone lives under public_html/.../sipaw
        $root . '/../../config/sipaw.php',
        $root . '/../../config/ringringpaw.php',
        $root . '/../config/sipaw.php',
        $root . '/../config/ringringpaw.php',
        // Temporary: config/ inside the app/docroot (Navid File Browser layout).
        // Prefer outside-web-root long-term; .htaccess must deny /config/ over HTTP.
        $root . '/config/sipaw.php',
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
    echo "siPaw is not configured yet.\n\n";
    echo "Create MySQL DB, import sql/001_schema.sql, then place config at ONE of:\n";
    echo "  /home/u233722910/domains/nakilab.com/config/sipaw.php  (preferred)\n";
    echo "  /home/u233722910/domains/nakilab.com/config/ringringpaw.php  (legacy filename OK)\n";
    echo "  /home/u233722910/domains/sipaw.nakilab.com/config/sipaw.php\n";
    echo "  {app}/config/sipaw.php  (temporary in-docroot; HTTP blocked)\n";
    echo "(copy from config.example.php — never commit real passwords).\n";
    echo "Set app_url to https://sipaw.nakilab.com\n";
    exit;
}

function rrp_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = rrp_load_config()['db'];
    $charset = $cfg['charset'] ?? 'utf8mb4';
    if (!empty($cfg['unix_socket'])) {
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=%s',
            $cfg['unix_socket'],
            $cfg['name'],
            $charset
        );
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name'],
            $charset
        );
    }

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // All stored times are UTC; SIPO converts to the owner's timezone for display.
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        throw new RuntimeException(rrp_safe_db_error($e, 'Database connection failed'), 0, $e);
    }

    return $pdo;
}

/**
 * User-safe DB error text: SQLSTATE / driver code / hint — never password or DSN secrets.
 */
function rrp_safe_db_error(Throwable $e, string $fallback = 'Database error'): string
{
    $sqlState = '';
    $driverCode = '';
    if ($e instanceof PDOException) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (string) ($e->errorInfo[1] ?? '');
    } elseif ($e->getPrevious() instanceof PDOException) {
        $prev = $e->getPrevious();
        $sqlState = (string) ($prev->errorInfo[0] ?? $prev->getCode());
        $driverCode = (string) ($prev->errorInfo[1] ?? '');
    }

    $hint = '';
    $code = (int) $driverCode;
    if ($code === 1049 || $sqlState === '42000') {
        $hint = ' Check that config db.name matches a real MySQL database.';
    } elseif ($code === 1045) {
        $hint = ' Check db.user / db.pass in sipaw.php (or legacy ringringpaw.php).';
    } elseif ($code === 1146) {
        $hint = ' Table missing — import sql/001_schema.sql into the same DB as config.';
    } elseif ($code === 2002 || $code === 2003) {
        $hint = ' Cannot reach MySQL host.';
    }

    $parts = [$fallback];
    if ($sqlState !== '') {
        $parts[] = 'SQLSTATE ' . $sqlState;
    }
    if ($driverCode !== '') {
        $parts[] = 'code ' . $driverCode;
    }
    return implode(' — ', $parts) . '.' . $hint;
}

function rrp_config_path_in_use(): ?string
{
    foreach (rrp_config_paths() as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }
    return null;
}

function rrp_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $cfg = rrp_load_config();
    session_name($cfg['session_name'] ?? 'rrp_session');
    // Remember-me (lib/auth.php) carries long logins; this just avoids 24-min GC drops.
    ini_set('session.gc_maxlifetime', (string) (8 * 3600));
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => rrp_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function rrp_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
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

/**
 * Site-root path for static assets / shared layout links.
 * Uses "/assets/…" (not app_url) so a stale app_url cannot break CSS/images,
 * and /admin/* pages resolve to the subdomain root (not /admin/assets/…).
 * Subdomain docroot must be the app folder (https://sipaw.nakilab.com → …/sipaw).
 * When $versioned is true, appends ?v=filemtime so Hostinger CDN/browser
 * caches (max-age up to 7d) pick up CSS/JS after deploy.
 */
function rrp_asset(string $path, bool $versioned = false): string
{
    $path = ltrim($path, '/');
    $url = '/' . $path;
    if (!$versioned) {
        return $url;
    }
    $file = dirname(__DIR__) . '/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : (string) time();
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($v);
}

function rrp_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rrp_clip(string $value, int $max = 80): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if (strlen($value) <= $max) {
        return $value;
    }
    return rtrim(substr($value, 0, max(1, $max - 1))) . '…';
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

require_once __DIR__ . '/ops/health.php';
rrp_ops_install_handlers();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/plan.php';
require_once __DIR__ . '/pets.php';
require_once __DIR__ . '/scans.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/webpush.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/geocode.php';
require_once __DIR__ . '/lost.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/tickets.php';
require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/chat.php';
require_once __DIR__ . '/social/photos.php';
require_once __DIR__ . '/social/moderation.php';
require_once __DIR__ . '/rewards/coins.php';
require_once __DIR__ . '/rewards/program.php';
require_once __DIR__ . '/payments/billing.php';
require_once __DIR__ . '/insights/metrics.php';
require_once __DIR__ . '/health.php';
require_once __DIR__ . '/area.php';
require_once __DIR__ . '/si.php';
require_once __DIR__ . '/forum.php';
require_once __DIR__ . '/wellness.php';
require_once __DIR__ . '/routines.php';
require_once __DIR__ . '/food_photo.php';
require_once __DIR__ . '/pet_register.php';
require_once __DIR__ . '/si_brain.php';
require_once __DIR__ . '/viewmodels/chat_vm.php';
require_once __DIR__ . '/viewmodels/health_vm.php';
require_once __DIR__ . '/viewmodels/daily_vm.php';
