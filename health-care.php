<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

$petId = isset($_GET['pet']) ? (int) $_GET['pet'] : 0;
$pet = null;
foreach ($pets as $p) {
    if ($petId === (int) $p['id'] || (!$petId && !$pet)) {
        $pet = $p;
        $petId = (int) $p['id'];
    }
}

$tab = (string) ($_GET['tab'] ?? 'vax');
if (!in_array($tab, ['vax', 'meds', 'feed', 'vet'], true)) {
    $tab = 'vax';
}

rrp_layout_head('Health — siPaw');
rrp_app_start(['nav' => 'health']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Health</h2><?php if ($pet): ?><span class="label"><?= rrp_h($pet['name']) ?></span><?php endif; ?></div>
  <a class="iconbtn" href="<?= $pet ? 'pet-form.php?id=' . (int) $pet['id'] : 'pet-form.php' ?>" aria-label="Edit"><svg aria-hidden="true"><use href="#i-edit"/></svg></a>
</div>

<?php if (!$pets): ?>
  <div class="pad"><div class="box"><p class="sub" style="margin:0">Add a pet to track vaccines, meds, and food.</p><a class="btn primary" style="margin-top:12px" href="pet-form.php">Add a pet</a></div></div>
<?php else: ?>
  <div class="pets" role="group" aria-label="Pets" style="padding-top:4px;padding-bottom:12px">
    <?php foreach ($pets as $p): ?>
      <a class="petchip" href="health-care.php?pet=<?= (int) $p['id'] ?>&tab=<?= rrp_h($tab) ?>" aria-pressed="<?= (int) $p['id'] === $petId ? 'true' : 'false' ?>">
        <span class="ph">
          <?php if (!empty($p['image_path'])): ?><img src="<?= rrp_h($p['image_path']) ?>" alt="" /><?php else: ?>
          <svg viewBox="0 0 200 200"><use href="#<?= ($p['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg><?php endif; ?>
        </span>
        <?= rrp_h($p['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="tabs" role="tablist">
    <?php foreach (['vax' => 'Vaccines', 'meds' => 'Meds', 'feed' => 'Food', 'vet' => 'Vet'] as $k => $label): ?>
      <a href="health-care.php?pet=<?= (int) $petId ?>&tab=<?= $k ?>" role="tab" aria-pressed="<?= $tab === $k ? 'true' : 'false' ?>" style="display:flex;align-items:center;justify-content:center;height:42px;font-family:var(--f-display);font-weight:500;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:<?= $tab === $k ? 'var(--text)' : 'var(--sub)' ?>;text-decoration:none;position:relative">
        <?= rrp_h($label) ?>
        <?php if ($tab === $k): ?><span style="position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--blue)"></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="pad stack" style="margin-top:16px;gap:14px">
    <?php if ($tab === 'vax'): ?>
      <div class="box">
        <div class="row between"><span class="label">Vaccines</span><svg class="ico" aria-hidden="true"><use href="#i-syringe"/></svg></div>
        <?php if (!empty($pet['medical_vaccines'])): ?>
          <p style="margin:12px 0 0;white-space:pre-wrap;font-size:14px"><?= rrp_h($pet['medical_vaccines']) ?></p>
        <?php else: ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No vaccine notes yet.</p>
        <?php endif; ?>
        <?php if (!empty($pet['medical_next_due'])): ?>
          <dl class="kv" style="margin-top:12px">
            <dt>Next due</dt>
            <dd class="data"><?= rrp_h($pet['medical_next_due']) ?></dd>
          </dl>
        <?php endif; ?>
      </div>
      <a class="btn outline" href="pet-form.php?id=<?= (int) $petId ?>">Edit vaccine notes</a>
      <p class="soon" style="text-align:center">Scan vaccine card — coming soon</p>
    <?php elseif ($tab === 'meds'): ?>
      <div class="box">
        <span class="label">Medication</span>
        <?php if (!empty($pet['medical_pills'])): ?>
          <div class="list" style="margin-top:10px">
            <div class="li">
              <button type="button" class="check" aria-checked="false" disabled aria-hidden="true"><svg><use href="#i-check"/></svg></button>
              <div><b>Daily meds</b><small><?= rrp_h($pet['medical_pills']) ?></small></div>
            </div>
          </div>
        <?php else: ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No medication notes yet.</p>
        <?php endif; ?>
        <?php if (!empty($pet['medical_conditions'])): ?>
          <p style="margin:14px 0 0;font-size:14px"><span class="label">Conditions</span><br><?= rrp_h($pet['medical_conditions']) ?></p>
        <?php endif; ?>
      </div>
      <a class="btn outline" href="pet-form.php?id=<?= (int) $petId ?>">Edit meds</a>
      <p class="soon" style="text-align:center">Refill reminders — coming soon</p>
    <?php elseif ($tab === 'feed'): ?>
      <div class="box">
        <span class="label">Food</span>
        <p class="sub" style="margin:12px 0 0;font-size:13px">
          Daily feeding checklist and gram meter will live here. For now, keep diet notes in conditions or edit the pet profile.
        </p>
        <div class="meter" style="margin-top:16px" aria-hidden="true"><i style="width:40%"></i></div>
      </div>
      <p class="soon" style="text-align:center">Feeding log — coming soon</p>
    <?php else: ?>
      <div class="box">
        <span class="label">Vet</span>
        <?php if (!empty($pet['medical_next_due'])): ?>
          <p style="margin:12px 0 0;font-weight:500">Next vaccine due <?= rrp_h($pet['medical_next_due']) ?></p>
        <?php else: ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No appointment on file yet.</p>
        <?php endif; ?>
        <?php if (!empty($pet['medical_conditions'])): ?>
          <p style="margin:12px 0 0;font-size:14px;white-space:pre-wrap"><?= rrp_h($pet['medical_conditions']) ?></p>
        <?php endif; ?>
      </div>
      <button type="button" class="btn outline" disabled>Start walk <span class="soon">(coming soon)</span></button>
    <?php endif; ?>
    <div class="gap"></div>
  </div>
<?php endif; ?>
<?php
rrp_app_end(['nav' => 'health', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
