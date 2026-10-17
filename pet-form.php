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
                rrp_redirect('dashboard.php');
            }
        } else {
            $result = rrp_create_pet((int) $owner['id'], $_POST, $file);
            if ($result['ok']) {
                rrp_flash_set('ok', 'Pet added — download the QR tag.');
                rrp_redirect('tag.php?id=' . (int) $result['pet_id']);
            }
        }
        $error = $result['error'] ?? 'Could not save pet.';
    }
}

$values = $pet ?: $_POST;
$type = $values['type'] ?? 'dog';
$breedsJson = json_encode(RRP_BREED_SUGGESTIONS, JSON_UNESCAPED_UNICODE);

rrp_layout_head(($editing ? 'Edit pet' : 'Add a pet') . ' — Ring Ring Paw');
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="dashboard.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="dashboard.php">Dashboard</a>
  </div>

  <h1><?= $editing ? 'Edit pet' : 'Add a pet' ?></h1>
  <p class="lede">Up to <?= RRP_MAX_PETS ?> pets per account. Photo, type, breed, color, magic word, distinctive marks, and medical notes.</p>

  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="panel" id="petForm">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />

    <label for="image">Photo</label>
    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" />
    <?php if (!empty($pet['image_path'])): ?>
      <p class="field-hint">Current photo kept unless you choose a new one.</p>
      <img src="<?= rrp_h($pet['image_path']) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:12px;margin-top:8px" />
    <?php endif; ?>

    <label for="name">Pet name</label>
    <input id="name" name="name" required value="<?= rrp_h($values['name'] ?? '') ?>" />

    <label for="type">Type</label>
    <select id="type" name="type" required>
      <?php foreach (['dog' => 'Dog', 'cat' => 'Cat', 'rabbit' => 'Rabbit', 'custom' => 'Custom'] as $k => $label): ?>
        <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>

    <div id="customTypeWrap" style="<?= $type === 'custom' ? '' : 'display:none' ?>">
      <label for="custom_type">Custom type</label>
      <input id="custom_type" name="custom_type" value="<?= rrp_h($values['custom_type'] ?? '') ?>" />
    </div>

    <label for="breed">Breed</label>
    <input id="breed" name="breed" list="breedList" value="<?= rrp_h($values['breed'] ?? '') ?>" autocomplete="off" />
    <datalist id="breedList"></datalist>
    <p class="datalist-note">Suggestions appear for dog, cat, and rabbit.</p>

    <label for="color">Color</label>
    <input id="color" name="color" value="<?= rrp_h($values['color'] ?? '') ?>" />

    <label for="magic_word">Magic word / call keyword</label>
    <input id="magic_word" name="magic_word" value="<?= rrp_h($values['magic_word'] ?? '') ?>" />
    <p class="field-hint">A word your pet responds to — shown to you and helpers, not on the public scan page.</p>

    <label for="identifying_marks">Distinctive marks</label>
    <textarea id="identifying_marks" name="identifying_marks"><?= rrp_h($values['identifying_marks'] ?? '') ?></textarea>

    <h2 style="margin-top:20px">Medical</h2>
    <label for="medical_vaccines">Vaccines</label>
    <textarea id="medical_vaccines" name="medical_vaccines"><?= rrp_h($values['medical_vaccines'] ?? '') ?></textarea>

    <label for="medical_next_due">Next vaccine due</label>
    <input id="medical_next_due" name="medical_next_due" type="date" value="<?= rrp_h($values['medical_next_due'] ?? '') ?>" />

    <label for="medical_pills">Pills / medication</label>
    <textarea id="medical_pills" name="medical_pills"><?= rrp_h($values['medical_pills'] ?? '') ?></textarea>

    <label for="medical_conditions">Conditions</label>
    <textarea id="medical_conditions" name="medical_conditions"><?= rrp_h($values['medical_conditions'] ?? '') ?></textarea>

    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block"><?= $editing ? 'Save changes' : 'Save &amp; get QR' ?></button>
    </div>
  </form>
</div>
<script>
window.RRP_BREEDS = <?= $breedsJson ?>;
</script>
<script src="assets/js/pet-form.js" defer></script>
<?php rrp_layout_foot(); ?>
