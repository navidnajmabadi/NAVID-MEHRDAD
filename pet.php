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

$tagTail = $pet ? substr((string) $pet['public_id'], -4) : '----';

rrp_layout_head(
    $pet ? ('Found ' . $pet['name'] . '? — siPaw') : 'Tag not found — siPaw',
    [
        'canonical' => $pet ? rrp_app_url('pet.php?id=' . rawurlencode($pet['public_id'])) : rrp_app_url(),
        'body_class' => 'page-scan',
        'description' => $pet
            ? ('You found ' . $pet['name'] . '. Message the owner on siPaw.')
            : 'siPaw tag not found.',
    ]
);
rrp_app_start(['nav' => false]);
?>
<div class="browserbar data"><svg aria-hidden="true"><use href="#i-lock"/></svg>ringringpaw.nakilab.com/t/<?= rrp_h($tagTail) ?></div>

<div class="pad stack" style="gap:16px">
  <div class="tilewrap" style="display:flex;justify-content:center;padding:8px 0 4px">
    <?php rrp_logo_tile(); ?>
  </div>

  <?php if (!$pet || !$owner): ?>
    <h2 class="title" style="text-align:center;font-size:28px">Tag not found</h2>
    <p class="sub" style="text-align:center">This QR isn’t linked to a pet yet, or the account isn’t active.</p>
  <?php else: ?>
    <h2 class="title" style="text-align:center;font-size:28px">You found <?= rrp_h($pet['name']) ?></h2>
    <p class="sub" style="text-align:center">Thank you. Call or message the owner — location only if you agree.</p>

    <div class="foundpet">
      <div class="ph">
        <?php if (!empty($pet['image_path'])): ?>
          <img src="<?= rrp_h($pet['image_path']) ?>" alt="<?= rrp_h($pet['name']) ?>" />
        <?php else: ?>
          <svg viewBox="0 0 200 200"><use href="#<?= ($pet['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
        <?php endif; ?>
      </div>
      <div class="stack" style="gap:4px">
        <b style="font-weight:500"><?= rrp_h($pet['name']) ?><?= $pet['breed'] ? ', ' . rrp_h($pet['breed']) : '' ?></b>
        <small class="sub" style="font-size:13px"><?= rrp_h(rrp_pet_type_label($pet)) ?><?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?></small>
        <span class="status data" style="margin-top:4px">Owner notified</span>
      </div>
    </div>

    <?php if (!empty($pet['magic_word'])): ?>
      <div class="magic">
        <span class="label">Say this and they will come</span>
        <b><?= rrp_h($pet['magic_word']) ?></b>
      </div>
    <?php endif; ?>

    <a class="btn primary" href="tel:<?= rrp_h(preg_replace('/\s+/', '', $owner['phone'])) ?>">
      <svg aria-hidden="true"><use href="#i-phone"/></svg>Call <?= rrp_h($owner['name']) ?>
    </a>

    <div class="box" id="shareCard">
      <span class="label">Share live location?</span>
      <p class="sub" style="margin:8px 0 12px;font-size:13px">Optional. Your browser will ask before anything is sent.</p>
      <div class="row" style="gap:10px">
        <button type="button" class="btn outline" id="shareOnce" style="flex:1">Share once</button>
        <button type="button" class="btn solid" id="shareLive" style="flex:1">Share live 30 min</button>
      </div>
      <div class="flash flash-ok" id="status" style="display:none;margin:12px 0 0" role="status"></div>
      <button type="button" class="btn lost" id="stopLive" style="display:none;margin-top:12px">Stop sharing</button>
    </div>

    <dl class="kv">
      <dt>Look for</dt>
      <dd><?= rrp_h($pet['identifying_marks'] ?: '—') ?></dd>
      <dt>Health</dt>
      <dd><?= rrp_h(trim(implode(' · ', array_filter([
          $pet['medical_pills'] ?? null,
          $pet['medical_conditions'] ?? null,
      ]))) ?: '—') ?></dd>
      <dt>Vaccines</dt>
      <dd><?= rrp_h($pet['medical_vaccines'] ?: '—') ?></dd>
      <dt>Owner</dt>
      <dd><?= rrp_h($owner['name']) ?></dd>
    </dl>

    <p class="sub" style="font-size:12px;text-align:center">No app needed. Any phone camera can read a siPaw tag.</p>
  <?php endif; ?>
  <div class="gap"></div>
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
<?php
rrp_app_end(['nav' => false, 'push' => false]);
