<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$petId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pet = $petId ? rrp_pet_by_id($petId, (int) $owner['id']) : null;
$editing = (bool) $pet;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $file = $_FILES['image'] ?? null;
        if ($editing) {
            $result = rrp_update_pet((int) $owner['id'], $petId, $_POST, $file);
            if ($result['ok']) {
                rrp_flash_set('ok', 'Pet updated.');
                rrp_redirect('pet-profile.php?id=' . $petId);
            }
        } else {
            $result = rrp_create_pet((int) $owner['id'], $_POST, $file);
            if ($result['ok']) {
                rrp_flash_set('ok', 'Pet added — pair the QR tag.');
                rrp_redirect('tag.php?id=' . (int) $result['pet_id']);
            }
        }
        $error = $result['error'] ?? 'Could not save pet.';
    }
}

$values = $pet ?: $_POST;
$type = $values['type'] ?? 'dog';
$petCount = rrp_pet_count((int) $owner['id']);
$petLimit = rrp_owner_pet_limit($owner);
$breedsJson = json_encode(RRP_BREED_SUGGESTIONS, JSON_UNESCAPED_UNICODE);

rrp_layout_head(($editing ? 'Edit pet' : 'Add a pet') . ' — SIPAW', ['body_class' => 'page-add']);
rrp_app_start(['nav' => false]);
?>
<form method="post" enctype="multipart/form-data" id="petForm" class="scr" style="display:flex;flex-direction:column;min-height:100%">
  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
  <input type="hidden" name="type" id="type" value="<?= rrp_h($type) ?>" />
  <input type="hidden" name="custom_type" id="custom_type" value="<?= rrp_h($values['custom_type'] ?? '') ?>" />

  <div class="topbar">
    <a class="iconbtn" href="<?= $editing ? 'pet-profile.php?id=' . (int) $petId : 'dashboard.php' ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
    <div class="center"><span class="label" id="add-sub"><?= $editing ? 'Edit pet' : 'Step 1 of 4' ?></span></div>
    <span style="width:40px"></span>
  </div>

  <?php if (!$editing): ?>
  <div class="progress" id="add-bar" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i><i></i></div>
  <?php endif; ?>

  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <div class="scroll" style="flex:1">
    <div class="step on" data-step="1" data-sub="Species">
      <h2 class="title"><?= $editing ? 'Edit pet' : 'Add a pet' ?></h2>
      <p class="sub"><?= $editing ? 'Update photo and species.' : 'Start with who you are protecting. You can add up to ' . (int) RRP_MAX_PETS . ' pets.' ?></p>
      <div class="choice" id="species">
        <button type="button" class="opt" data-val="dog" aria-pressed="<?= $type === 'dog' ? 'true' : 'false' ?>">
          <svg class="pv" viewBox="0 0 200 200"><use href="#pet-dog"/></svg><span>Dog</span>
        </button>
        <button type="button" class="opt" data-val="cat" aria-pressed="<?= $type === 'cat' ? 'true' : 'false' ?>">
          <svg class="pv" viewBox="0 0 200 200"><use href="#pet-cat"/></svg><span>Cat</span>
        </button>
      </div>
      <div class="chips" style="margin-top:8px">
        <button type="button" class="chip" data-val="rabbit" aria-pressed="<?= $type === 'rabbit' ? 'true' : 'false' ?>">Rabbit</button>
        <button type="button" class="chip" data-val="custom" aria-pressed="<?= $type === 'custom' ? 'true' : 'false' ?>">Other</button>
      </div>
      <div id="customTypeWrap" style="<?= $type === 'custom' ? '' : 'display:none' ?>;margin-top:12px">
        <div class="field"><label for="custom_type_vis">Custom type</label><div class="in"><input id="custom_type_vis" value="<?= rrp_h($values['custom_type'] ?? '') ?>" /></div></div>
      </div>
      <label class="photo-drop" for="image">
        <svg class="ico" style="width:24px;height:24px" aria-hidden="true"><use href="#i-camera"/></svg>
        <b>Add a clear photo of the face</b>
        Finders see it on the scan page and the lost poster.
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" />
        <?php if (!empty($pet['image_path'])): ?>
          <img id="photo-prev" src="<?= rrp_h($pet['image_path']) ?>" alt="" />
        <?php else: ?>
          <img id="photo-prev" alt="" hidden />
        <?php endif; ?>
      </label>
    </div>

    <div class="step" data-step="2" data-sub="Breed" <?= $editing ? 'style="display:flex"' : '' ?>>
      <h2 class="title">Breed</h2>
      <div class="field"><label for="breed">Breed</label><div class="in"><input id="breed" name="breed" list="breedList" value="<?= rrp_h($values['breed'] ?? '') ?>" autocomplete="off" placeholder="Persian, Beagle, mixed" /></div></div>
      <datalist id="breedList"></datalist>
      <div class="field"><label for="color">Color</label><div class="in"><input id="color" name="color" value="<?= rrp_h($values['color'] ?? '') ?>" /></div></div>
    </div>

    <div class="step" data-step="3" data-sub="Basics" <?= $editing ? 'style="display:flex"' : '' ?>>
      <h2 class="title">The basics</h2>
      <div class="field"><label for="name">Name</label><div class="in"><input id="name" name="name" required value="<?= rrp_h($values['name'] ?? '') ?>" /></div></div>
      <p class="sub" style="font-size:13px">Birthday, weight, sex, and microchip fields are shown in the prototype and will save when those columns ship. Name is required now.</p>
    </div>

    <div class="step" data-step="4" data-sub="Marks and health" <?= $editing ? 'style="display:flex"' : '' ?>>
      <h2 class="title">Help finders know them</h2>
      <div class="field">
        <label for="identifying_marks">Distinctive marks</label>
        <div class="in area"><textarea id="identifying_marks" name="identifying_marks"><?= rrp_h($values['identifying_marks'] ?? '') ?></textarea></div>
      </div>
      <div class="field">
        <label for="magic_word">Magic word</label>
        <div class="in"><input id="magic_word" name="magic_word" value="<?= rrp_h($values['magic_word'] ?? '') ?>" /></div>
        <small>The word they always come to. Finders see it on the scan page.</small>
      </div>
      <div class="field">
        <label for="medical_vaccines">Vaccines</label>
        <div class="in area"><textarea id="medical_vaccines" name="medical_vaccines"><?= rrp_h($values['medical_vaccines'] ?? '') ?></textarea></div>
      </div>
      <div class="field">
        <label for="medical_next_due">Next vaccine due</label>
        <div class="in"><input id="medical_next_due" name="medical_next_due" type="date" value="<?= rrp_h($values['medical_next_due'] ?? '') ?>" /></div>
      </div>
      <div class="field">
        <label for="medical_pills">Pills / medication</label>
        <div class="in area"><textarea id="medical_pills" name="medical_pills"><?= rrp_h($values['medical_pills'] ?? '') ?></textarea></div>
      </div>
      <div class="field">
        <label for="medical_conditions">Conditions</label>
        <div class="in area"><textarea id="medical_conditions" name="medical_conditions"><?= rrp_h($values['medical_conditions'] ?? '') ?></textarea></div>
      </div>
    </div>
  </div>

  <div class="footer-actions">
    <?php if ($editing): ?>
      <button class="btn primary" type="submit">Save changes</button>
      <a class="link" href="pet-profile.php?id=<?= (int) $petId ?>">Cancel</a>
    <?php else: ?>
      <button class="btn primary" type="button" id="add-next">Continue</button>
      <button class="btn primary" type="submit" id="add-save" hidden>Save &amp; get QR</button>
      <a class="link" href="dashboard.php" id="add-skip">Skip for now</a>
      <p class="data" style="font-size:11px;color:var(--meta)"><?= (int) $petCount ?> of <?= (int) RRP_MAX_PETS ?> used</p>
    <?php endif; ?>
  </div>
</form>
<script>
window.RRP_BREEDS = <?= $breedsJson ?>;
window.RRP_PET_WIZARD = <?= $editing ? 'false' : 'true' ?>;
</script>
<script src="assets/js/pet-form.js" defer></script>
<script src="assets/js/pet-wizard.js" defer></script>
<?php
rrp_app_end(['nav' => false]);
