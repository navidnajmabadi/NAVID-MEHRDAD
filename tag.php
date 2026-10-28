<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// Tags and QR codes were removed from SIPAW; old links land on the pet's profile.
rrp_require_owner();
$id = (int) ($_GET['id'] ?? 0);
rrp_redirect($id > 0 ? 'pet-profile.php?id=' . $id : 'settings.php');
