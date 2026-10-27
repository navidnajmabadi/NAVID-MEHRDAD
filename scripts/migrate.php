<?php
declare(strict_types=1);

/**
 * CLI: apply SQL migrations in sql/ that have not run yet (deploy runs this after git pull).
 *
 * Usage: php scripts/migrate.php            # apply pending
 *        php scripts/migrate.php --dry-run  # list what would run
 *
 * - 001–008 were applied by hand before this runner existed; they are recorded as a baseline
 *   and never re-run (their seed INSERTs are not repeatable).
 * - Each statement runs on its own. "Already done" errors (duplicate column/key/table, …) are
 *   skipped, so a migration that was partly run by hand in phpMyAdmin completes safely.
 * - Any other error stops the run and the file is NOT recorded, so the next deploy retries.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/lib/bootstrap.php';

const RRP_MIGRATE_BASELINE = '008';
// MySQL/MariaDB codes meaning "this change is already in the database".
const RRP_MIGRATE_ALREADY = [1050, 1060, 1061, 1062, 1068, 1091, 1826, 1022];

$dry = in_array('--dry-run', $argv, true);
$pdo = rrp_db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
       name VARCHAR(120) NOT NULL PRIMARY KEY,
       applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
       skipped_statements INT UNSIGNED NOT NULL DEFAULT 0
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
$done = array_flip($pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));

$files = glob(dirname(__DIR__) . '/sql/[0-9][0-9][0-9]_*.sql') ?: [];
sort($files);
$ran = 0;
foreach ($files as $path) {
    $name = basename($path);
    if (isset($done[$name])) {
        continue;
    }
    if (substr($name, 0, 3) <= RRP_MIGRATE_BASELINE) {
        if (!$dry) {
            $pdo->prepare('INSERT IGNORE INTO schema_migrations (name, skipped_statements) VALUES (?, 0)')->execute([$name]);
        }
        echo "baseline  {$name}\n";
        continue;
    }
    $statements = rrp_migrate_split((string) file_get_contents($path));
    if ($dry) {
        echo "pending   {$name} (" . count($statements) . " statements)\n";
        continue;
    }
    $skipped = 0;
    foreach ($statements as $i => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($code, RRP_MIGRATE_ALREADY, true)) {
                // A multi-part ALTER fails as a whole when ONE part exists — run the parts one by one
                // so the missing ones are still applied.
                $parts = rrp_migrate_alter_parts($sql);
                if (count($parts) > 1) {
                    foreach ($parts as $part) {
                        try {
                            $pdo->exec($part);
                        } catch (PDOException $e2) {
                            $c2 = (int) ($e2->errorInfo[1] ?? 0);
                            if (!in_array($c2, RRP_MIGRATE_ALREADY, true)) {
                                fwrite(STDERR, "FAILED    {$name} statement " . ($i + 1) . " part (MySQL {$c2}): " . $e2->getMessage() . "\n");
                                exit(1);
                            }
                            $skipped++;
                        }
                    }
                    continue;
                }
                $skipped++;
                continue;
            }
            fwrite(STDERR, "FAILED    {$name} statement " . ($i + 1) . " (MySQL {$code}): " . $e->getMessage() . "\n");
            fwrite(STDERR, '          ' . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 160) . "\n");
            exit(1);
        }
    }
    $pdo->prepare('INSERT INTO schema_migrations (name, skipped_statements) VALUES (?, ?)')->execute([$name, $skipped]);
    echo "applied   {$name} (" . count($statements) . " statements" . ($skipped ? ", {$skipped} already done" : '') . ")\n";
    $ran++;
}
echo $dry ? "Dry run: nothing changed.\n" : ($ran ? "Done: {$ran} migration(s) applied.\n" : "Database is up to date.\n");

/**
 * "ALTER TABLE t ADD a …, ADD b …" → ["ALTER TABLE t ADD a …", "ALTER TABLE t ADD b …"].
 * Splits on top-level commas only (not inside parentheses or quotes).
 */
function rrp_migrate_alter_parts(string $sql): array
{
    if (!preg_match('/^\s*ALTER\s+TABLE\s+(`?\w+`?)\s+(.*)$/is', $sql, $m)) {
        return [$sql];
    }
    $table = $m[1];
    $body = $m[2];
    $parts = [];
    $depth = 0;
    $quote = null;
    $cur = '';
    for ($i = 0, $n = strlen($body); $i < $n; $i++) {
        $ch = $body[$i];
        if ($quote !== null) {
            if ($ch === $quote) {
                $quote = null;
            }
        } elseif ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
        } elseif ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth--;
        } elseif ($ch === ',' && $depth === 0) {
            $parts[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }
    if (trim($cur) !== '') {
        $parts[] = trim($cur);
    }
    return array_map(static fn($p) => "ALTER TABLE {$table} {$p}", $parts);
}

/** Split a migration file into statements (no procedures/triggers in our SQL). */
function rrp_migrate_split(string $sql): array
{
    $lines = [];
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $lines[] = $line;
    }
    $out = [];
    foreach (explode(';', implode("\n", $lines)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $out[] = $stmt;
        }
    }
    return $out;
}
