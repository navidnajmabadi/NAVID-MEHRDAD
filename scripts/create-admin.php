<?php
declare(strict_types=1);

/**
 * CLI: create an admin user (Navid / Mehrdad).
 *
 * Usage (from app root, with config readable):
 *   php scripts/create-admin.php "Navid" navid@example.com 'StrongPasswordHere'
 *
 * Or use admin/setup.php in the browser with admin_setup_secret (until 2 admins exist).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$name = $argv[1] ?? '';
$email = $argv[2] ?? '';
$password = $argv[3] ?? '';

if ($name === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Usage: php scripts/create-admin.php \"Name\" email@domain.com 'password'\n");
    exit(1);
}

$result = rrp_admin_create($name, $email, $password);
if (empty($result['ok'])) {
    fwrite(STDERR, ($result['error'] ?? 'Failed') . "\n");
    exit(1);
}

rrp_admin_log(null, 'admin_cli_create', 'admin_users', (int) $result['admin_id'], 'Created via CLI');
fwrite(STDOUT, "Created admin #{$result['admin_id']} <{$email}>\n");
exit(0);
