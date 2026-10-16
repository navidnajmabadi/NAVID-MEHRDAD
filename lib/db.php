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
}

function nk_h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
