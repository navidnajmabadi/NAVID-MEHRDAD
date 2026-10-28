<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/finance.php';
require_once __DIR__ . '/lib/views/health_dashboard.php';

// Finance: what the owner spends on their pets. SIPO logs the same entries from chat.
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
$filter = (int) ($_GET['pet'] ?? 0);
$petFilter = null;
foreach ($pets as $p) {
    if ((int) $p['id'] === $filter) {
        $petFilter = $p;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    if (($_POST['act'] ?? '') === 'delete') {
        rrp_expense_delete((int) $owner['id'], (int) ($_POST['id'] ?? 0));
        rrp_flash_set('ok', 'Removed.');
    } else {
        $pet = rrp_pet_by_id((int) ($_POST['pet_id'] ?? 0), (int) $owner['id']);
        $r = $pet ? rrp_expense_add($owner, $pet, (string) ($_POST['category'] ?? 'other'), (float) ($_POST['amount'] ?? 0),
            (string) ($_POST['currency'] ?? ''), (string) ($_POST['spent_on'] ?? ''), trim((string) ($_POST['note'] ?? '')) ?: null)
            : ['ok' => false, 'error' => 'Choose a pet.'];
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Saved 💸' : ($r['error'] ?? 'Could not save.'));
    }
    rrp_redirect('finance.php' . ($petFilter ? '?pet=' . (int) $petFilter['id'] : ''));
}

$sum = $pets ? rrp_expense_summary($owner, $petFilter, 12) : null;
$money = static fn(float $v, string $c) => ($c === 'USD' ? '$' : ($c === 'EUR' ? '€' : $c . ' ')) . number_format($v, $v >= 100 ? 0 : 2);
$csrf = rrp_csrf_token();
$today = rrp_sipo_local_now($owner)->format('Y-m-d');

rrp_layout_head('Finance — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'settings']);
?>
<div class="topbar">
  <span></span>
  <div class="center"><h2>Finance</h2></div>
  <?php rrp_coin_badge($owner); ?>
</div>
<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>
  <?php if (!$pets): ?>
    <section class="care-card"><p class="sub">Add a pet first, then track food, vet and other costs here.</p><a class="btn primary" href="si-chat.php?new=1" style="margin-top:10px">Add my pet with SIPO</a></section>
  <?php else: ?>
    <?php if (count($pets) > 1): ?>
      <nav class="hd-tabs" aria-label="Pet">
        <a href="finance.php" <?= $petFilter ? '' : 'aria-current="page"' ?>>All pets</a>
        <?php foreach ($pets as $p): ?><a href="finance.php?pet=<?= (int) $p['id'] ?>" <?= $petFilter && (int) $petFilter['id'] === (int) $p['id'] ? 'aria-current="page"' : '' ?>><?= rrp_h($p['name']) ?></a><?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <div class="hd-kpis">
      <div class="hd-kpi is-ok"><span class="label">This month<?= $petFilter ? ' · ' . rrp_h($petFilter['name']) : ' · all pets' ?></span><b class="data"><?= rrp_h($money($sum['this_month'], $sum['currency'])) ?></b></div>
      <div class="hd-kpi"><span class="label">Last month</span><b class="data"><?= rrp_h($money($sum['last_month'], $sum['currency'])) ?></b></div>
      <div class="hd-kpi"><span class="label">This year</span><b class="data"><?= rrp_h($money($sum['year'], $sum['currency'])) ?></b></div>
      <div class="hd-kpi"><span class="label">Monthly average</span><b class="data"><?php $act = array_filter(array_column($sum['months'], 'total')); ?><?= rrp_h($money($act ? array_sum($act) / count($act) : 0, $sum['currency'])) ?></b></div>
    </div>

    <section class="care-card">
      <h3 class="label">Last 12 months</h3>
      <?php rrp_view_bar_chart(array_map(static fn($m) => ['label' => substr($m['label'], 0, 1), 'value' => round($m['total'])], $sum['months']), $sum['currency']); ?>
    </section>

    <?php if ($sum['by_category']): $mx = max($sum['by_category']); ?>
    <section class="care-card">
      <h3 class="label">By category</h3>
      <div class="fin-cats">
        <?php foreach ($sum['by_category'] as $c => $v): ?>
          <div class="fin-cat"><span><?= rrp_h(RRP_EXPENSE_CATEGORIES[$c] ?? $c) ?></span><i style="width:<?= round(100 * $v / $mx) ?>%"></i><b class="data"><?= rrp_h($money($v, $sum['currency'])) ?></b></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <form method="post" class="care-card fin-form">
      <h3 class="label">Add a cost</h3>
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" />
      <div class="fin-grid">
        <label>Pet<select name="pet_id"><?php foreach ($pets as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $petFilter && (int) $petFilter['id'] === (int) $p['id'] ? 'selected' : '' ?>><?= rrp_h($p['name']) ?></option><?php endforeach; ?></select></label>
        <label>Category<select name="category"><?php foreach (RRP_EXPENSE_CATEGORIES as $k => $v): ?><option value="<?= rrp_h($k) ?>"><?= rrp_h($v) ?></option><?php endforeach; ?></select></label>
        <label>Amount<input type="number" name="amount" step="0.01" min="0.01" required inputmode="decimal" /></label>
        <label>Currency<input type="text" name="currency" maxlength="3" value="<?= rrp_h($sum['currency']) ?>" /></label>
        <label>Date<input type="date" name="spent_on" value="<?= rrp_h($today) ?>" max="<?= rrp_h($today) ?>" /></label>
        <label>Note<input type="text" name="note" maxlength="255" placeholder="Optional" /></label>
      </div>
      <button class="btn primary" type="submit" style="margin-top:10px">Save</button>
      <p class="meta">Or just tell SIPO: "I spent 30 on food".</p>
    </form>

    <section class="care-card">
      <h3 class="label">Recent</h3>
      <?php if (!$sum['recent']): ?><p class="sub">Nothing yet.</p><?php endif; ?>
      <ul class="rt-list">
        <?php foreach ($sum['recent'] as $e): ?>
          <li><span class="rt-when data"><?= rrp_h(date('j M', strtotime((string) $e['spent_on']))) ?></span>
            <b class="rt-title"><?= rrp_h(RRP_EXPENSE_CATEGORIES[$e['category']] ?? $e['category']) ?> · <?= rrp_h($e['pet_name']) ?><?= $e['note'] ? ' <small class="meta">' . rrp_h($e['note']) . '</small>' : '' ?></b>
            <span class="data"><?= rrp_h($money((float) $e['amount'], (string) $e['currency'])) ?></span>
            <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="delete" /><input type="hidden" name="id" value="<?= (int) $e['id'] ?>" /><button class="iconbtn fin-del" type="submit" aria-label="Remove">×</button></form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
