<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** GET ?scope=all|following&before_id=&pet= → posts for infinite scroll. */
$owner = rrp_api_owner();
$scope = ($_GET['scope'] ?? 'all') === 'following' ? 'following' : 'all';
$petId = null;
if (!empty($_GET['pet'])) {
    $prof = rrp_forum_pet_profile((string) $_GET['pet'], (int) $owner['id']);
    $petId = $prof['id'] ?? -1;
}
$posts = rrp_forum_feed(20, null, (int) $owner['id'], $scope, (int) ($_GET['before_id'] ?? 0), $petId);
rrp_json(['ok' => true, 'posts' => array_map('rrp_forum_present', $posts)]);
