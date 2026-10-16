<?php
// Nakilab — config + database access shared by the form, the blog and the MCP server.
// Secrets live OUTSIDE the web root in …/domains/nakilab.com/config/ (never in git):
//   config/db.php   → ['dsn' => 'mysql:host=127.0.0.1;dbname=…;charset=utf8mb4', 'user' => '…', 'pass_b64' => '…']
//   config/mcp.php  → ['token_sha256' => '…']
// For local testing, NAKILAB_CONFIG_DIR points somewhere else (e.g. a SQLite setup).
declare(strict_types=1);

function nk_config_dir(): string {
    return getenv('NAKILAB_CONFIG_DIR') ?: dirname(__DIR__, 2) . '/config';
}

function nk_config(string $name): array {
    static $cache = [];
    if (!isset($cache[$name])) {
        $path = nk_config_dir() . '/' . $name . '.php';
        $cache[$name] = is_file($path) ? (array) require $path : [];
    }
    return $cache[$name];
}

function nk_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = nk_config('db');
    if (empty($c['dsn'])) throw new RuntimeException('Database is not configured.');
    $pdo = new PDO($c['dsn'], $c['user'] ?? null, isset($c['pass_b64']) ? base64_decode($c['pass_b64']) : null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if (nk_driver() === 'mysql') $pdo->exec("SET time_zone = '+00:00'");
    else $pdo->exec('PRAGMA busy_timeout = 3000');
    nk_migrate($pdo);
    return $pdo;
}

function nk_driver(): string {
    return str_starts_with((string) (nk_config('db')['dsn'] ?? ''), 'sqlite:') ? 'sqlite' : 'mysql';
}

// All timestamps are stored as UTC "Y-m-d H:i:s".
function nk_now(): string { return gmdate('Y-m-d H:i:s'); }

function nk_migrate(PDO $db): void {
    if (nk_driver() === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS submissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            type VARCHAR(16) NOT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(200) NOT NULL,
            details MEDIUMTEXT NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'new',
            KEY idx_created (created_at),
            KEY idx_ip (ip_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(160) NOT NULL,
            title VARCHAR(200) NOT NULL,
            excerpt VARCHAR(400) NOT NULL DEFAULT '',
            body MEDIUMTEXT NOT NULL,
            tags VARCHAR(300) NOT NULL DEFAULT '',
            author VARCHAR(80) NOT NULL DEFAULT 'Nakilab',
            status VARCHAR(16) NOT NULL DEFAULT 'draft',
            publish_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            created_by VARCHAR(120) NOT NULL DEFAULT '',
            UNIQUE KEY uniq_slug (slug),
            KEY idx_live (status, publish_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, type TEXT NOT NULL,
            name TEXT NOT NULL, email TEXT NOT NULL, details TEXT NOT NULL, ip_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'new')");
        $db->exec("CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, title TEXT NOT NULL,
            excerpt TEXT NOT NULL DEFAULT '', body TEXT NOT NULL, tags TEXT NOT NULL DEFAULT '',
            author TEXT NOT NULL DEFAULT 'Nakilab', status TEXT NOT NULL DEFAULT 'draft', publish_at TEXT NULL,
            created_at TEXT NOT NULL, updated_at TEXT NOT NULL, created_by TEXT NOT NULL DEFAULT '')");
    }
    nk_add_submission_columns($db);
}

// Form field → its own readable column in `submissions` (so each answer shows separately in phpMyAdmin).
// `details` keeps the full original JSON as well.
const NK_SUB_COLUMNS = [
    'title'     => ['idea_title', 'VARCHAR(160)'],
    'idea'      => ['idea_description', 'TEXT'],
    'stage'     => ['stage', 'VARCHAR(60)'],
    'country'   => ['country', 'VARCHAR(80)'],
    'help'      => ['help_needed', 'VARCHAR(120)'],
    'skills'    => ['skills', 'VARCHAR(300)'],
    'interests' => ['interests', 'VARCHAR(300)'],
    'budget'    => ['budget', 'VARCHAR(40)'],
    'time'      => ['time_per_week', 'VARCHAR(40)'],
    'org'       => ['organisation', 'VARCHAR(160)'],
    'message'   => ['message', 'TEXT'],
];

function nk_add_submission_columns(PDO $db): void {
    $have = nk_driver() === 'mysql'
        ? array_column($db->query('SHOW COLUMNS FROM submissions')->fetchAll(), 'Field')
        : array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(), 'name');
    if (!in_array('email_log', $have, true)) {
        $db->exec(nk_driver() === 'mysql'
            ? "ALTER TABLE submissions ADD COLUMN email_log VARCHAR(255) NULL AFTER status"
            : "ALTER TABLE submissions ADD COLUMN email_log TEXT NULL");
    }
    if (in_array('message', $have, true)) return;
    $after = 'email';
    foreach (NK_SUB_COLUMNS as [$col, $type]) {
        if (!in_array($col, $have, true)) {
            $db->exec(nk_driver() === 'mysql'
                ? "ALTER TABLE submissions ADD COLUMN $col $type NULL AFTER $after"
                : "ALTER TABLE submissions ADD COLUMN $col TEXT NULL");
        }
        $after = $col;
    }
    // Fill the new columns for rows saved before they existed
    $rows = $db->query('SELECT id, details FROM submissions')->fetchAll();
    foreach ($rows as $r) {
        [$sql, $vals] = nk_submission_column_values(json_decode((string) $r['details'], true) ?: []);
        if (!$sql) continue;
        $vals[] = (int) $r['id'];
        $db->prepare("UPDATE submissions SET $sql WHERE id = ?")->execute($vals);
    }
}

/** @return array{0:string,1:array} "col = ?, …" and its values, from a details array */
function nk_submission_column_values(array $details): array {
    $set = []; $vals = [];
    foreach (NK_SUB_COLUMNS as $key => [$col]) {
        if (!array_key_exists($key, $details)) continue;
        $v = $details[$key];
        $set[] = "$col = ?";
        $vals[] = is_array($v) ? implode(', ', $v) : (string) $v;
    }
    return [implode(', ', $set), $vals];
}

function nk_h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
