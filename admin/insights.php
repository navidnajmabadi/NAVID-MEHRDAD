<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';
require_once dirname(__DIR__) . '/lib/views/health_dashboard.php';

// Business + health insights (brief §4). Owners only as counts; small groups hidden as "<N".
$admin = rrp_require_admin();
$b = rrp_ins_business();
$countries = rrp_ins_countries();
$points = rrp_ins_map_points();
$species = rrp_ins_species();
$conds = array_slice(rrp_ins_conditions_by_city(), 0, 25);
$labs = rrp_ins_lab_rates();
$social = rrp_ins_social();
$min = rrp_ins_min_group();
$usd = static fn(int $c) => '$' . number_format($c / 100, 2);
$bars = static function (array $rows, string $k = 'k', string $n = 'n'): void {
    $mx = max(1, max(array_map(static fn($r) => (int) $r[$n], $rows ?: [['n' => 1]])));
    echo '<div class="dbars">';
    foreach ($rows as $r) {
        echo '<div class="dbar"><span>' . rrp_h((string) ($r[$k] ?: '—')) . '</span><i style="width:' . round(100 * (int) $r[$n] / $mx) . '%"></i><b class="data">' . (int) $r[$n] . '</b></div>';
    }
    echo '</div>';
};
rrp_admin_shell_start('Insights', 'insights');
?>
  <h1>Insights</h1>
  <p class="lede">Business, users and de-identified health data. No names or contact details — groups under <?= $min ?> pets show as "&lt;<?= $min ?>".</p>

  <div class="kpis">
    <div class="kpi"><b><?= rrp_h($usd($b['revenue_30_cents'])) ?></b><span>Revenue 30 days</span></div>
    <div class="kpi"><b><?= (int) $b['plus_now'] ?></b><span>Plus now</span></div>
    <div class="kpi"><b><?= (int) $b['owners_total'] ?></b><span>Owners</span></div>
    <div class="kpi"><b><?= (int) $b['owners_new_7'] ?> / <?= (int) $b['owners_new_30'] ?></b><span>New 7d / 30d</span></div>
    <div class="kpi"><b><?= (int) $b['dau'] ?> · <?= (int) $b['wau'] ?> · <?= (int) $b['mau'] ?></b><span>Active day · week · month</span></div>
    <div class="kpi"><b><?= number_format($b['tokens_30']) ?></b><span>AI tokens 30 days</span></div>
    <div class="kpi"><b>$<?= number_format($b['ai_cost_30_usd'], 2) ?></b><span>AI cost 30d (est. $<?= rrp_h((string) $b['token_price']) ?>/1M)</span></div>
    <div class="kpi"><b><?= (int) $b['pets_total'] ?></b><span>Pets</span></div>
  </div>

  <div class="dash-grid">
    <section class="dash-card">
      <h2>SIPO use · 30 days</h2>
      <?php $pts = array_map(static fn($d) => ['date' => (string) $d['day'], 'value' => (float) $d['messages']], $b['daily']); ?>
      <?php if (count($pts) >= 2): ?><p class="meta">Messages per day</p><?php rrp_view_line_chart($pts, null, null, 'messages'); ?>
      <?php $tk = array_map(static fn($d) => ['date' => (string) $d['day'], 'value' => (float) $d['tokens']], $b['daily']); ?><p class="meta">Tokens per day</p><?php rrp_view_line_chart($tk, null, null, 'tokens'); ?>
      <?php else: ?><p class="meta">Not enough days yet.</p><?php endif; ?>
    </section>
    <section class="dash-card">
      <h2>Revenue by month</h2>
      <table class="dash-table"><tr><th>Month</th><th>Revenue</th><th>Monthly</th><th>Yearly</th><th>Free months</th></tr>
        <?php foreach ($b['revenue_months'] as $r): ?><tr><td><?= rrp_h($r['month']) ?></td><td><?= rrp_h($usd((int) $r['cents'])) ?></td><td><?= (int) $r['monthly'] ?></td><td><?= (int) $r['yearly'] ?></td><td><?= (int) $r['free_months'] ?></td></tr><?php endforeach; ?>
        <?php if (!$b['revenue_months']): ?><tr><td colspan="5" class="meta">No payments yet (Square keys not set).</td></tr><?php endif; ?>
      </table>
      <p class="meta">Referrals: <?php foreach ($b['referrals'] as $r): ?><?= rrp_h($r['status']) ?> <?= (int) $r['n'] ?> · <?php endforeach; ?> coins given <?= number_format($b['coins_out']) ?></p>
    </section>
    <section class="dash-card">
      <h2>Where owners are</h2>
      <div id="insMap" class="dash-map" aria-label="Owners map (10 km cells)"></div>
      <div class="dbars" style="margin-top:10px"><?php $mx = max(1, max($countries ?: [1])); foreach (array_slice($countries, 0, 10, true) as $cc => $n): ?>
        <div class="dbar"><span><?= rrp_h($cc) ?></span><i style="width:<?= round(100 * $n / $mx) ?>%"></i><b class="data"><?= (int) $n ?></b></div><?php endforeach; ?></div>
    </section>
    <section class="dash-card">
      <h2>Pets</h2>
      <p class="meta">Species</p><?php $bars($species['type']); ?>
      <p class="meta" style="margin-top:10px">Sex</p><?php $bars($species['sex']); ?>
      <p class="meta" style="margin-top:10px">Top breeds</p><?php $bars($species['breeds']); ?>
    </section>
    <section class="dash-card">
      <h2>Health by city</h2>
      <table class="dash-table"><tr><th>City</th><th>Species</th><th>Condition</th><th>%</th></tr>
        <?php foreach ($conds as $c): ?><tr><td><?= rrp_h($c['city']) ?></td><td><?= rrp_h($c['species']) ?></td><td><?= rrp_h($c['condition']) ?></td>
          <td><?= $c['pct'] !== null ? rrp_h((string) $c['pct']) . '% <span class="meta">(' . (int) $c['pets'] . '/' . (int) $c['of'] . ')</span>' : '<span class="meta">&lt;' . $min . ' pets</span>' ?></td></tr><?php endforeach; ?>
        <?php if (!$conds): ?><tr><td colspan="4" class="meta">No conditions recorded yet.</td></tr><?php endif; ?>
      </table>
    </section>
    <section class="dash-card">
      <h2>Lab values out of range</h2>
      <table class="dash-table"><tr><th>Species</th><th>Test</th><th>Results</th><th>Out of range</th></tr>
        <?php foreach ($labs as $l): ?><tr><td><?= rrp_h($l['species']) ?></td><td><?= rrp_h($l['analyte']) ?></td><td><?= (int) $l['results'] ?></td><td><?= rrp_h((string) $l['pct']) ?>%</td></tr><?php endforeach; ?>
        <?php if (!$labs): ?><tr><td colspan="4" class="meta">No lab reports yet.</td></tr><?php endif; ?>
      </table>
    </section>
    <section class="dash-card">
      <h2>Boards (admin only)</h2>
      <p class="meta">Posts <?= (int) $social['posts_30'] ?> · comments <?= (int) $social['comments_30'] ?> · removed <?= (int) $social['removed_30'] ?> (30 days)</p>
      <p class="meta">Talked about most: <?= rrp_h(implode(', ', array_map(static fn($w, $n) => "{$w} ({$n})", array_keys($social['top_words']), $social['top_words']))) ?: '—' ?></p>
    </section>
    <section class="dash-card">
      <h2>Report factory</h2>
      <p class="meta">De-identified CSV for analysis and future reports.</p>
      <ul><?php foreach (rrp_ins_reports() as $k => $label): ?><li><a class="link" href="report.php?r=<?= rrp_h($k) ?>"><?= rrp_h($label) ?></a></li><?php endforeach; ?></ul>
    </section>
  </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function(){
  var pts = <?= json_encode(array_map(static fn($p) => [(float) $p['lat'], (float) $p['lng'], (int) $p['n']], $points)) ?>;
  var el = document.getElementById('insMap'); if (!el || !window.L) return;
  var m = L.map(el).setView(pts.length ? [pts[0][0], pts[0][1]] : [30, 0], pts.length ? 4 : 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 10, attribution: '&copy; OSM'}).addTo(m);
  var b = [];
  pts.forEach(function(p){ L.circleMarker([p[0], p[1]], {radius: Math.min(18, 5 + p[2] * 2), color: '#2E6AFF', fillOpacity: .5}).addTo(m).bindTooltip(p[2] + ' owner' + (p[2] === 1 ? '' : 's')); b.push([p[0], p[1]]); });
  if (b.length > 1) m.fitBounds(b, {padding: [20, 20], maxZoom: 8});
})();
</script>
<?php
rrp_admin_shell_end();
