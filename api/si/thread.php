<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/** Chat ViewModel as JSON (same data si-chat.php renders). ?new=1 → new-pet onboarding chat. */
$owner = rrp_api_owner();
rrp_json(['ok' => true] + rrp_vm_chat($owner, !empty($_GET['new'])));
