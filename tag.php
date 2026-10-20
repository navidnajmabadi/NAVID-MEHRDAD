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
$used = rrp_pet_count((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

rrp_layout_head('QR tag — ' . $pet['name'] . ' — siPaw', [
    'extra_head' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>',
    'body_class' => 'page-tag',
]);
rrp_app_start(['nav' => false]);
?>
<div class="topbar">
  <a class="iconbtn" href="pet-profile.php?id=<?= (int) $pet['id'] ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><span class="label">Pair the tag</span></div>
  <span style="width:40px"></span>
</div>

<?php rrp_render_flash(); ?>

<div class="pad pair" style="padding-top:24px">
  <h2 class="title">Pair the tag</h2>
  <p class="sub">Print this and attach it to the collar. No phone number is printed on it.</p>
  <div class="tagdisc">
    <div class="qrwrap">
      <span class="tiny">SCAN ME</span>
      <div id="qrcode" style="background:#fff;padding:4px"></div>
      <span class="fine">Reach my owner. My location is shared only if you agree.</span>
    </div>
  </div>
  <span class="data" style="font-size:16px">TAG <?= rrp_h(strtoupper(substr((string) $pet['public_id'], -4))) ?></span>
  <span class="status data">Tag ready. <?= (int) $used ?> of <?= (int) RRP_MAX_PETS ?> used</span>
  <p class="meta data" style="word-break:break-all;font-size:11px" id="qrUrl"><?= rrp_h($petUrl) ?></p>
</div>

<div class="footer-actions">
  <button type="button" class="btn primary" id="downloadPng">Download PNG</button>
  <button type="button" class="btn outline" id="downloadPdf">Download PDF</button>
  <a class="btn outline" href="<?= rrp_h($petUrl) ?>" target="_blank" rel="noopener">Open scan page</a>
  <a class="link" href="dashboard.php">Done</a>
</div>

<script>
window.RRP_QR = {
  url: <?= json_encode($petUrl, JSON_UNESCAPED_SLASHES) ?>,
  petName: <?= json_encode($pet['name'], JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous"></script>
<script src="assets/js/tag.js" defer></script>
<?php
rrp_app_end(['nav' => false]);
