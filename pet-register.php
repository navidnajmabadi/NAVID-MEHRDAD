<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// New pets are onboarded inside their own SIPO chat (one chat per pet, brief §2).
rrp_require_owner();
rrp_redirect('si-chat.php?new=1');
