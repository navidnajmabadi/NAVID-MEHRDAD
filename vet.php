<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/views/health_dashboard.php';

// Read-only health dashboard for a vet, opened from the owner's private link.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');

$share = rrp_share_resolve((string) ($_GET['t'] ?? ''));
$extra = '<meta name="robots" content="noindex, nofollow" /><link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />';
if (!$share) {
    http_response_code(404);
    rrp_layout_head('Link expired — SIPAW', ['extra_head' => $extra, 'body_class' => 'page-care page-vet']);
    rrp_app_start(['nav' => false]);
    echo '<div class="pad stack"><h2 class="title">This link has expired</h2><p class="sub">Ask the owner to create a new one in SIPAW → Health → Share with your vet.</p></div>';
    rrp_app_end(['nav' => false, 'push' => false]);
    exit;
}
$vm = rrp_vm_health($share['owner'], $share['pet']);
rrp_layout_head($share['pet']['name'] . ' — health record', ['extra_head' => $extra, 'body_class' => 'page-care page-vet']);
rrp_app_start(['nav' => false, 'class' => 'app-vet']);
?>
<div class="pad stack care-page">
  <header class="vet-head">
    <?php rrp_logo_wordmark(); ?>
    <p class="meta">Shared by <?= rrp_h(rrp_sipo_owner_nickname($share['owner'])) ?> · read-only · link valid until <?= rrp_h(substr((string) $share['link']['expires_at'], 0, 10)) ?></p>
  </header>
  <?php rrp_view_health_dashboard($vm, true); ?>
  <p class="meta">Owner-reported data and values read from uploaded reports. Please confirm against your clinic records.</p>
</div>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
