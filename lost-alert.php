<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$alertId = (int) ($_GET['id'] ?? 0);
$alert = $alertId > 0 ? rrp_lost_by_id($alertId) : null;

$allowed = false;
if ($alert) {
    if ((int) $alert['owner_id'] === (int) $owner['id']) {
        $allowed = true;
    } else {
        $chk = rrp_db()->prepare(
            'SELECT 1 FROM lost_alert_recipients WHERE lost_alert_id = ? AND recipient_owner_id = ? LIMIT 1'
        );
        $chk->execute([$alertId, (int) $owner['id']]);
        $allowed = (bool) $chk->fetchColumn();
    }
}

rrp_layout_head($alert ? ('Help find ' . $alert['pet_name'] . ' — Ring Ring Paw') : 'Alert — Ring Ring Paw');
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="dashboard.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="dashboard.php">Dashboard</a>
  </div>

  <?php if (!$alert || !$allowed): ?>
    <h1>Alert not available</h1>
    <p class="lede">This LOST alert isn’t shared with your account, or it doesn’t exist.</p>
  <?php else: ?>
    <div class="scan-hero">
      <?php if (!empty($alert['image_path'])): ?>
        <img class="scan-photo" src="<?= rrp_h($alert['image_path']) ?>" alt="<?= rrp_h($alert['pet_name']) ?>" />
      <?php endif; ?>
      <p class="pill pill-lost" style="margin:0 auto 10px">LOST HELP ME</p>
      <h1>Help find <?= rrp_h($alert['pet_name']) ?></h1>
      <p class="lede">
        <?= rrp_h($alert['pet_type']) ?>
        · Alert <?= rrp_h($alert['status']) ?>
        · ~<?= rrp_h((string) $alert['radius_km']) ?> km radius
      </p>
    </div>

    <div class="panel">
      <h2>Distinctive marks</h2>
      <p style="margin:0;white-space:pre-wrap"><?= rrp_h($alert['identifying_marks'] ?: '—') ?></p>
    </div>

    <div class="panel">
      <h2>Magic word</h2>
      <p style="margin:0;font-family:var(--font-display);font-size:1.25rem"><?= rrp_h($alert['magic_word'] ?: '—') ?></p>
      <p class="field-hint">Use gently if you think you’ve found them — it helps confirm it’s the right pet.</p>
    </div>

    <?php if (!empty($alert['medical_notes'])): ?>
    <div class="panel">
      <h2>Medical (helpers)</h2>
      <p style="margin:0;white-space:pre-wrap"><?= rrp_h($alert['medical_notes']) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($alert['status'] === 'found'): ?>
      <div class="flash flash-ok">Yoho rescued — this pet was marked Found. Thank you!</div>
    <?php elseif ($alert['status'] === 'open' && (int) $alert['owner_id'] === (int) $owner['id']): ?>
      <form method="post" action="dashboard.php" class="stack-actions">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lost_found" />
        <input type="hidden" name="alert_id" value="<?= (int) $alert['id'] ?>" />
        <button type="submit" class="btn btn-primary btn-block">Mark Found — Yoho rescued</button>
      </form>
    <?php else: ?>
      <p class="meta">Keep an eye out nearby. You’ll get email + inbox reminders at 1h, 3h, and 8h if still open.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php rrp_layout_foot(); ?>
