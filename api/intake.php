<?php
// Nakilab intake: stores idea / idea-request / investor submissions in a private SQLite file
// that lives OUTSIDE the public web root (…/domains/nakilab.com/intake-data/intake.sqlite).
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, ['ok' => false, 'error' => 'Method not allowed.']);

// Same-site requests only (localhost is allowed only in local testing, when NAKILAB_INTAKE_DIR is set)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = '#^https://([a-z0-9-]+\.)*nakilab\.com$#i';
if (getenv('NAKILAB_INTAKE_DIR')) $allowed = '#^(https://([a-z0-9-]+\.)*nakilab\.com|http://localhost(:\d+)?)$#i';
if ($origin !== '' && !preg_match($allowed, $origin)) reply(403, ['ok' => false, 'error' => 'Forbidden.']);

$raw = file_get_contents('php://input', false, null, 0, 32768);
$in = json_decode((string) $raw, true);
if (!is_array($in)) reply(400, ['ok' => false, 'error' => 'Invalid request.']);

// Honeypot: bots fill the hidden "website" field. Pretend success, store nothing.
if (!empty($in['website'])) reply(200, ['ok' => true]);

function clean($v, int $max): string {
    $s = is_string($v) ? trim($v) : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr($s, 0, $max);
}

$type = $in['type'] ?? '';
if (!in_array($type, ['have', 'need', 'invest'], true)) reply(422, ['ok' => false, 'error' => 'Please choose what you are here for.']);
if (($in['consent'] ?? false) !== true) reply(422, ['ok' => false, 'error' => 'Please tick the consent box.']);

$name = clean($in['name'] ?? '', 120);
$email = clean($in['email'] ?? '', 200);
if ($name === '') reply(422, ['ok' => false, 'error' => 'Please enter your name.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) reply(422, ['ok' => false, 'error' => 'Please enter a valid email.']);

$spec = [
    'have'   => ['title' => [160, true], 'idea' => [4000, true], 'stage' => [60, false], 'country' => [80, false]],
    'need'   => ['skills' => [300, true], 'interests' => [300, false], 'budget' => [40, false], 'time' => [40, false]],
    'invest' => ['org' => [160, false], 'message' => [4000, true]],
][$type];
$details = [];
foreach ($spec as $key => [$max, $required]) {
    $details[$key] = clean($in[$key] ?? '', $max);
    if ($required && $details[$key] === '') reply(422, ['ok' => false, 'error' => 'Please fill in all required fields.']);
}
if ($type === 'have') {
    $allowed = ['Evaluation', 'Build', 'Launch', 'Funding'];
    $details['help'] = array_values(array_intersect($allowed, is_array($in['help'] ?? null) ? $in['help'] : []));
}

$dataDir = getenv('NAKILAB_INTAKE_DIR') ?: dirname((string) $_SERVER['DOCUMENT_ROOT']) . '/intake-data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0700, true)) reply(500, ['ok' => false, 'error' => 'Server error. Please try again later.']);

try {
    $db = new PDO('sqlite:' . $dataDir . '/intake.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 3000');
    $db->exec('CREATE TABLE IF NOT EXISTS submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        type TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        details TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'new\'
    )');

    // Hash the IP with a private salt: enough to rate-limit, without storing the address itself
    $saltFile = $dataDir . '/.salt';
    if (!is_file($saltFile)) file_put_contents($saltFile, bin2hex(random_bytes(32)), LOCK_EX);
    $ipHash = hash('sha256', trim((string) file_get_contents($saltFile)) . ($_SERVER['REMOTE_ADDR'] ?? ''));

    $recent = $db->prepare("SELECT COUNT(*) FROM submissions WHERE ip_hash = ? AND created_at > datetime('now', '-10 minutes')");
    $recent->execute([$ipHash]);
    if ((int) $recent->fetchColumn() >= 5) reply(429, ['ok' => false, 'error' => 'Too many submissions. Please try again in a few minutes.']);

    $ins = $db->prepare('INSERT INTO submissions (type, name, email, details, ip_hash) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$type, $name, $email, json_encode($details, JSON_UNESCAPED_UNICODE), $ipHash]);
} catch (Throwable $e) {
    error_log('intake: ' . $e->getMessage());
    reply(500, ['ok' => false, 'error' => 'Server error. Please try again later.']);
}

reply(200, ['ok' => true]);
