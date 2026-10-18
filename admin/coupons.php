<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $r = rrp_coupon_create((int) $admin['id'], $_POST);
        if (!empty($r['ok'])) {
            rrp_flash_set('ok', 'Coupon created.');
            rrp_redirect('coupons.php');
        }
        $error = $r['error'] ?? 'Could not create.';
    } elseif ($action === 'disable') {
        rrp_coupon_set_status((int) $admin['id'], (int) ($_POST['coupon_id'] ?? 0), 'disabled');
        rrp_flash_set('ok', 'Coupon disabled.');
        rrp_redirect('coupons.php');
    } elseif ($action === 'enable') {
        rrp_coupon_set_status((int) $admin['id'], (int) ($_POST['coupon_id'] ?? 0), 'active');
        rrp_flash_set('ok', 'Coupon enabled.');
        rrp_redirect('coupons.php');
    }
}

$coupons = rrp_coupons_list();

rrp_admin_shell_start('Coupons', 'coupons');
?>
  <h1>Coupons</h1>
  <p class="lede">Issue codes now; checkout redemption comes with paid seats.</p>
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="panel">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <input type="hidden" name="action" value="create" />
    <label for="code">Code</label>
    <input id="code" name="code" required pattern="[A-Za-z0-9_-]{3,40}" placeholder="WELCOME20" value="<?= rrp_h($_POST['code'] ?? '') ?>" />
    <label for="description">Description</label>
    <input id="description" name="description" value="<?= rrp_h($_POST['description'] ?? '') ?>" />
    <label for="discount_type">Type</label>
    <select id="discount_type" name="discount_type">
      <option value="percent">Percent</option>
      <option value="fixed">Fixed $</option>
      <option value="free_month">Free month</option>
    </select>
    <label for="discount_value">Value</label>
    <input id="discount_value" name="discount_value" type="number" step="0.01" min="0" value="<?= rrp_h($_POST['discount_value'] ?? '10') ?>" />
    <label for="max_redemptions">Max redemptions (optional)</label>
    <input id="max_redemptions" name="max_redemptions" type="number" min="1" value="<?= rrp_h($_POST['max_redemptions'] ?? '') ?>" />
    <div class="stack-actions">
      <button type="submit" class="btn btn-primary">Create coupon</button>
    </div>
  </form>

  <?php foreach ($coupons as $c): ?>
    <div class="panel panel-soft" style="margin-bottom:10px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
      <div>
        <strong><?= rrp_h($c['code']) ?></strong>
        <span class="pill"><?= rrp_h($c['status']) ?></span>
        <div class="meta">
          <?= rrp_h($c['discount_type']) ?>
          <?= $c['discount_type'] !== 'free_month' ? ' · ' . rrp_h((string) $c['discount_value']) : '' ?>
          · used <?= (int) $c['redemption_count'] ?><?= $c['max_redemptions'] !== null ? '/' . (int) $c['max_redemptions'] : '' ?>
          <?php if ($c['description']): ?> · <?= rrp_h($c['description']) ?><?php endif; ?>
        </div>
      </div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="coupon_id" value="<?= (int) $c['id'] ?>" />
        <?php if ($c['status'] === 'active'): ?>
          <input type="hidden" name="action" value="disable" />
          <button type="submit" class="btn btn-ghost">Disable</button>
        <?php else: ?>
          <input type="hidden" name="action" value="enable" />
          <button type="submit" class="btn btn-secondary">Enable</button>
        <?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
<?php rrp_admin_shell_end(); ?>
