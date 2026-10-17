<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$scans = rrp_scans_for_owner((int) $owner['id']);
$notes = rrp_notifications_for_owner((int) $owner['id']);
rrp_mark_notifications_read((int) $owner['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_pet') {
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        rrp_delete_pet((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
        rrp_flash_set('ok', 'Pet removed.');
        rrp_redirect('dashboard.php');
    }
}

$extra = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />';
rrp_layout_head('Dashboard — Ring Ring Paw', ['extra_head' => $extra]);
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="index.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="logout.php">Log out</a>
  </div>

  <h1>Hi, <?= rrp_h($owner['name']) ?></h1>
  <p class="lede">Your pets, QR tags, scan alerts, and live map when a finder shares location.</p>
  <?php rrp_render_flash(); ?>

  <div class="nav-links">
    <?php if (count($pets) < RRP_MAX_PETS): ?>
      <a class="btn btn-primary" href="pet-form.php">Add pet</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="#scans">Scans</a>
  </div>

  <section class="panel" aria-labelledby="petsHeading">
    <h2 id="petsHeading">Your pets (<?= count($pets) ?>/<?= RRP_MAX_PETS ?>)</h2>
    <?php if (!$pets): ?>
      <p class="empty">No pets yet. Add one to generate a QR collar tag.</p>
    <?php else: ?>
      <?php foreach ($pets as $pet): ?>
        <div class="panel panel-soft" style="margin-top:12px;margin-bottom:0">
          <div class="pet-card">
            <?php if (!empty($pet['image_path'])): ?>
              <img class="pet-thumb" src="<?= rrp_h($pet['image_path']) ?>" alt="<?= rrp_h($pet['name']) ?>" />
            <?php else: ?>
              <div class="pet-thumb pet-thumb-placeholder" aria-hidden="true"><?= rrp_h(substr($pet['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div>
              <strong><?= rrp_h($pet['name']) ?></strong>
              <div class="meta"><?= rrp_h(rrp_pet_type_label($pet)) ?><?= $pet['breed'] ? ' · ' . rrp_h($pet['breed']) : '' ?><?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?></div>
              <div class="row" style="margin-top:10px">
                <a class="btn btn-secondary" href="tag.php?id=<?= (int) $pet['id'] ?>">QR tag</a>
                <a class="btn btn-ghost" href="pet-form.php?id=<?= (int) $pet['id'] ?>">Edit</a>
              </div>
              <form method="post" style="margin-top:8px" onsubmit="return confirm('Remove this pet and its QR tag?');">
                <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete_pet" />
                <input type="hidden" name="pet_id" value="<?= (int) $pet['id'] ?>" />
                <button type="submit" class="btn btn-ghost" style="color:var(--color-danger)">Remove</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="panel" id="inbox" aria-labelledby="inboxHeading">
    <h2 id="inboxHeading">Inbox</h2>
    <?php if (!$notes): ?>
      <p class="empty">No alerts yet. You’ll get an email and an inbox note when someone scans a tag.</p>
    <?php else: ?>
      <?php foreach ($notes as $n): ?>
        <div class="panel panel-soft" style="margin-top:10px;margin-bottom:0">
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
            <strong><?= rrp_h($n['title']) ?></strong>
            <?php if (empty($n['read_at'])): ?><span class="pill pill-unread">New</span><?php endif; ?>
          </div>
          <p class="meta" style="white-space:pre-wrap;margin:6px 0 0"><?= rrp_h($n['body']) ?></p>
          <p class="meta" style="margin:6px 0 0"><?= rrp_h($n['created_at']) ?> UTC</p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="panel" id="scans" aria-labelledby="scansHeading">
    <h2 id="scansHeading">Scan activity</h2>
    <div id="scanList">
      <?php if (!$scans): ?>
        <p class="empty">No scans yet. Print a QR and ask someone to scan it on another phone.</p>
      <?php else: ?>
        <?php foreach ($scans as $s): ?>
          <div class="panel panel-soft" style="margin-top:10px;margin-bottom:0;display:flex;justify-content:space-between;gap:10px;align-items:center">
            <div>
              <strong><?= rrp_h($s['pet_name']) ?></strong>
              <div class="meta">
                <?= rrp_h($s['mode']) ?>
                · <?= rrp_h($s['scanned_at']) ?> UTC
                <?php if ($s['lat'] !== null): ?>
                  · <?= rrp_h((string) $s['lat']) ?>, <?= rrp_h((string) $s['lng']) ?>
                <?php endif; ?>
              </div>
            </div>
            <span class="pill <?= !empty($s['active']) ? 'pill-live' : 'pill-ended' ?>">
              <?= !empty($s['active']) ? 'LIVE' : 'ended' ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="panel" id="mapCard" style="display:none" aria-labelledby="mapHeading">
    <h2 id="mapHeading">Live location</h2>
    <p class="meta" id="mapMeta"></p>
    <div id="map"></div>
  </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
<script src="assets/js/dashboard.js" defer></script>
<?php rrp_layout_foot(); ?>
