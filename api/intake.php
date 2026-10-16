<?php
// Nakilab intake: stores idea / idea-request / investor submissions in the Nakilab database.
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, ['ok' => false, 'error' => 'Method not allowed.']);

// Same-site requests only (localhost is allowed only in local testing, when NAKILAB_CONFIG_DIR is set)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = '#^https://([a-z0-9-]+\.)*nakilab\.com$#i';
if (getenv('NAKILAB_CONFIG_DIR')) $allowed = '#^(https://([a-z0-9-]+\.)*nakilab\.com|http://localhost(:\d+)?)$#i';
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

try {
    $db = nk_db();

    // Hash the IP with a private salt: enough to rate-limit, without storing the address itself
    $saltFile = nk_config_dir() . '/.salt';
    if (!is_file($saltFile)) file_put_contents($saltFile, bin2hex(random_bytes(32)), LOCK_EX);
    $ipHash = hash('sha256', trim((string) file_get_contents($saltFile)) . ($_SERVER['REMOTE_ADDR'] ?? ''));

    $recent = $db->prepare('SELECT COUNT(*) FROM submissions WHERE ip_hash = ? AND created_at > ?');
    $recent->execute([$ipHash, gmdate('Y-m-d H:i:s', time() - 600)]);
    if ((int) $recent->fetchColumn() >= 5) reply(429, ['ok' => false, 'error' => 'Too many submissions. Please try again in a few minutes.']);

    $ins = $db->prepare('INSERT INTO submissions (created_at, type, name, email, details, ip_hash) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->execute([nk_now(), $type, $name, $email, json_encode($details, JSON_UNESCAPED_UNICODE), $ipHash]);
    [$cols, $vals] = nk_submission_column_values($details);
    if ($cols) {
        $vals[] = (int) $db->lastInsertId();
        $db->prepare("UPDATE submissions SET $cols WHERE id = ?")->execute($vals);
    }
} catch (Throwable $e) {
    error_log('intake: ' . $e->getMessage());
    reply(500, ['ok' => false, 'error' => 'Server error. Please try again later.']);
}

reply(200, ['ok' => true]);
