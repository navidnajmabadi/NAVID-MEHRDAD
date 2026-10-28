<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $action = (string) ($_POST['action'] ?? '');
        $ownerId = (int) ($_POST['owner_id'] ?? 0);
        if ($action === 'suspend') {
            $r = rrp_admin_set_owner_status((int) $admin['id'], $ownerId, 'suspended');
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', $r['ok'] ? 'Account suspended.' : ($r['error'] ?? 'Failed'));
        } elseif ($action === 'unsuspend') {
            $r = rrp_admin_set_owner_status((int) $admin['id'], $ownerId, 'active');
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', $r['ok'] ? 'Account reactivated.' : ($r['error'] ?? 'Failed'));
        }
    }
    rrp_redirect('accounts.php?q=' . rawurlencode((string) ($_GET['q'] ?? $_POST['q'] ?? '')));
}

$q = trim((string) ($_GET['q'] ?? ''));
$owners = rrp_admin_search_owners($q, 80);

rrp_admin_shell_start('Accounts', 'accounts');
?>
  <h1>Accounts</h1>
  <p class="lede">Search and suspend owner accounts.</p>
  <?php rrp_render_flash(); ?>

  <form method="get" class="panel row" style="align-items:flex-end">
    <div style="flex:2">
      <label for="q">Search</label>
      <input id="q" name="q" value="<?= rrp_h($q) ?>" placeholder="Email, city, or owner #…" />
    </div>
    <button type="submit" class="btn btn-secondary">Search</button>
  </form>

  <?php foreach ($owners as $o): ?>
    <div class="panel panel-soft" style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div>
          <strong><?= rrp_h(rrp_admin_mask_name((string) $o['name'], (int) $o['id'])) ?></strong>
          <span class="pill <?= $o['status'] === 'active' ? 'pill-unread' : 'pill-ended' ?>"><?= rrp_h($o['status']) ?></span>
          <div class="meta">
            <?= rrp_h(rrp_admin_mask_email((string) $o['email'])) ?><?= !empty($o['phone']) ? ' · phone on file' : '' ?><br />
            <?= rrp_h((string) ($o['city'] ?? '—')) ?>, <?= rrp_h((string) ($o['country'] ?? '—')) ?>
          </div>
          <div class="meta" style="margin-top:4px">
            Joined <?= rrp_h($o['created_at']) ?> UTC
            · sub <?= rrp_h((string) $o['subscription_status']) ?>
            · wallet $<?= rrp_h(number_format((float) $o['wallet_balance'], 2)) ?>
          </div>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
          <input type="hidden" name="owner_id" value="<?= (int) $o['id'] ?>" />
          <input type="hidden" name="q" value="<?= rrp_h($q) ?>" />
          <?php if ($o['status'] === 'active'): ?>
            <input type="hidden" name="action" value="suspend" />
            <button type="submit" class="btn btn-danger" onclick="return confirm('Suspend this account?');">Suspend</button>
          <?php else: ?>
            <input type="hidden" name="action" value="unsuspend" />
            <button type="submit" class="btn btn-secondary">Unsuspend</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$owners): ?><p class="empty">No accounts match.</p><?php endif; ?>
<?php rrp_admin_shell_end(); ?>
