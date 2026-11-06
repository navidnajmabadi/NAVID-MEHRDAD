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

/** Short code a person can read out ("A7F2C1"): the first 6 characters of the error's fingerprint. */
function rrp_ops_error_code(string $fingerprint): string
{
    return strtoupper(substr($fingerprint, 0, 6));
}

/**
 * Store an error (deduped by fingerprint). Never throws. Returns the short error code ('' if not stored).
 * Newer columns (owner_id, trace, user_agent from migration 025) are used when they exist.
 */
function rrp_ops_record_error(string $level, string $message, ?string $file, ?int $line, ?string $trace = null): string
{
    static $busy = false;
    if ($busy) {
        return '';
    }
    $busy = true;
    $code = '';
    try {
        $root = dirname(__DIR__, 2);
        $file = $file ? str_replace($root . '/', '', $file) : null;
        $msg = mb_substr(preg_replace('/\s+/', ' ', $message) ?? $message, 0, 500);
        $fp = sha1($level . '|' . ($file ?? '') . '|' . ($line ?? 0) . '|' . preg_replace('/\d+/', '#', $msg));
        $code = rrp_ops_error_code($fp);
        $url = isset($_SERVER['REQUEST_URI']) ? mb_substr((string) strtok((string) $_SERVER['REQUEST_URI'], '?'), 0, 255) : (PHP_SAPI === 'cli' ? 'cli' : null);
        $ownerId = session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['owner_id']) ? (int) $_SESSION['owner_id'] : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 200) : null;
        $trace = $trace !== null ? mb_substr($trace, 0, 4000) : null;
        try {
            rrp_db()->prepare(
                'INSERT INTO app_errors (fingerprint, level, message, file, line, url, owner_id, trace, user_agent, first_seen, last_seen)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = UTC_TIMESTAMP(), url = VALUES(url), owner_id = VALUES(owner_id),
                   trace = COALESCE(VALUES(trace), trace), user_agent = VALUES(user_agent), resolved = 0'
            )->execute([$fp, $level, $msg, $file, $line, $url, $ownerId, $trace, $ua]);
        } catch (Throwable $e) {
            // Migration 025 not run yet: store the original columns only.
            rrp_db()->prepare(
                'INSERT INTO app_errors (fingerprint, level, message, file, line, url, first_seen, last_seen)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = UTC_TIMESTAMP(), url = VALUES(url), resolved = 0'
            )->execute([$fp, $level, $msg, $file, $line, $url]);
        }
    } catch (Throwable $e) {
        // DB down: PHP's own error log still has it
        $code = '';
    }
    $busy = false;
    return $code;
}

/** One block of plain text an admin can paste to whoever fixes it. */
function rrp_ops_error_text(array $e): string
{
    $lines = [
        'SIPAW error ' . rrp_ops_error_code((string) $e['fingerprint']) . ' (' . $e['level'] . ', seen ' . (int) $e['hits'] . 'x)',
        'Message: ' . $e['message'],
        'Where: ' . ($e['file'] ?? '') . ':' . (int) ($e['line'] ?? 0),
        'Page: ' . ($e['url'] ?? ''),
        'Last seen (UTC): ' . $e['last_seen'],
    ];
    if (!empty($e['owner_id'])) {
        $lines[] = 'Owner id: ' . (int) $e['owner_id'];
    }
    if (!empty($e['user_agent'])) {
        $lines[] = 'Browser: ' . $e['user_agent'];
    }
    if (!empty($e['trace'])) {
        $lines[] = "Trace:\n" . $e['trace'];
    }
    return implode("\n", $lines);
}

/**
 * Where PHP's own error log goes: config/ is closed to the web (config/.htaccess), so a log file
 * there cannot be downloaded. Hostinger's panel does not show one, so Admin → Errors reads this.
 */
function rrp_ops_log_path(): ?string
{
    $dir = dirname(__DIR__, 2) . '/config';
    return is_dir($dir) && is_writable($dir) ? $dir . '/php-error.log' : null;
}

/** Install handlers once (bootstrap): crashes, fatals, and warnings are all stored in app_errors. */
function rrp_ops_install_handlers(): void
{
    $log = rrp_ops_log_path();
    if ($log !== null) {
        @ini_set('log_errors', '1');
        @ini_set('error_log', $log);
    }
    set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
        // Warnings and notices only (not deprecations); @-silenced calls are skipped; at most 15 per request.
        static $seen = 0;
        $watch = E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_RECOVERABLE_ERROR;
        if (!($no & $watch) || !(error_reporting() & $no) || $seen >= 15) {
            return false;
        }
        $seen++;
        rrp_ops_record_error($no & (E_NOTICE | E_USER_NOTICE) ? 'notice' : 'warning', $str, $file, $line);
        return false; // PHP still writes it to the log too
    });
    set_exception_handler(static function (Throwable $e): void {
        $code = rrp_ops_record_error('exception', get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        // A signed-in super owner sees the real cause (nobody else does), so a live crash can be read at once.
        $detail = '';
        try {
            $me = function_exists('rrp_current_owner') ? rrp_current_owner() : null;
            if ($me && function_exists('rrp_is_super_owner') && rrp_is_super_owner($me)) {
                $detail = '<pre style="white-space:pre-wrap;color:#9ad;margin-top:24px;font-size:13px">[admin only] ' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . basename($e->getFile()) . ':' . $e->getLine(), ENT_QUOTES) . '</pre>';
            }
        } catch (Throwable $ignored) {
        }
        $codeLine = $code !== '' ? '<p style="color:#8a8f9c;font-size:14px">Error code <b style="color:#fff;letter-spacing:.08em">' . htmlspecialchars($code, ENT_QUOTES) . '</b> (tell us this code and we can find it).</p>' : '';
        echo PHP_SAPI === 'cli' ? "Error\n" : '<!doctype html><meta charset="utf-8"><title>SIPAW</title><body style="font-family:system-ui;background:#000;color:#fff;padding:40px"><h1>Something went wrong</h1><p>We have been told. Please try again in a moment.</p>' . $codeLine . $detail;
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
    foreach (array_filter([rrp_ops_log_path(), ini_get('error_log') ?: null, $root . '/error_log', $root . '/api/si/error_log']) as $p) {
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
