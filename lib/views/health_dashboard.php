<?php
declare(strict_types=1);

/**
 * Health dashboard view (owner page + read-only vet page). Renders rrp_vm_health().
 * Charts are inline SVG: reference range as a band, points coloured by flag,
 * only abnormal points blink (brief §A2; reduced-motion turns it off).
 */

function rrp_view_health_dashboard(array $vm, bool $readOnly): void
{
    $s = $vm['status'];
    $pet = $vm['pet'];
    ?>
<section id="sec-overview" class="care-status is-<?= rrp_h($s['state']) ?>" aria-live="polite">
  <?php rrp_pet_avatar($pet); ?>
  <div>
    <h2><?= rrp_h($s['headline']) ?></h2>
    <?php if ($s['alerts']): ?>
      <ul class="care-alerts" role="list"><?php foreach ($s['alerts'] as $a): ?><li><?= rrp_h($a) ?></li><?php endforeach; ?></ul>
    <?php else: ?>
      <p class="sub">Routines on track and nothing out of range.</p>
    <?php endif; ?>
  </div>
</section>

<section class="care-card" id="sec-profile">
  <h3 class="label">Profile</h3>
  <dl class="care-kv">
    <?php foreach (['Kind' => $pet['kind'], 'Breed' => $pet['breed'], 'Sex' => $pet['sex'] ? ucfirst($pet['sex']) : null, 'Age' => $pet['age'],
        'Weight' => $pet['weight_kg'] ? rtrim(rtrim(number_format($pet['weight_kg'], 1), '0'), '.') . ' kg' : null, 'Conditions' => $pet['conditions']] as $k => $v): ?>
      <div><dt><?= rrp_h($k) ?></dt><dd><?= $v ? rrp_h((string) $v) : '<span class="meta">—</span>' ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <?php if (count($vm['weights']) >= 2): ?>
    <div class="care-chart-wrap"><span class="meta">Weight (kg)</span><?php rrp_view_line_chart($vm['weights'], null, null, 'kg'); ?></div>
  <?php endif; ?>
</section>

<?php if ($vm['treatments']): ?>
<section class="care-card" id="sec-treatment">
  <h3 class="label">Vet treatment</h3>
  <?php foreach ($vm['treatments'] as $t): ?>
    <article class="care-treat is-<?= rrp_h($t['status']) ?>">
      <header><b><?= rrp_h($t['condition_name']) ?></b>
        <span class="chip-s"><?= rrp_h($t['status'] === 'active' ? 'Active' : ucfirst($t['status'])) ?></span></header>
      <p class="meta">Since <?= rrp_h($t['started_on']) ?><?= $t['planned_end_on'] ? ' · until ' . rrp_h($t['planned_end_on']) : '' ?><?= $t['vet_name'] ? ' · ' . rrp_h($t['vet_name']) : '' ?></p>
      <?php if ($t['instructions']): ?><blockquote><?= nl2br(rrp_h($t['instructions'])) ?></blockquote><?php endif; ?>
      <?php foreach ($t['meds'] as $m): ?>
        <p class="care-med<?= (int) $m['active'] ? '' : ' is-off' ?>">💊 <?= rrp_h($m['name']) ?> · <span class="data"><?= rrp_h(substr((string) $m['schedule_time'], 0, 5)) ?></span>
          <?= $m['dose_note'] ? '· as prescribed: ' . rrp_h($m['dose_note']) : '' ?></p>
      <?php endforeach; ?>
      <?php if ($t['logs']): ?>
        <div class="care-trendrow" aria-label="Last 14 days">
          <?php foreach ($t['logs'] as $l): ?><span class="t-<?= rrp_h($l['trend']) ?>" title="<?= rrp_h($l['log_date'] . ': ' . $l['trend'] . ($l['note'] ? ' — ' . $l['note'] : '')) ?>"></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($t['rechecks'] as $rc): ?><p class="meta">Recheck <?= rrp_h(substr((string) $rc['appointment_at'], 0, 16)) ?> UTC<?= $rc['clinic_name'] ? ' · ' . rrp_h($rc['clinic_name']) : '' ?></p><?php endforeach; ?>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="care-card" id="sec-labs">
  <h3 class="label">Lab results</h3>
  <?php if (!$vm['labs']): ?>
    <p class="sub">No lab results yet.<?= $readOnly ? '' : ' Send a blood test photo or PDF to SIPO and the numbers land here.' ?></p>
  <?php else: ?>
    <div class="care-labs">
      <?php foreach ($vm['labs'] as $a): $l = $a['latest']; $abn = in_array($l['flag'], ['low', 'high'], true); ?>
        <div class="care-lab<?= $abn ? ' is-abnormal' : ($a['watch'] ? ' is-watch' : '') ?>">
          <div class="care-lab-head">
            <b><?= rrp_h($a['label']) ?></b>
            <span class="data"><?= rrp_h(rtrim(rtrim(number_format($l['value'], 2, '.', ''), '0'), '.')) ?> <?= rrp_h((string) $a['unit']) ?></span>
          </div>
          <p class="meta"><?= $abn ? '<span class="flag">' . rrp_h(strtoupper($l['flag'])) . '</span> · ' : '' ?>
            <?= ($l['ref_low'] !== null || $l['ref_high'] !== null) ? 'Normal ' . rrp_h($l['ref_low'] . '–' . $l['ref_high']) : 'No range on report' ?>
            · <?= rrp_h($l['date']) ?><?= count($a['points']) > 1 ? ' · ' . ['up' => '↑ rising', 'down' => '↓ falling', 'flat' => '→ steady'][$a['direction']] : '' ?><?= $a['watch'] ? ' · <b class="watch">near the edge of normal</b>' : '' ?></p>
          <?php if (count($a['points']) >= 2): rrp_view_line_chart($a['points'], $l['ref_low'], $l['ref_high'], (string) $a['unit']); endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="care-card" id="sec-food">
  <h3 class="label">Food today</h3>
  <?php $f = $vm['food']; $pct = $f['today']['percent']; ?>
  <?php if ($f['need']['kcal']): ?>
    <div class="care-meter" role="meter" aria-valuemin="0" aria-valuemax="<?= (int) $f['need']['kcal'] ?>" aria-valuenow="<?= (int) $f['today']['eaten_kcal'] ?>" aria-label="Calories today">
      <span style="width:<?= min(100, (int) $pct) ?>%"></span>
    </div>
    <p class="meta"><b class="data"><?= (int) $f['today']['eaten_kcal'] ?></b> of ~<?= (int) $f['need']['kcal'] ?> kcal · <?= (int) $f['today']['meals'] ?> logged<?= $f['today']['unknown_meals'] ? ' (' . (int) $f['today']['unknown_meals'] . ' without calories)' : '' ?></p>
    <p class="meta"><?= rrp_h($f['need']['note']) ?></p>
  <?php else: ?>
    <p class="sub"><?= rrp_h($f['need']['note']) ?></p>
  <?php endif; ?>
  <?php foreach ($f['foods'] as $food): ?>
    <div class="care-food">
      <b><?= rrp_h($food['name']) ?></b> <span class="meta"><?= rrp_h($food['food_kind']) ?><?= $food['kcal_per_kg'] ? ' · ' . (int) $food['kcal_per_kg'] . ' kcal/kg' : '' ?><?= $food['daily_grams'] ? ' · ~' . (int) $food['daily_grams'] . ' g/day' : '' ?></span>
      <?php if ($food['days_left'] !== null): ?>
        <span class="chip-s<?= $food['days_left'] <= RRP_FOOD_SUPPLY_WARN_DAYS ? ' is-warn' : '' ?>"><?= $food['days_left'] <= 0 ? 'Out' : (int) $food['days_left'] . ' days left' ?></span>
      <?php endif; ?>
      <?php foreach ($food['warnings'] as $w): ?><p class="care-warn">⚠ <?= rrp_h($w) ?></p><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($f['preferences']): ?>
    <p class="label" style="margin-top:14px">Likes · last 30 days</p>
    <?php foreach ($f['preferences'] as $p): $tot = max(1, $p['loved'] + $p['ok'] + $p['refused']); ?>
      <div class="care-pref"><span><?= rrp_h($p['name']) ?></span>
        <span class="care-pref-bar" aria-label="<?= (int) $p['loved'] ?> loved, <?= (int) $p['ok'] ?> ok, <?= (int) $p['refused'] ?> refused">
          <i class="p-loved" style="width:<?= 100 * $p['loved'] / $tot ?>%"></i><i class="p-ok" style="width:<?= 100 * $p['ok'] / $tot ?>%"></i><i class="p-refused" style="width:<?= 100 * $p['refused'] / $tot ?>%"></i>
        </span></div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="care-card" id="sec-routines">
  <h3 class="label">Care routines</h3>
  <p class="meta"><?= rrp_h($vm['region_label']) ?> · your vet sets the exact schedule.</p>
  <ul class="care-routines" role="list">
    <?php foreach ($vm['routines'] as $r): ?>
      <li class="is-<?= rrp_h($r['state']) ?>">
        <span class="dot" aria-hidden="true"></span>
        <div><b><?= rrp_h($r['title']) ?></b>
          <small><?= $r['last_done'] ? 'Last ' . rrp_h($r['last_done']) . ' · ' : '' ?><?= $r['next_due'] ? 'Next ' . rrp_h($r['next_due']) : ($r['state'] === 'unknown' ? 'Not known yet' : '') ?></small>
          <small class="meta"><?= rrp_h($r['when']) ?></small></div>
        <span class="chip-s"><?= rrp_h(['ok' => 'On track', 'due_soon' => 'Due soon', 'overdue' => 'Overdue', 'unknown' => 'Unknown'][$r['state']]) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <details class="care-sources"><summary class="meta">Sources</summary>
    <ul><?php $seen = []; foreach ($vm['routines'] as $r): foreach ($r['sources'] as [$label, $url]): if (isset($seen[$url])) continue; $seen[$url] = 1; ?>
      <li><a class="link" href="<?= rrp_h($url) ?>" target="_blank" rel="noopener"><?= rrp_h($label) ?></a></li>
    <?php endforeach; endforeach; ?></ul>
  </details>
</section>
<?php
}

/**
 * Small line chart. $points: [{date, value, flag?}], optional reference band.
 */
function rrp_view_line_chart(array $points, ?float $lo, ?float $hi, string $unit): void
{
    $w = 320;
    $h = 96;
    $pad = 10;
    $vals = array_column($points, 'value');
    $min = min(array_merge($vals, array_filter([$lo], 'is_float')));
    $max = max(array_merge($vals, array_filter([$hi], 'is_float')));
    if ($max - $min < 1e-9) {
        $max += 1;
        $min -= 1;
    }
    $span = $max - $min;
    $min -= $span * 0.12;
    $max += $span * 0.12;
    $n = count($points);
    $x = static fn(int $i) => $pad + ($n === 1 ? ($w - 2 * $pad) / 2 : $i * ($w - 2 * $pad) / ($n - 1));
    $y = static fn(float $v) => $h - $pad - ($v - $min) / ($max - $min) * ($h - 2 * $pad);
    $path = '';
    foreach ($points as $i => $p) {
        $path .= ($i ? ' L' : 'M') . round($x($i), 1) . ' ' . round($y((float) $p['value']), 1);
    }
    $label = implode(', ', array_map(static fn($p) => $p['date'] . ': ' . $p['value'] . ' ' . $unit, $points));
    ?>
<svg class="care-chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="<?= rrp_h($label) ?>" preserveAspectRatio="none">
  <?php if ($lo !== null && $hi !== null): ?>
    <rect class="band" x="0" y="<?= round($y($hi), 1) ?>" width="<?= $w ?>" height="<?= max(1, round($y($lo) - $y($hi), 1)) ?>" />
  <?php endif; ?>
  <path class="line" d="<?= rrp_h($path) ?>" />
  <?php foreach ($points as $i => $p): $abn = in_array($p['flag'] ?? '', ['low', 'high'], true); ?>
    <circle class="pt<?= $abn ? ' is-abnormal' : '' ?>" cx="<?= round($x($i), 1) ?>" cy="<?= round($y((float) $p['value']), 1) ?>" r="<?= $abn ? 4.5 : 3.5 ?>"><title><?= rrp_h($p['date'] . ': ' . $p['value'] . ' ' . $unit) ?></title></circle>
  <?php endforeach; ?>
</svg>
<?php
}

/**
 * KPI strip + section tabs for the owner's Health dashboard (not on the vet share view).
 * @param array{kpis:list<array{label:string,value:string,sub?:string,state?:string,href?:string}>, sections:array<string,string>} $top
 */
function rrp_view_health_top(array $top): void
{
    ?>
<nav class="hd-tabs" aria-label="Dashboard sections">
  <?php foreach ($top['sections'] as $id => $label): ?><a href="#<?= rrp_h($id) ?>"><?= rrp_h($label) ?></a><?php endforeach; ?>
</nav>
<div class="hd-kpis">
  <?php foreach ($top['kpis'] as $k): $tag = !empty($k['href']) ? 'a' : 'div'; ?>
    <<?= $tag ?> class="hd-kpi is-<?= rrp_h($k['state'] ?? 'neutral') ?>"<?= $tag === 'a' ? ' href="' . rrp_h($k['href']) . '"' : '' ?>>
      <span class="label"><?= rrp_h($k['label']) ?></span>
      <b class="data"><?= rrp_h($k['value']) ?></b>
      <?php if (!empty($k['sub'])): ?><small class="meta"><?= rrp_h($k['sub']) ?></small><?php endif; ?>
    </<?= $tag ?>>
  <?php endforeach; ?>
</div>
<?php
}

/** Simple bar chart. $bars: [{label, value}] */
function rrp_view_bar_chart(array $bars, string $unit, ?float $goal = null): void
{
    $max = max(1.0, (float) max(array_merge(array_column($bars, 'value'), [$goal ?? 0])));
    $label = implode(', ', array_map(static fn($b) => $b['label'] . ': ' . $b['value'] . ' ' . $unit, $bars));
    ?>
<div class="hd-bars" role="img" aria-label="<?= rrp_h($label) ?>">
  <?php if ($goal): ?><i class="hd-goal" style="bottom:<?= round(100 * $goal / $max, 1) ?>%" title="Goal <?= rrp_h((string) $goal) ?> <?= rrp_h($unit) ?>"></i><?php endif; ?>
  <?php foreach ($bars as $b): ?>
    <div class="hd-bar"><span style="height:<?= round(100 * (float) $b['value'] / $max, 1) ?>%" title="<?= rrp_h($b['label'] . ': ' . $b['value'] . ' ' . $unit) ?>"></span><small><?= rrp_h($b['label']) ?></small></div>
  <?php endforeach; ?>
</div>
<?php
}
