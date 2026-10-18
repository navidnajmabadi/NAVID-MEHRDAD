<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        if ($action === 'delete_pet') {
            rrp_delete_pet((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
            rrp_flash_set('ok', 'Pet removed.');
            rrp_redirect('dashboard.php');
        }
        if ($action === 'lost_trigger') {
            $result = rrp_trigger_lost((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
            if (!empty($result['ok'])) {
                $n = (int) ($result['recipients'] ?? 0);
                rrp_flash_set(
                    'ok',
                    $n > 0
                        ? "LOST HELP ME sent to {$n} nearby owner" . ($n === 1 ? '' : 's') . '.'
                        : 'LOST HELP ME started. No nearby owners within 25 km yet — alerts will still escalate on schedule if helpers join.'
                );
            } else {
                rrp_flash_set('error', $result['error'] ?? 'Could not start alert.');
            }
            rrp_redirect('dashboard.php');
        }
        if ($action === 'lost_found') {
            $result = rrp_mark_lost_found((int) $owner['id'], (int) ($_POST['alert_id'] ?? 0));
            if (!empty($result['ok'])) {
                $alert = rrp_lost_by_id((int) ($_POST['alert_id'] ?? 0));
                $petName = $alert['pet_name'] ?? 'your pet';
                rrp_redirect('rescued.php?pet=' . rawurlencode($petName));
            }
            rrp_flash_set('error', $result['error'] ?? 'Could not mark found.');
            rrp_redirect('dashboard.php');
        }
    }
}

$pets = rrp_pets_by_owner((int) $owner['id']);
$scans = rrp_scans_for_owner((int) $owner['id']);
$notes = rrp_notifications_for_owner((int) $owner['id']);
$myLost = rrp_lost_for_owner((int) $owner['id']);
$nearbyLost = rrp_lost_inbox_for_owner((int) $owner['id']);
rrp_mark_notifications_read((int) $owner['id']);

$openLostByPet = [];
foreach ($myLost as $la) {
    if ($la['status'] === 'open') {
        $openLostByPet[(int) $la['pet_id']] = $la;
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
  <p class="lede">Your pets, QR tags, LOST HELP ME, scan alerts, and live map when a finder shares location.</p>
  <?php rrp_render_flash(); ?>

  <div class="nav-links">
    <?php if (count($pets) < RRP_MAX_PETS): ?>
      <a class="btn btn-primary" href="pet-form.php">Add pet</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="account.php">Account</a>
    <a class="btn btn-ghost" href="support.php">Support</a>
    <a class="btn btn-ghost" href="#scans">Scans</a>
  </div>

  <section class="panel panel-soft" id="pushBanner" aria-labelledby="pushHeading">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
      <h2 id="pushHeading" data-push-title style="margin:0;font-size:1.05rem">Phone notifications</h2>
      <span class="pill pill-ended" data-push-badge>…</span>
    </div>
    <p class="meta" data-push-hint style="margin:8px 0 0">Checking push status…</p>
    <?php if (!rrp_vapid_configured()): ?>
      <p class="field-hint" style="margin-top:8px">
        Server VAPID keys are empty — email and inbox still work.
        Admin: add keys to <code>domains/…/config/ringringpaw.php</code> (see docs/WEB_PUSH.md).
      </p>
    <?php endif; ?>
    <button type="button" class="btn btn-secondary" id="pushEnableBtn" data-push-enable style="margin-top:12px" hidden>
      Enable notifications
    </button>
  </section>

  <?php if ($nearbyLost): ?>
  <section class="panel panel-lost" aria-labelledby="nearbyHeading">
    <h2 id="nearbyHeading">Nearby LOST alerts</h2>
    <p class="meta">Owners within ~25 km of your registered address need a hand.</p>
    <?php foreach ($nearbyLost as $la): ?>
      <a class="lost-teaser" href="lost-alert.php?id=<?= (int) $la['id'] ?>">
        <?php if (!empty($la['image_path'])): ?>
          <img src="<?= rrp_h($la['image_path']) ?>" alt="" width="56" height="56" />
        <?php endif; ?>
        <span>
          <strong><?= rrp_h($la['pet_name']) ?></strong>
          <span class="meta">~<?= rrp_h((string) $la['distance_km']) ?> km · magic word <?= rrp_h($la['magic_word'] ?: '—') ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="panel" aria-labelledby="petsHeading">
    <h2 id="petsHeading">Your pets (<?= count($pets) ?>/<?= RRP_MAX_PETS ?>)</h2>
    <?php if (!$pets): ?>
      <p class="empty">No pets yet. Add one to generate a QR collar tag.</p>
    <?php else: ?>
      <?php foreach ($pets as $pet): ?>
        <?php $open = $openLostByPet[(int) $pet['id']] ?? null; ?>
        <div class="panel panel-soft" style="margin-top:12px;margin-bottom:0">
          <div class="pet-card">
            <?php if (!empty($pet['image_path'])): ?>
              <img class="pet-thumb" src="<?= rrp_h($pet['image_path']) ?>" alt="<?= rrp_h($pet['name']) ?>" />
            <?php else: ?>
              <div class="pet-thumb pet-thumb-placeholder" aria-hidden="true"><?= rrp_h(substr($pet['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div>
              <strong><?= rrp_h($pet['name']) ?></strong>
              <?php if ($open): ?><span class="pill pill-lost">LOST</span><?php endif; ?>
              <div class="meta"><?= rrp_h(rrp_pet_type_label($pet)) ?><?= $pet['breed'] ? ' · ' . rrp_h($pet['breed']) : '' ?><?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?></div>
              <div class="row" style="margin-top:10px">
                <a class="btn btn-secondary" href="tag.php?id=<?= (int) $pet['id'] ?>">QR tag</a>
                <a class="btn btn-ghost" href="pet-form.php?id=<?= (int) $pet['id'] ?>">Edit</a>
              </div>
              <?php if ($open): ?>
                <form method="post" style="margin-top:10px">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="lost_found" />
                  <input type="hidden" name="alert_id" value="<?= (int) $open['id'] ?>" />
                  <button type="submit" class="btn btn-primary btn-block">Mark Found — Yoho rescued</button>
                </form>
                <?php
                  $recipStmt = rrp_db()->prepare('SELECT COUNT(*) FROM lost_alert_recipients WHERE lost_alert_id = ?');
                  $recipStmt->execute([(int) $open['id']]);
                  $recipN = (int) $recipStmt->fetchColumn();
                ?>
                <?php if ($recipN === 0): ?>
                  <p class="field-hint" style="color:var(--color-danger)">No nearby owners within 25 km yet (geocode/address). <a href="lost-alert.php?id=<?= (int) $open['id'] ?>">Alert details</a></p>
                <?php else: ?>
                  <p class="field-hint"><?= $recipN ?> nearby owner<?= $recipN === 1 ? '' : 's' ?> notified · <a href="lost-alert.php?id=<?= (int) $open['id'] ?>">View alert</a></p>
                <?php endif; ?>
                <p class="field-hint">Pings: <?= (int) $open['ping_count'] ?>/<?= count(RRP_LOST_PING_OFFSETS) ?> · next <?= rrp_h((string) ($open['next_ping_at'] ?? 'done')) ?> UTC</p>
              <?php else: ?>
                <form method="post" style="margin-top:10px" onsubmit="return confirm('Broadcast LOST HELP ME to nearby Ring Ring Paw owners (~25 km from your address)?');">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="lost_trigger" />
                  <input type="hidden" name="pet_id" value="<?= (int) $pet['id'] ?>" />
                  <button type="submit" class="btn btn-danger btn-block btn-lost">LOST HELP ME</button>
                </form>
              <?php endif; ?>
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
      <p class="empty">No alerts yet. You’ll get an email and an inbox note on scans and nearby LOST HELP ME.</p>
    <?php else: ?>
      <?php foreach ($notes as $n): ?>
        <div class="panel panel-soft" style="margin-top:10px;margin-bottom:0">
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
            <strong><?= rrp_h($n['title']) ?></strong>
            <?php if (empty($n['read_at'])): ?><span class="pill pill-unread">New</span><?php endif; ?>
          </div>
          <p class="meta" style="white-space:pre-wrap;margin:6px 0 0"><?= rrp_h($n['body']) ?></p>
          <?php if (!empty($n['lost_alert_id'])): ?>
            <p style="margin:8px 0 0"><a href="lost-alert.php?id=<?= (int) $n['lost_alert_id'] ?>">Open alert</a></p>
          <?php endif; ?>
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
