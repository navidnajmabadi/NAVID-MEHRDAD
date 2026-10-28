<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$plan = rrp_owner_plan($owner);
$pets = rrp_pets_by_owner((int) $owner['id']);
$petId = rrp_page_pet_id($owner, $pets);
$error = null;

if ($plan === 'plus' && $_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'approve') {
        $result = rrp_care_reminder_set_status((int) $owner['id'], (int) ($_POST['reminder_id'] ?? 0), 'scheduled');
    } elseif ($action === 'dismiss') {
        $result = rrp_care_reminder_set_status((int) $owner['id'], (int) ($_POST['reminder_id'] ?? 0), 'dismissed');
    } elseif ($action === 'done') {
        $result = rrp_care_reminder_set_status((int) $owner['id'], (int) ($_POST['reminder_id'] ?? 0), 'completed');
    } elseif ($action === 'apply_pack') {
        $result = rrp_apply_routine_pack((int) $owner['id'], $petId, (string) ($_POST['pack_key'] ?? ''));
    } elseif ($action === 'create') {
        $result = rrp_care_reminder_create($petId, [
            'title' => $_POST['title'] ?? '',
            'due_at' => $_POST['due_at'] ?? '',
            'status' => 'pending_approval',
        ]);
    } else {
        $result = ['ok' => false, 'error' => 'Unknown action'];
    }
    if (!empty($result['ok'])) {
        rrp_flash_set('ok', isset($result['created']) ? 'Added ' . (int) $result['created'] . ' reminders.' : 'Updated.');
        rrp_redirect('reminders.php?pet=' . $petId);
    }
    $error = $result['error'] ?? 'Failed.';
}

$reminders = ($plan === 'plus' && $petId) ? rrp_care_reminders_for_pet($petId) : [];
$templates = $plan === 'plus' ? rrp_routine_templates() : [];
$packs = [];
foreach ($templates as $t) {
    $packs[$t['pack_key']] = true;
}
$nav = rrp_owner_nav_context((int) $owner['id']);

rrp_layout_head('Reminders — SIPAW');
rrp_app_start(['nav' => 'routines']);
?>
<div class="topbar">
  <a class="iconbtn" href="health-care.php<?= $petId ? '?pet=' . $petId : '' ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Reminders</h2></div>
  <?php if ($plan === 'plus' && $petId): ?>
    <a class="iconbtn" href="api/reminders/index.php?pet_id=<?= $petId ?>&format=ics" aria-label="Download ICS">ICS</a>
  <?php else: ?><span></span><?php endif; ?>
</div>
<div class="pad stack" style="gap:16px">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error"><?= rrp_h($error) ?></div><?php endif; ?>

  <?php if ($plan !== 'plus'): ?>
    <div class="box blue"><p class="label">Plus</p><p class="sub" style="margin-top:8px">Approve care packs and export .ics on Plus.</p><a class="btn primary" href="plus.php" style="margin-top:12px">See Plus</a></div>
  <?php elseif (!$pets): ?>
    <p class="sub">Add a pet first.</p>
  <?php else: ?>
    <form method="get"><select name="pet" onchange="this.form.submit()" style="min-height:44px;width:100%;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px">
      <?php foreach ($pets as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id']===$petId?'selected':'' ?>><?= rrp_h($p['name']) ?></option><?php endforeach; ?>
    </select></form>

    <div class="box stack" style="gap:10px">
      <p class="label">Apply pack</p>
      <?php foreach (array_keys($packs) as $pack): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
          <input type="hidden" name="action" value="apply_pack" />
          <input type="hidden" name="pack_key" value="<?= rrp_h($pack) ?>" />
          <button class="btn outline" type="submit"><?= rrp_h($pack) ?></button>
        </form>
      <?php endforeach; ?>
      <?php if (!$packs): ?><p class="sub">No templates — import sql/007_t03_t15_deltas.sql</p><?php endif; ?>
    </div>

    <form method="post" class="box stack" style="gap:10px">
      <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
      <input type="hidden" name="action" value="create" />
      <p class="label">Custom reminder</p>
      <div class="field"><div class="in"><input name="title" required placeholder="Title" /></div></div>
      <div class="field"><div class="in"><input name="due_at" type="date" required /></div></div>
      <button class="btn primary" type="submit">Add (needs approve)</button>
    </form>

    <div class="stack" style="gap:10px">
      <?php foreach ($reminders as $r): ?>
        <div class="box">
          <p class="label"><?= rrp_h($r['status']) ?></p>
          <p style="margin-top:8px"><?= rrp_h($r['title']) ?></p>
          <p class="data sub" style="margin-top:6px"><?= rrp_h($r['due_at']) ?></p>
          <div class="row" style="gap:8px;margin-top:10px;flex-wrap:wrap">
            <?php if ($r['status'] === 'pending_approval'): ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="action" value="approve" /><input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>" /><button class="btn primary" type="submit">Approve</button></form>
              <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="action" value="dismiss" /><input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>" /><button class="btn outline" type="submit">Dismiss</button></form>
            <?php else: ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="action" value="done" /><input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>" /><button class="btn outline" type="submit">Done</button></form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$reminders): ?><p class="sub">No open reminders.</p><?php endif; ?>
    </div>
    <p class="sub" style="font-size:12px">.ics only — no Google Calendar OAuth (v1 lock).</p>
  <?php endif; ?>
  <div class="gap"></div>
</div>
<?php rrp_app_end(['nav' => 'routines', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]); ?>
