<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();
$tab = (string) ($_GET['tab'] ?? 'scans');
$scans = rrp_admin_recent_scans(40);
$lost = rrp_admin_recent_lost(40);
$actions = rrp_admin_recent_actions(50);

rrp_admin_shell_start('Logs', 'logs');
?>
  <h1>Logs</h1>
  <p class="lede">Recent scans, lost alerts, and admin actions.</p>

  <div class="nav-links">
    <a href="?tab=scans"<?= $tab === 'scans' ? ' style="text-decoration:underline"' : '' ?>>Scans</a>
    <a href="?tab=lost"<?= $tab === 'lost' ? ' style="text-decoration:underline"' : '' ?>>Lost alerts</a>
    <a href="?tab=admin"<?= $tab === 'admin' ? ' style="text-decoration:underline"' : '' ?>>Admin actions</a>
  </div>

  <?php if ($tab === 'lost'): ?>
    <?php foreach ($lost as $la): ?>
      <div class="panel panel-soft" style="margin-bottom:8px">
        <strong><?= rrp_h($la['pet_name']) ?></strong>
        <span class="pill"><?= rrp_h($la['status']) ?></span>
        <div class="meta">
          Owner <?= rrp_h($la['owner_name']) ?> (<?= rrp_h($la['owner_email']) ?>)
          · pings <?= (int) $la['ping_count'] ?>
          · recipients <?= (int) $la['recipient_count'] ?>
          · <?= rrp_h($la['started_at']) ?> UTC
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$lost): ?><p class="empty">No lost alerts yet.</p><?php endif; ?>

  <?php elseif ($tab === 'admin'): ?>
    <?php foreach ($actions as $a): ?>
      <div class="panel panel-soft" style="margin-bottom:8px">
        <strong><?= rrp_h($a['action']) ?></strong>
        <div class="meta">
          <?= rrp_h((string) ($a['admin_name'] ?? 'system')) ?>
          <?php if ($a['target_type']): ?>
            · <?= rrp_h($a['target_type']) ?>#<?= (int) $a['target_id'] ?>
          <?php endif; ?>
          · <?= rrp_h($a['created_at']) ?> UTC
          <?php if ($a['detail']): ?><br /><?= rrp_h($a['detail']) ?><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$actions): ?><p class="empty">No admin actions logged yet.</p><?php endif; ?>

  <?php else: ?>
    <?php foreach ($scans as $s): ?>
      <div class="panel panel-soft" style="margin-bottom:8px">
        <strong><?= rrp_h($s['pet_name']) ?></strong>
        <span class="pill"><?= rrp_h($s['mode']) ?></span>
        <div class="meta">
          Owner <?= rrp_h($s['owner_name']) ?>
          · <?= rrp_h($s['scanned_at']) ?> UTC
          <?php if ($s['lat'] !== null): ?>
            · <?= rrp_h((string) $s['lat']) ?>, <?= rrp_h((string) $s['lng']) ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$scans): ?><p class="empty">No scans yet.</p><?php endif; ?>
  <?php endif; ?>
<?php rrp_admin_shell_end(); ?>
