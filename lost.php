<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        if ($action === 'lost_trigger') {
            $result = rrp_trigger_lost((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
            if (!empty($result['ok'])) {
                $n = (int) ($result['recipients'] ?? 0);
                rrp_flash_set(
                    'ok',
                    $n > 0
                        ? "Lost mode sent to {$n} nearby owner" . ($n === 1 ? '' : 's') . '.'
                        : 'Lost mode started. No nearby owners within 25 km yet.'
                );
            } else {
                rrp_flash_set('error', $result['error'] ?? 'Could not start alert.');
            }
            rrp_redirect('lost.php?pet=' . (int) ($_POST['pet_id'] ?? 0));
        }
        if ($action === 'lost_found') {
            $result = rrp_mark_lost_found((int) $owner['id'], (int) ($_POST['alert_id'] ?? 0));
            if (!empty($result['ok'])) {
                $alert = rrp_lost_by_id((int) ($_POST['alert_id'] ?? 0));
                rrp_redirect('rescued.php?pet=' . rawurlencode($alert['pet_name'] ?? 'your pet'));
            }
            rrp_flash_set('error', $result['error'] ?? 'Could not mark found.');
            rrp_redirect('lost.php');
        }
    }
}

$pets = rrp_pets_by_owner((int) $owner['id']);
$myLost = rrp_lost_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

$openLostByPet = [];
foreach ($myLost as $la) {
    if ($la['status'] === 'open') {
        $openLostByPet[(int) $la['pet_id']] = $la;
    }
}

$petId = isset($_GET['pet']) ? (int) $_GET['pet'] : 0;
$pet = null;
foreach ($pets as $p) {
    if ($petId === (int) $p['id'] || (!$petId && !$pet)) {
        $pet = $p;
        $petId = (int) $p['id'];
    }
}
$open = $pet ? ($openLostByPet[$petId] ?? null) : null;
$recipientCount = 0;
if ($open) {
    $cnt = rrp_db()->prepare('SELECT COUNT(*) FROM lost_alert_recipients WHERE lost_alert_id = ?');
    $cnt->execute([(int) $open['id']]);
    $recipientCount = (int) $cnt->fetchColumn();
}

$pingOffsets = defined('RRP_LOST_PING_OFFSETS') ? RRP_LOST_PING_OFFSETS : [0, 3600, 10800, 28800];
$pingCount = $open ? (int) ($open['ping_count'] ?? 0) : 0;

rrp_layout_head('Lost mode — SIPAW');
rrp_app_start(['nav' => 'lost']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center">
    <h2>Lost mode</h2>
    <?php if ($open): ?><span class="status red data">Active</span><?php else: ?><span class="status idle data">Off</span><?php endif; ?>
  </div>
  <span style="width:40px"></span>
</div>

<?php rrp_render_flash(); ?>

<?php if (!$pets): ?>
  <div class="pad stack"><div class="box"><p class="sub" style="margin:0">Add a pet before using Lost mode.</p><a class="btn primary" style="margin-top:12px" href="pet-register.php">Add a pet with SIPO</a></div></div>
<?php else: ?>
  <div class="pets" role="group" aria-label="Choose pet" style="padding-top:8px">
    <?php foreach ($pets as $i => $p): ?>
      <a class="petchip" href="lost.php?pet=<?= (int) $p['id'] ?>" aria-pressed="<?= (int) $p['id'] === $petId ? 'true' : 'false' ?>">
        <span class="ph">
          <?php if (!empty($p['image_path'])): ?><img src="<?= rrp_h($p['image_path']) ?>" alt="" /><?php else: ?>
          <svg viewBox="0 0 200 200"><use href="#<?= ($p['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg><?php endif; ?>
        </span>
        <?= rrp_h($p['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="mapbox" aria-hidden="true">
    <svg viewBox="0 0 400 230" preserveAspectRatio="xMidYMid slice"><use href="#map"/></svg>
    <div class="pulse"></div><div class="pulse d2"></div>
    <div class="maplabel data">~25 km · registered addresses</div>
  </div>

  <div class="pad stack" style="margin-top:16px;gap:16px">
    <?php if ($open): ?>
      <div class="counter">
        <div><b><?= $recipientCount ?></b><span>Owners</span></div>
        <div><b><?= $pingCount ?>/<?= count($pingOffsets) ?></b><span>Pings</span></div>
        <div><b>25</b><span>Km ring</span></div>
      </div>

      <div class="section">
        <span class="label red">Escalation</span>
        <div class="timeline">
          <?php
          $labels = [
              ['t0', 'Alert sent to nearby owners'],
              ['+1h', 'Reminder ping'],
              ['+3h', 'Second reminder'],
              ['+8h', 'Final ping'],
          ];
          foreach ($labels as $i => $row):
              $cls = 'tl';
              if ($i < $pingCount) {
                  $cls .= ' done';
              } elseif ($i === $pingCount) {
                  $cls .= ' next';
              }
          ?>
            <div class="<?= $cls ?>">
              <span class="t"><?= rrp_h($row[0]) ?></span>
              <span class="rail"><i></i><span></span></span>
              <div class="body"><b><?= rrp_h($row[1]) ?></b><small>Owners within ~25 km of your address</small></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lost_found" />
        <input type="hidden" name="alert_id" value="<?= (int) $open['id'] ?>" />
        <button type="submit" class="btn solid">She / he is home</button>
      </form>
      <a class="btn outline" href="lost-alert.php?id=<?= (int) $open['id'] ?>">Alert details</a>
      <p class="sub" style="font-size:12px">Next ping: <?= rrp_h((string) ($open['next_ping_at'] ?? 'done')) ?> UTC</p>
    <?php else: ?>
      <div class="box">
        <p style="margin:0 0 8px;font-weight:500">If <?= rrp_h($pet['name'] ?? 'your pet') ?> goes missing</p>
        <p class="sub" style="margin:0;font-size:13px">
          Alert nearby registered owners (~25 km) with photo, distinctive marks, magic word, and medical that matters.
        </p>
      </div>
      <form method="post" onsubmit="return confirm('Turn on Lost mode for <?= rrp_h($pet['name'] ?? 'this pet') ?>? Nearby owners within ~25 km will be alerted.');">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lost_trigger" />
        <input type="hidden" name="pet_id" value="<?= (int) $petId ?>" />
        <button type="submit" class="btn lost">Turn on Lost mode</button>
      </form>
    <?php endif; ?>

    <?php if ($pet): ?>
      <div class="section">
        <span class="label">Lost poster</span>
        <p class="sub" style="margin:8px 0 12px;font-size:13px">Download a flyer with photo, marks, and magic word.</p>
      </div>
      <div class="poster" id="lostPoster">
        <div class="top">
          <span class="lab">Lost pet</span>
          <img src="<?= rrp_h(rrp_asset('assets/img/logos/sipaw-wordmark-transparent-black.svg')) ?>" alt="siPaw" width="56" height="33" />
        </div>
        <div class="hd"><?= rrp_h($pet['name']) ?></div>
        <div class="pic">
          <?php if (!empty($pet['image_path'])): ?>
            <img src="<?= rrp_h($pet['image_path']) ?>" alt="" crossorigin="anonymous" style="width:100%;height:100%;object-fit:cover" />
          <?php else: ?>
            <svg viewBox="0 0 200 200"><use href="#<?= ($pet['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
          <?php endif; ?>
        </div>
        <p><?= rrp_h(rrp_pet_type_label($pet)) ?><?= $pet['breed'] ? ' · ' . rrp_h($pet['breed']) : '' ?><?= $pet['color'] ? ' · ' . rrp_h($pet['color']) : '' ?></p>
        <?php if (!empty($pet['identifying_marks'])): ?>
          <p><strong>Look for:</strong> <?= rrp_h($pet['identifying_marks']) ?></p>
        <?php endif; ?>
        <?php if (!empty($pet['magic_word'])): ?>
          <p><strong>Magic word:</strong> <?= rrp_h($pet['magic_word']) ?></p>
        <?php endif; ?>
        <?php if (!empty($owner['phone'])): ?>
          <p><strong>Call:</strong> <?= rrp_h((string) $owner['phone']) ?></p>
        <?php endif; ?>
        <div class="bar">
          <span>Scan the collar tag or call via siPaw · <?= rrp_h(rrp_app_url('pet.php?id=' . rawurlencode((string) $pet['public_id']))) ?></span>
        </div>
      </div>
      <button type="button" class="btn primary" id="posterPng">Download PNG</button>
      <button type="button" class="btn outline" id="posterPdf">Download PDF</button>
      <script>
      window.RRP_POSTER = { petName: <?= json_encode($pet['name'], JSON_UNESCAPED_UNICODE) ?> };
      </script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous"></script>
      <script src="<?= rrp_h(rrp_asset('assets/js/poster.js', true)) ?>" defer></script>
    <?php endif; ?>
    <div class="gap"></div>
  </div>
<?php endif; ?>
<?php
rrp_app_end(['nav' => 'lost', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
