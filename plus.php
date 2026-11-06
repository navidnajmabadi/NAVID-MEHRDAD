<?php
declare(strict_types=1);

// Plus now lives in Billing (Menu, Billing). Old links and bookmarks still arrive here.
$q = (string) ($_SERVER['QUERY_STRING'] ?? '');
header('Location: billing.php' . ($q !== '' ? '?' . $q : ''), true, 301);
exit;
