<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
rrp_admin_logout();
rrp_redirect('login.php');
