<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';
require_once dirname(__DIR__) . '/lib/views/health_dashboard.php';

$admin = rrp_require_admin();
$stats = rrp_admin_stats();

$brainAvg = rrp_db()->query("SELECT AVG(score) s, AVG(prompt_tokens + completion_tokens) t, COUNT(*) n FROM si_request_log WHERE source = 'live' AND created_at > (UTC_TIMESTAMP() - INTERVAL 7 DAY)")->fetch();
$labSum = rrp_lab_summary();
$labRate = $labSum['ran'] ? (int) round(100 * $labSum['pass'] / $labSum['ran']) : null;
$tpm = $brainAvg && $brainAvg['n'] ? (int) round((float) $brainAvg['t']) : null;

rrp_admin_shell_start('Overview', 'home');
?>
  <h1>Overview</h1>
  <p class="lede">Hi <?= rrp_h($admin['name']) ?>. A live snapshot of SIPAW: people, pets, safety, and how well SIPO is doing.</p>
  <?php rrp_render_flash(); ?>

  <div class="ad-kpis">
    <div class="ad-kpi"><b><?= (int) $stats['users_total'] ?></b><span>Owners (<?= (int) $stats['users_active'] ?> active, <?= (int) $stats['users_suspended'] ?> suspended)</span></div>
    <div class="ad-kpi"><b><?= (int) $stats['pets_total'] ?></b><span>Pets: <?= (int) $stats['pets_by_type']['dog'] ?> dogs, <?= (int) $stats['pets_by_type']['cat'] ?> cats, <?= (int) $stats['pets_by_type']['rabbit'] + (int) $stats['pets_by_type']['custom'] ?> other</span></div>
    <div class="ad-kpi <?= $stats['open_lost'] ? 'is-bad' : '' ?>"><b><?= (int) $stats['open_lost'] ?></b><span>Open lost-pet alerts · scans 24h: <?= (int) $stats['scans_24h'] ?></span></div>
    <div class="ad-kpi <?= $stats['open_tickets'] ? 'is-warn' : '' ?>"><b><?= (int) $stats['open_tickets'] ?></b><span>Open tickets</span></div>
  </div>

  <div class="ad-card">
    <h2>SIPO health, last 7 days</h2>
    <div class="ad-kpis" style="margin-bottom:8px">
      <div class="ad-kpi <?= $brainAvg['s'] === null ? '' : ((float) $brainAvg['s'] >= 4 ? 'is-good' : ((float) $brainAvg['s'] >= 3 ? 'is-warn' : 'is-bad')) ?>"><b><?= $brainAvg['s'] !== null ? number_format((float) $brainAvg['s'], 1) : '–' ?></b><span>Reply score out of 5 (<?= (int) $brainAvg['n'] ?> replies)</span></div>
      <div class="ad-kpi <?= $tpm === null ? '' : ($tpm > 12000 ? 'is-bad' : ($tpm > 7000 ? 'is-warn' : 'is-good')) ?>"><b><?= $tpm !== null ? number_format($tpm) : '–' ?></b><span>Tokens per message (aim under 7,000)</span></div>
      <div class="ad-kpi <?= $labRate === null ? '' : ($labRate >= 90 ? 'is-good' : ($labRate >= 70 ? 'is-warn' : 'is-bad')) ?>"><b><?= $labRate === null ? '–' : $labRate . '%' ?></b><span>Tests passing (<?= $labSum['ran'] ?> run of <?= $labSum['total'] ?>)</span></div>
    </div>
    <a class="ad-btn" href="graph.php">Open the brain graph</a> <a class="ad-btn" href="lab.php">Open the test lab</a> <a class="ad-btn" href="naki.php">NAKI brain</a>
  </div>

  <?php $g = rrp_ins_growth(14); ?>
  <section class="ad-card">
    <h2>Last 14 days</h2>
    <p class="meta">New owners per day</p>
    <?php rrp_view_bar_chart(array_map(static fn($d) => ['label' => substr($d['date'], 8), 'value' => $d['value']], $g['signups']), 'owners'); ?>
    <p class="meta" style="margin-top:10px">Pets added per day</p>
    <?php rrp_view_bar_chart(array_map(static fn($d) => ['label' => substr($d['date'], 8), 'value' => $d['value']], $g['pets']), 'pets'); ?>
    <p class="meta" style="margin-top:10px"><a class="ad-btn is-sm" href="insights.php">More charts in Insights</a></p>
  </section>
<?php rrp_admin_shell_end(); ?>
