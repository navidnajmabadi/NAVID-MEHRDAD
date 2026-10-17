<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$petId = (int) ($_GET['id'] ?? 0);
$pet = rrp_pet_by_id($petId, (int) $owner['id']);
if (!$pet) {
    rrp_flash_set('error', 'Pet not found.');
    rrp_redirect('dashboard.php');
}

$petUrl = rrp_app_url('pet.php?id=' . rawurlencode($pet['public_id']));

rrp_layout_head('QR tag — ' . $pet['name'] . ' — Ring Ring Paw', [
    'extra_head' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>',
]);
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="dashboard.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="dashboard.php">Dashboard</a>
  </div>

  <h1><?= rrp_h($pet['name']) ?>’s QR tag</h1>
  <p class="lede">Print this and attach it to the collar. Anyone who scans it can call you.</p>
  <?php rrp_render_flash(); ?>

  <div class="panel">
    <div class="qr-wrap"><div id="qrcode"></div></div>
    <p class="meta" style="word-break:break-all;margin-top:12px" id="qrUrl"><?= rrp_h($petUrl) ?></p>
    <div class="stack-actions">
      <button type="button" class="btn btn-primary btn-block" id="downloadPng">Download PNG</button>
      <button type="button" class="btn btn-secondary btn-block" id="downloadPdf">Download PDF</button>
      <a class="btn btn-ghost btn-block" href="<?= rrp_h($petUrl) ?>" target="_blank" rel="noopener">Open public page</a>
    </div>
  </div>
</div>

<script>
window.RRP_QR = {
  url: <?= json_encode($petUrl, JSON_UNESCAPED_SLASHES) ?>,
  petName: <?= json_encode($pet['name'], JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous"></script>
<script src="assets/js/tag.js" defer></script>
<?php rrp_layout_foot(); ?>
