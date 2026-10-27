<?php
declare(strict_types=1);

/**
 * Site health (brief §4): uptime from cron heartbeats, DB speed, memory/load, storage, and an
 * error log built from uncaught exceptions + fatal errors (grouped, with hit counts).
 */

/** Record that a cron ran (gaps between heartbeats = downtime or cron stopped). */
function rrp_ops_heartbeat(string $source): void
{
    $t0 = microtime(true);
    try {
        rrp_db()->query('SELECT 1')->fetchColumn();
        $dbMs = (int) round((microtime(true) - $t0) * 1000);
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? null) : null;
        rrp_db()->prepare('INSERT INTO site_heartbeats (source, ok, db_ms, mem_kb, load1, created_at) VALUES (?, 1, ?, ?, ?, UTC_TIMESTAMP())')
            ->execute([$source, $dbMs, (int) round(memory_get_peak_usage(true) / 1024), $load]);
        rrp_db()->exec('DELETE FROM site_heartbeats WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 45 DAY)');
    } catch (Throwable $e) {
        error_log('heartbeat: ' . $e->getMessage());
    }
}

/** Store an error (deduped by fingerprint). Never throws. */
function rrp_ops_record_error(string $level, string $message, ?string $file, ?int $line): void
{
    static $busy = false;
    if ($busy) {
        return;
    }
    $busy = true;
    try {
        $root = dirname(__DIR__, 2);
        $file = $file ? str_replace($root . '/', '', $file) : null;
        $msg = mb_substr(preg_replace('/\s+/', ' ', $message) ?? $message, 0, 500);
        $fp = sha1($level . '|' . ($file ?? '') . '|' . ($line ?? 0) . '|' . preg_replace('/\d+/', '#', $msg));
        $url = isset($_SERVER['REQUEST_URI']) ? mb_substr((string) strtok((string) $_SERVER['REQUEST_URI'], '?'), 0, 255) : (PHP_SAPI === 'cli' ? 'cli' : null);
        rrp_db()->prepare(
            'INSERT INTO app_errors (fingerprint, level, message, file, line, url, first_seen, last_seen)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = UTC_TIMESTAMP(), url = VALUES(url), resolved = 0'
        )->execute([$fp, $level, $msg, $file, $line, $url]);
    } catch (Throwable $e) {
        // DB down: PHP's own error log still has it
    }
    $busy = false;
}

/** Install handlers once (bootstrap). Uncaught exceptions + fatal errors only (warnings stay in error_log). */
function rrp_ops_install_handlers(): void
{
    set_exception_handler(static function (Throwable $e): void {
        rrp_ops_record_error('exception', get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo PHP_SAPI === 'cli' ? "Error\n" : '<!doctype html><meta charset="utf-8"><title>SIPAW</title><body style="font-family:system-ui;background:#000;color:#fff;padding:40px"><h1>Something went wrong</h1><p>We have been told. Please try again in a moment.</p>';
    });
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            rrp_ops_record_error('fatal', (string) $err['message'], (string) $err['file'], (int) $err['line']);
        }
    });
}

/** @return array<string,mixed> numbers for Admin → Site health */
function rrp_ops_snapshot(): array
{
    $root = dirname(__DIR__, 2);
    $q = static function (string $sql) {
        try {
            return rrp_db()->query($sql)->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    };
    // Uptime: 15-min slots in the last 7 days that had at least one heartbeat.
    $slots = $q("SELECT FLOOR(UNIX_TIMESTAMP(created_at) / 900) AS s FROM site_heartbeats WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 7 DAY) GROUP BY s");
    $firstBeat = $q("SELECT MIN(created_at) AS t FROM site_heartbeats WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 7 DAY)")[0]['t'] ?? null;
    $expected = $firstBeat ? max(1, (int) floor((time() - strtotime($firstBeat . ' UTC')) / 900)) : 0;
    $uptime = $expected ? min(100, round(100 * count($slots) / $expected, 1)) : null;
    $gaps = $q("SELECT a.created_at AS after, TIMESTAMPDIFF(MINUTE, a.created_at,
                    (SELECT MIN(b.created_at) FROM site_heartbeats b WHERE b.created_at > a.created_at)) AS mins
                FROM site_heartbeats a WHERE a.created_at > (UTC_TIMESTAMP() - INTERVAL 7 DAY)
                HAVING mins > 40 ORDER BY a.created_at DESC LIMIT 10");
    $last = $q("SELECT source, created_at, db_ms, mem_kb, load1 FROM site_heartbeats ORDER BY id DESC LIMIT 1")[0] ?? null;
    $dbAvg = $q("SELECT ROUND(AVG(db_ms)) AS ms FROM site_heartbeats WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 1 DAY)")[0]['ms'] ?? null;
    $dbSize = $q("SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) AS mb FROM information_schema.tables WHERE table_schema = DATABASE()")[0]['mb'] ?? null;
    $bigTables = $q("SELECT table_name AS t, ROUND((data_length + index_length) / 1048576, 1) AS mb, table_rows AS r FROM information_schema.tables
                     WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC LIMIT 6");
    return [
        'uptime_7d' => $uptime,
        'gaps' => $gaps,
        'last_heartbeat' => $last,
        'db_ms_avg' => $dbAvg,
        'db_mb' => $dbSize,
        'big_tables' => $bigTables,
        'uploads_mb' => round(rrp_ops_dir_size($root . '/uploads') / 1048576, 1),
        'disk_free_gb' => @disk_free_space($root) !== false ? round((float) disk_free_space($root) / 1073741824, 1) : null,
        'memory_limit' => ini_get('memory_limit'),
        'php' => PHP_VERSION,
        'load' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        'errors' => $q('SELECT * FROM app_errors WHERE resolved = 0 ORDER BY last_seen DESC LIMIT 50'),
        'php_log' => rrp_ops_php_log_tail(40),
    ];
}

function rrp_ops_dir_size(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $n = 0;
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            $n += $f->getSize();
        }
    } catch (Throwable $e) {
    }
    return $n;
}

/** Last lines of PHP's error_log (Hostinger writes it next to the app or per folder). @return list<string> */
function rrp_ops_php_log_tail(int $lines): array
{
    $root = dirname(__DIR__, 2);
    foreach (array_filter([ini_get('error_log') ?: null, $root . '/error_log', $root . '/api/si/error_log']) as $p) {
        if (is_readable($p) && is_file($p)) {
            $size = filesize($p);
            $fh = fopen($p, 'rb');
            fseek($fh, max(0, $size - 64 * 1024));
            $chunk = (string) stream_get_contents($fh);
            fclose($fh);
            $rows = array_values(array_filter(explode("\n", $chunk)));
            // Hide anything that looks like a secret before showing it.
            return array_map(static fn($l) => mb_substr(preg_replace('/(sk-[A-Za-z0-9_-]{6})[A-Za-z0-9_-]+/', '$1…', $l) ?? $l, 0, 300), array_slice($rows, -$lines));
        }
    }
    return [];
}
