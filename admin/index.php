<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';
require_once dirname(__DIR__) . '/lib/views/health_dashboard.php';

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

  <?php $g = rrp_ins_growth(14); ?>
  <section class="panel">
    <h2>Last 14 days</h2>
    <p class="meta">New owners per day</p>
    <?php rrp_view_bar_chart(array_map(static fn($d) => ['label' => substr($d['date'], 8), 'value' => $d['value']], $g['signups']), 'owners'); ?>
    <p class="meta" style="margin-top:10px">Pets added per day</p>
    <?php rrp_view_bar_chart(array_map(static fn($d) => ['label' => substr($d['date'], 8), 'value' => $d['value']], $g['pets']), 'pets'); ?>
    <p class="meta" style="margin-top:10px"><a class="link" href="insights.php">More charts in Insights</a></p>
  </section>
<?php rrp_admin_shell_end(); ?>
