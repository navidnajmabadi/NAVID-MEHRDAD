<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// Site health (brief §4): uptime, database, memory, storage, error log.
$admin = rrp_require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    if (($_POST['act'] ?? '') === 'resolve') {
        rrp_db()->prepare('UPDATE app_errors SET resolved = 1 WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    }
    rrp_redirect('site.php');
}
$s = rrp_ops_snapshot();
$csrf = rrp_csrf_token();
$hb = $s['last_heartbeat'];
$stale = !$hb || strtotime($hb['created_at'] . ' UTC') < time() - 40 * 60;
rrp_admin_shell_start('Site health', 'site');
?>
  <h1>Site health</h1>
  <p class="lede">Uptime comes from the cron jobs checking in every 10–15 minutes. A gap means the site or the cron was down.</p>
  <div class="kpis">
    <div class="kpi <?= $s['uptime_7d'] !== null && $s['uptime_7d'] < 99 ? 'is-bad' : '' ?>"><b><?= $s['uptime_7d'] !== null ? $s['uptime_7d'] . '%' : '–' ?></b><span>Uptime 7 days</span></div>
    <div class="kpi <?= $stale ? 'is-bad' : '' ?>"><b><?= $hb ? rrp_h(substr((string) $hb['created_at'], 11, 5)) . ' UTC' : 'never' ?></b><span>Last cron check-in</span></div>
    <div class="kpi"><b><?= $s['db_ms_avg'] !== null ? (int) $s['db_ms_avg'] . ' ms' : '–' ?></b><span>Database response (24h)</span></div>
    <div class="kpi"><b><?= $hb ? round($hb['mem_kb'] / 1024, 1) . ' MB' : '–' ?></b><span>Memory per run (limit <?= rrp_h((string) $s['memory_limit']) ?>)</span></div>
    <div class="kpi"><b><?= $s['load'] ? rrp_h(implode(' ', array_map(static fn($x) => number_format($x, 2), $s['load']))) : '–' ?></b><span>Server load 1/5/15 min</span></div>
    <div class="kpi"><b><?= rrp_h((string) $s['db_mb']) ?> MB</b><span>Database size</span></div>
    <div class="kpi"><b><?= rrp_h((string) $s['uploads_mb']) ?> MB</b><span>Uploads (photos)</span></div>
    <div class="kpi"><b><?= $s['disk_free_gb'] !== null ? rrp_h((string) $s['disk_free_gb']) . ' GB' : '–' ?></b><span>Disk free</span></div>
  </div>
  <?php if ($stale): ?><div class="flash flash-error">No cron check-in for 40+ minutes. Check Hostinger → Cron Jobs: <code>cron/sipo-nudges.php</code> and <code>cron/health-reminders.php</code>.</div><?php endif; ?>

  <div class="dash-grid">
    <section class="dash-card">
      <h2>Downtime (gaps over 40 min, 7 days)</h2>
      <table class="dash-table"><tr><th>After</th><th>Gap</th></tr>
        <?php foreach ($s['gaps'] as $g): ?><tr><td><?= rrp_h((string) $g['after']) ?> UTC</td><td class="pill-bad"><?= (int) $g['mins'] ?> min</td></tr><?php endforeach; ?>
        <?php if (!$s['gaps']): ?><tr><td colspan="2" class="meta">No gaps 🎉</td></tr><?php endif; ?></table>
    </section>
    <section class="dash-card">
      <h2>Biggest tables</h2>
      <table class="dash-table"><tr><th>Table</th><th>MB</th><th>Rows</th></tr>
        <?php foreach ($s['big_tables'] as $t): ?><tr><td><?= rrp_h($t['t']) ?></td><td><?= rrp_h((string) $t['mb']) ?></td><td><?= number_format((int) $t['r']) ?></td></tr><?php endforeach; ?></table>
      <p class="meta">PHP <?= rrp_h($s['php']) ?></p>
    </section>
  </div>

  <section class="dash-card" style="margin-top:14px">
    <h2>App errors (uncaught)</h2>
    <table class="dash-table"><tr><th>Last seen</th><th>Hits</th><th>Error</th><th>Where</th><th></th></tr>
      <?php foreach ($s['errors'] as $e): ?><tr><td><?= rrp_h(substr((string) $e['last_seen'], 0, 16)) ?></td><td class="data"><?= (int) $e['hits'] ?></td>
        <td class="mono"><?= rrp_h((string) $e['message']) ?></td><td class="meta"><?= rrp_h((string) $e['file']) ?>:<?= (int) $e['line'] ?><br /><?= rrp_h((string) $e['url']) ?></td>
        <td><form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="resolve" /><input type="hidden" name="id" value="<?= (int) $e['id'] ?>" /><button class="link" type="submit">Fixed</button></form></td></tr><?php endforeach; ?>
      <?php if (!$s['errors']): ?><tr><td colspan="5" class="meta">No uncaught errors.</td></tr><?php endif; ?>
    </table>
  </section>
  <section class="dash-card" style="margin-top:14px">
    <h2>PHP error log (last lines)</h2>
    <div class="mono"><?= $s['php_log'] ? rrp_h(implode("\n", $s['php_log'])) : '<span class="meta">Not readable from here, or empty.</span>' ?></div>
  </section>
<?php
rrp_admin_shell_end();
