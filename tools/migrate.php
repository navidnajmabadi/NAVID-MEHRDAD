<?php
// One-off CLI: create the Nakilab tables and copy submissions from the old SQLite file.
// Run on the server:  php tools/migrate.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/lib/db.php';

$db = nk_db(); // creates tables if missing
echo 'Database: ', nk_driver(), " — tables ready\n";

$old = dirname(__DIR__, 2) . '/intake-data/intake.sqlite';
if (!is_file($old)) { echo "No old SQLite file found — nothing to import.\n"; exit; }
$src = new PDO('sqlite:' . $old, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$exists = $db->prepare('SELECT COUNT(*) FROM submissions WHERE created_at = ? AND email = ?');
$ins = $db->prepare('INSERT INTO submissions (created_at, type, name, email, details, ip_hash, status) VALUES (?,?,?,?,?,?,?)');
$copied = 0;
foreach ($src->query('SELECT * FROM submissions ORDER BY id') as $r) {
    $exists->execute([$r['created_at'], $r['email']]);
    if ((int) $exists->fetchColumn() > 0) continue;
    $ins->execute([$r['created_at'], $r['type'], $r['name'], $r['email'], $r['details'], $r['ip_hash'], $r['status']]);
    $copied++;
}
echo "Copied $copied submission(s). Total now: ", $db->query('SELECT COUNT(*) FROM submissions')->fetchColumn(), "\n";
