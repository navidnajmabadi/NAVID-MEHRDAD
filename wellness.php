<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$plan = rrp_owner_plan($owner);
$pets = rrp_pets_by_owner((int) $owner['id']);
$petId = isset($_GET['pet']) ? (int) $_GET['pet'] : (int) ($pets[0]['id'] ?? 0);
$error = null;

if ($plan === 'plus' && $_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $scores = [];
    foreach (($_POST['sev'] ?? []) as $k => $v) {
        $scores[(string) $k] = (int) $v;
    }
    $result = rrp_wellness_save_day((int) $owner['id'], $petId, (string) ($_POST['date'] ?? date('Y-m-d')), $scores, (string) ($_POST['note'] ?? ''));
    if (!empty($result['ok'])) {
        rrp_flash_set('ok', 'Wellness check-in saved.');
        rrp_redirect('wellness.php?pet=' . $petId);
    }
    $error = $result['error'] ?? 'Could not save.';
}

$defs = rrp_wellness_symptom_defs();
$history = $plan === 'plus' ? rrp_wellness_history((int) $owner['id'], $petId, 14) : [];
$nav = rrp_owner_nav_context((int) $owner['id']);

rrp_layout_head('Wellness — SIPAW');
rrp_app_start(['nav' => 'health']);
?>
<div class="topbar">
  <a class="iconbtn" href="health-care.php<?= $petId ? '?pet=' . $petId : '' ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Wellness</h2></div>
  <span></span>
</div>
<div class="pad stack" style="gap:16px">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error"><?= rrp_h($error) ?></div><?php endif; ?>

  <?php if ($plan !== 'plus'): ?>
    <div class="box blue">
      <p class="label">Plus</p>
      <p class="sub" style="margin-top:8px">Daily allergy / wellness check-ins and trends are on Plus.</p>
      <a class="btn primary" href="plus.php" style="margin-top:12px">See Plus</a>
    </div>
  <?php elseif (!$pets): ?>
    <p class="sub">Add a pet first.</p>
  <?php else: ?>
    <form method="get"><select name="pet" onchange="this.form.submit()" style="min-height:44px;width:100%;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px">
      <?php foreach ($pets as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id']===$petId?'selected':'' ?>><?= rrp_h($p['name']) ?></option><?php endforeach; ?>
    </select></form>

    <form method="post" class="box stack" style="gap:12px">
      <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
      <p class="label">Today’s check-in</p>
      <input type="date" name="date" value="<?= rrp_h(date('Y-m-d')) ?>" style="min-height:44px;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px" />
      <?php foreach ($defs as $d): $key = $d['symptom_key']; ?>
        <label class="sub" style="font-size:13px;display:flex;justify-content:space-between;gap:12px;align-items:center">
          <span><?= rrp_h($d['label_en']) ?></span>
          <input type="number" name="sev[<?= rrp_h($key) ?>]" min="0" max="5" value="0" style="width:64px;min-height:40px;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 8px" />
        </label>
      <?php endforeach; ?>
      <div class="field"><div class="in area"><textarea name="note" rows="2" maxlength="500" placeholder="Optional note / triggers"></textarea></div></div>
      <button class="btn primary" type="submit">Save log</button>
      <p class="sub" style="font-size:12px">Education only — not a diagnosis.</p>
    </form>

    <div class="box">
      <p class="label">Recent scores</p>
      <?php if (!$history): ?>
        <p class="sub" style="margin-top:8px">No logs yet.</p>
      <?php else: ?>
        <ul class="sub" style="margin:10px 0 0;padding-left:18px;font-size:13px">
          <?php foreach (array_slice($history, -20) as $h): ?>
            <li><?= rrp_h($h['log_date']) ?> · <?= rrp_h($h['symptom_key'] ?? '') ?> = <?= (int) ($h['severity'] ?? 0) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="gap"></div>
</div>
<?php rrp_app_end(['nav' => 'health', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]); ?>
