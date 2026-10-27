<?php
declare(strict_types=1);

/** Shared JSON API guards (keeps endpoints thin: guard → ViewModel/Model → JSON). */

function rrp_api_owner(): array
{
    $owner = rrp_current_owner();
    if (!$owner) {
        rrp_json(['ok' => false, 'error' => 'Not logged in'], 401);
    }
    return $owner;
}

/** POST + JSON (or form) body + CSRF. @return array{0:array,1:array} [owner, data] */
function rrp_api_owner_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        rrp_json(['ok' => false, 'error' => 'POST required'], 405);
    }
    $owner = rrp_api_owner();
    $data = json_decode((string) file_get_contents('php://input') ?: '[]', true);
    if (!is_array($data) || !$data) {
        $data = $_POST;
    }
    if (!rrp_verify_csrf($data['csrf'] ?? null)) {
        rrp_json(['ok' => false, 'error' => 'Session expired. Refresh the page.'], 403);
    }
    // Every app call carries the phone's timezone (assets/js/*): SIPO's clock follows the owner.
    $owner = rrp_auth_sync_timezone($owner, $data['tz'] ?? null);
    return [$owner, $data];
}
