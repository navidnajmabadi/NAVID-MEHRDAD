<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();
$stats = rrp_admin_stats();

rrp_admin_shell_start('Overview', 'home');
?>
  <h1>Overview</h1>
  <p class="lede">Hi <?= rrp_h($admin['name']) ?> — live ops snapshot for SIPAW.</p>
  <?php rrp_render_flash(); ?>

  <div class="admin-stat-grid">
    <div class="panel panel-soft admin-stat">
      <div class="meta">Users joined</div>
      <strong><?= (int) $stats['users_total'] ?></strong>
      <div class="meta"><?= (int) $stats['users_active'] ?> active · <?= (int) $stats['users_suspended'] ?> suspended</div>
    </div>
    <div class="panel panel-soft admin-stat">
      <div class="meta">Pets</div>
      <strong><?= (int) $stats['pets_total'] ?></strong>
      <div class="meta">
        dog <?= (int) $stats['pets_by_type']['dog'] ?>
        · cat <?= (int) $stats['pets_by_type']['cat'] ?>
        · rabbit <?= (int) $stats['pets_by_type']['rabbit'] ?>
        · custom <?= (int) $stats['pets_by_type']['custom'] ?>
      </div>
    </div>
    <div class="panel panel-soft admin-stat">
      <div class="meta">Open LOST</div>
      <strong><?= (int) $stats['open_lost'] ?></strong>
      <div class="meta">Scans 24h: <?= (int) $stats['scans_24h'] ?></div>
    </div>
    <div class="panel panel-soft admin-stat">
      <div class="meta">Open tickets</div>
      <strong><?= (int) $stats['open_tickets'] ?></strong>
      <div class="meta">Billing: stub only</div>
    </div>
  </div>

  <section class="panel" aria-labelledby="billStub">
    <h2 id="billStub">Billing placeholders</h2>
    <p class="meta" style="margin:0">
      Seat price target: <strong>$5 / pet / month</strong> · yearly −30% · coupons table ready · wallet on owners.
      Payments provider TBD — no checkout in this release.
    </p>
  </section>
<?php rrp_admin_shell_end(); ?>
