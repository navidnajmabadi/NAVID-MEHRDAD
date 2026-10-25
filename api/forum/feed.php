<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$owner = rrp_current_owner();
if (!$owner) {
    rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
}

$species = isset($_GET['species']) ? preg_replace('/[^a-z]/', '', strtolower((string) $_GET['species'])) : null;
$posts = rrp_forum_feed(40, $species ?: null);
rrp_json(['ok' => true, 'posts' => $posts]);
