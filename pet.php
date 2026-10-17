<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$publicId = trim((string) ($_GET['id'] ?? ''));
$pet = $publicId !== '' ? rrp_pet_by_public_id($publicId) : null;
$owner = null;
$scanId = null;

if ($pet) {
    $stmt = rrp_db()->prepare('SELECT id, name, phone, email, status FROM owners WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $pet['owner_id']]);
    $owner = $stmt->fetch() ?: null;

    if ($owner && $owner['status'] === 'active') {
        // Debounce: one scan+notify per browser session per pet (avoid refresh spam).
        rrp_start_session();
        $seenKey = 'scanned_' . $pet['public_id'];
        if (empty($_SESSION[$seenKey])) {
            $created = rrp_record_scan((int) $pet['id'], 'none');
            $scanId = $created['scan_id'];
            rrp_notify_scan($pet, $owner, $scanId, 'none');
            $_SESSION[$seenKey] = $scanId;
        } else {
            $scanId = (int) $_SESSION[$seenKey];
        }
    }
}

rrp_layout_head(
    $pet ? ('Found ' . $pet['name'] . '? — Ring Ring Paw') : 'Tag not found — Ring Ring Paw',
    ['canonical' => $pet ? rrp_app_url('pet.php?id=' . rawurlencode($pet['public_id'])) : rrp_app_url()]
);
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="index.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
  </div>

  <?php if (!$pet || !$owner): ?>
    <h1>Tag not found</h1>
    <p class="lede">This QR code isn’t linked to a pet yet, or the account isn’t active.</p>
  <?php else: ?>
    <div class="scan-hero">
      <?php if (!empty($pet['image_path'])): ?>
        <img class="scan-photo" src="<?= rrp_h($pet['image_path']) ?>" alt="<?= rrp_h($pet['name']) ?>" />
      <?php endif; ?>
      <h1>You found <?= rrp_h($pet['name']) ?></h1>
      <p class="lede" style="margin-bottom:8px">
        <?= rrp_h(rrp_pet_type_label($pet)) ?>
        <?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?>
      </p>
    </div>

    <div class="panel">
      <h2>Call the owner</h2>
      <p style="margin:0 0 6px"><strong><?= rrp_h($owner['name']) ?></strong></p>
      <p class="meta" style="margin:0 0 14px"><?= rrp_h($owner['phone']) ?></p>
      <a class="btn btn-call btn-block" href="tel:<?= rrp_h(preg_replace('/\s+/', '', $owner['phone'])) ?>">Call <?= rrp_h($owner['name']) ?></a>
    </div>

    <div class="panel" id="shareCard">
      <h2>Share live location?</h2>
      <p class="lede" style="margin-bottom:12px">Optional. Your browser will ask before anything is sent. Helps the owner find you on a map.</p>
      <div class="row">
        <button type="button" class="btn btn-secondary" id="shareOnce">Share once</button>
        <button type="button" class="btn btn-primary" id="shareLive">Share live 30 min</button>
      </div>
      <div class="flash flash-ok" id="status" style="display:none;margin-top:12px" role="status"></div>
      <button type="button" class="btn btn-danger btn-block" id="stopLive" style="display:none;margin-top:12px">Stop sharing</button>
    </div>
  <?php endif; ?>
</div>

<?php if ($pet && $owner && $scanId): ?>
<script>
window.RRP_SCAN = {
  petPublicId: <?= json_encode($pet['public_id']) ?>,
  initialScanId: <?= (int) $scanId ?>,
  csrf: <?= json_encode(rrp_csrf_token()) ?>
};
</script>
<script src="assets/js/scan.js" defer></script>
<?php endif; ?>
<?php rrp_layout_foot(); ?>
