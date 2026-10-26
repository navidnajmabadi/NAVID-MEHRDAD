<?php
/**
 * CLI: flag super owners + ensure Admin rows for Navid + Mehrdad.
 * Usage: php scripts/elevate-supers.php [optional_bootstrap_password]
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$pass = $argv[1] ?? null;
$result = rrp_elevate_super_owners($pass);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
