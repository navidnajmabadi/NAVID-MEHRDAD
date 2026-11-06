<?php
declare(strict_types=1);

// API calls: buffer output so rrp_json() can send clean JSON even if a notice was printed first.
if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/')) {
    ob_start();
}

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

/** The config file the app is really reading (first readable one in rrp_config_paths), or null. */
function rrp_config_file(): ?string
{
    foreach (rrp_config_paths() as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }
    return null;
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

/**
 * A long outside call (reading a lab PDF takes a minute) leaves the connection idle and MySQL closes it
 * ("MySQL server has gone away", live bug on a Probavet PDF). Call this after such a call: it checks the
 * connection and opens a new one if it was dropped.
 */
function rrp_db_keepalive(): void
{
    try {
        rrp_db()->query('SELECT 1');
    } catch (Throwable $e) {
        for ($try = 0; $try < 3; $try++) {
            try {
                rrp_db(true)->query('SELECT 1');
                return;
            } catch (Throwable $e2) {
                error_log('rrp_db_keepalive (try ' . ($try + 1) . '): ' . $e2->getMessage());
                usleep(200000);
            }
        }
    }
}

/**
 * Shared database connection. rrp_db(true) drops it and connects again.
 */
function rrp_db(bool $reset = false): PDO
{
    static $pdo = null;
    if ($reset) {
        $pdo = null;
    }
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
    // Shared hosting clears the common session folder every ~24 minutes (other sites' settings win), which
    // logged people out and broke every open page ("Session expired"). Keep our sessions in our own folder,
    // closed to the web (config/.htaccess), and clean it ourselves.
    $dir = dirname(__DIR__) . '/config/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        ini_set('session.save_path', $dir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    ini_set('session.gc_maxlifetime', (string) (7 * 86400));
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
    // A stray PHP notice before the JSON breaks r.json() in the browser: drop anything buffered.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
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

/**
 * The form token lives in a long-lived cookie (double submit), not only in the session, so a page that
 * stays open longer than the session still works: the cookie outlives it. SameSite=Lax + HttpOnly.
 */
function rrp_csrf_cookie(bool $rotate = false): string
{
    $c = (string) ($_COOKIE['sipaw_csrf'] ?? '');
    if ($rotate || !preg_match('/^[a-f0-9]{64}$/', $c)) {
        $c = bin2hex(random_bytes(32));
        if (!headers_sent()) {
            setcookie('sipaw_csrf', $c, [
                'expires' => time() + 60 * 86400,
                'path' => '/',
                'secure' => rrp_request_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['sipaw_csrf'] = $c;
    }
    return $c;
}

function rrp_csrf_token(): string
{
    return rrp_csrf_cookie();
}

function rrp_verify_csrf(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }
    $c = (string) ($_COOKIE['sipaw_csrf'] ?? '');
    if ($c !== '' && hash_equals($c, $token)) {
        return true;
    }
    // Pages rendered before this change carry the old session token: accept them once.
    rrp_start_session();
    return isset($_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], $token);
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
// The form token cookie must be sent before any page output starts.
if (PHP_SAPI !== 'cli') {
    rrp_csrf_cookie();
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/plan.php';
require_once __DIR__ . '/pets.php';
require_once __DIR__ . '/scans.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/onboarding.php';
require_once __DIR__ . '/units.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/invoice.php';
require_once __DIR__ . '/sipo/knowledge.php';
require_once __DIR__ . '/sipo/choices.php';
require_once __DIR__ . '/sipo/driver.php';
require_once __DIR__ . '/sipo/router.php';
require_once __DIR__ . '/sipo/scoring.php';
require_once __DIR__ . '/care/learn.php';
require_once __DIR__ . '/care/watch.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/webpush.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/geocode.php';
require_once __DIR__ . '/lost.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/tickets.php';
require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/chat.php';
/** The Pack/social board is hidden for now. The code stays; set RRP_SOCIAL_ENABLED=1 in the environment to bring it back. */
function rrp_social_enabled(): bool
{
    return in_array(strtolower((string) getenv('RRP_SOCIAL_ENABLED')), ['1', 'true', 'yes', 'on'], true);
}

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
require_once __DIR__ . '/finance.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/google_sso.php';
