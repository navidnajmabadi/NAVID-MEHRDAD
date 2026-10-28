<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$petId = (int) ($_GET['id'] ?? 0);
$pet = $petId ? rrp_pet_by_id($petId, (int) $owner['id']) : null;
if (!$pet) {
    rrp_flash_set('error', 'Pet not found.');
    rrp_redirect('dashboard.php');
}

$nav = rrp_owner_nav_context((int) $owner['id']);
$myLost = rrp_lost_for_owner((int) $owner['id']);
$open = null;
foreach ($myLost as $la) {
    if ($la['status'] === 'open' && (int) $la['pet_id'] === (int) $pet['id']) {
        $open = $la;
        break;
    }
}
$scanUrl = rrp_app_url('pet.php?id=' . rawurlencode($pet['public_id']));

rrp_layout_head($pet['name'] . ' — SIPAW');
rrp_app_start(['nav' => 'settings']);
?>
<div class="profile-hero">
  <a class="iconbtn back" href="dashboard.php?pet=<?= (int) $pet['id'] ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <a class="iconbtn edit" href="pet-form.php?id=<?= (int) $pet['id'] ?>" aria-label="Edit"><svg aria-hidden="true"><use href="#i-edit"/></svg></a>
  <?php if (!empty($pet['image_path'])): ?>
    <img class="pet-photo" src="<?= rrp_h($pet['image_path']) ?>" alt="<?= rrp_h($pet['name']) ?>" />
  <?php else: ?>
    <svg class="bigpet" viewBox="0 0 200 200" aria-hidden="true"><use href="#<?= ($pet['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
  <?php endif; ?>
</div>

<div class="pad stack" style="margin-top:18px;gap:16px">
  <div>
    <h2 class="title" style="font-size:36px"><?= rrp_h($pet['name']) ?></h2>
    <p class="sub" style="margin-top:8px;font-size:14px">
      <?= rrp_h(rrp_pet_type_label($pet)) ?>
      <?= $pet['breed'] ? ' · ' . rrp_h($pet['breed']) : '' ?>
      <?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?>
    </p>
    <?php if ($open): ?>
      <span class="status red data" style="margin-top:10px">Lost mode on</span>
    <?php else: ?>
      <span class="status live data" style="margin-top:10px">Tag <?= rrp_h(substr((string) $pet['public_id'], -4)) ?> active</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($pet['magic_word'])): ?>
    <div class="magic">
      <span class="label">Magic word</span>
      <b><?= rrp_h($pet['magic_word']) ?></b>
    </div>
  <?php endif; ?>

  <dl class="kv">
    <dt>Vaccines</dt>
    <dd><?= rrp_h($pet['medical_vaccines'] ?: '—') ?></dd>
    <dt>Next due</dt>
    <dd class="data"><?= rrp_h($pet['medical_next_due'] ?: '—') ?></dd>
    <dt>Meds</dt>
    <dd><?= rrp_h($pet['medical_pills'] ?: '—') ?></dd>
    <dt>Conditions</dt>
    <dd><?= rrp_h($pet['medical_conditions'] ?: '—') ?></dd>
  </dl>

  <?php if (!empty($pet['identifying_marks'])): ?>
    <div class="box">
      <span class="label">Distinctive marks</span>
      <p style="margin:10px 0 0;font-size:14px;white-space:pre-wrap"><?= rrp_h($pet['identifying_marks']) ?></p>
    </div>
  <?php endif; ?>

  <div class="actions3">
    <a href="pet.php?id=<?= rrp_h(rawurlencode($pet['public_id'])) ?>" target="_blank" rel="noopener">
      <svg aria-hidden="true"><use href="#i-qr"/></svg>Scan page
    </a>
    <a href="tag.php?id=<?= (int) $pet['id'] ?>">
      <svg aria-hidden="true"><use href="#i-poster"/></svg>Tag / QR
    </a>
    <a href="health-care.php?pet=<?= (int) $pet['id'] ?>">
      <svg aria-hidden="true"><use href="#i-doc"/></svg>Passport
    </a>
  </div>

  <?php if ($open): ?>
    <a class="btn lost" href="lost.php?pet=<?= (int) $pet['id'] ?>">Manage Lost mode</a>
  <?php else: ?>
    <a class="btn outline" href="lost.php?pet=<?= (int) $pet['id'] ?>">Lost mode</a>
  <?php endif; ?>

  <p class="sub data" style="font-size:11px;word-break:break-all"><?= rrp_h($scanUrl) ?></p>
  <div class="gap"></div>
</div>
<style>
.actions3 a{padding:14px 6px;display:flex;flex-direction:column;align-items:center;gap:8px;font-family:var(--f-display);font-weight:500;font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;text-decoration:none;color:inherit}
.actions3 a + a{border-left:1px solid var(--line)}
.actions3 a svg{width:22px;height:22px}
</style>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
