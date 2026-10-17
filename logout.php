<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
rrp_logout();
rrp_redirect('index.php');
