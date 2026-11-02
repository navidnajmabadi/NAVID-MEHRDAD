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
$status = in_array(($_GET['status'] ?? ''), ['active', 'suspended'], true) ? (string) $_GET['status'] : '';
$sort = in_array(($_GET['sort'] ?? ''), ['joined', 'tokens', 'msgs', 'last'], true) ? (string) $_GET['sort'] : 'joined';
$owners = rrp_admin_search_owners($q, 200);
if ($status !== '') {
    $owners = array_values(array_filter($owners, static fn($o) => $o['status'] === $status));
}
$usage = rrp_admin_usage_map(array_map(static fn($o) => (int) $o['id'], $owners));
$val = static function (array $o) use ($usage, $sort) {
    $u = $usage[(int) $o['id']] ?? [];
    return match ($sort) {
        'tokens' => (int) ($u['tokens_7d'] ?? 0),
        'msgs' => (int) ($u['msgs_7d'] ?? 0),
        'last' => (string) ($u['last_chat'] ?? ''),
        default => (string) $o['created_at'],
    };
};
usort($owners, static fn($a, $b) => $val($b) <=> $val($a));
$sum = ['msgs' => 0, 'tokens' => 0, 'active' => 0];
foreach ($owners as $o) {
    $u = $usage[(int) $o['id']] ?? ['msgs_7d' => 0, 'tokens_7d' => 0];
    $sum['msgs'] += $u['msgs_7d'];
    $sum['tokens'] += $u['tokens_7d'];
    $sum['active'] += $o['status'] === 'active' ? 1 : 0;
}
$perMsg = $sum['msgs'] ? (int) round($sum['tokens'] / $sum['msgs']) : 0;
$maxTok = max(1, max(array_map(static fn($o) => (int) ($usage[(int) $o['id']]['tokens_7d'] ?? 0), $owners ?: [[ 'id' => 0 ]])));
$qs = static fn(array $over) => 'accounts.php?' . http_build_query(array_filter(array_merge(['q' => $q, 'status' => $status, 'sort' => $sort], $over), static fn($v) => $v !== ''));
$th = static function (string $label, string $key, bool $num = false) use ($sort, $qs): string {
    $on = $sort === $key;
    return '<th' . ($num ? ' class="num"' : '') . ($on ? ' aria-sort="descending"' : '') . '><a href="' . rrp_h($qs(['sort' => $key])) . '"' . ($on ? ' aria-sort="descending"' : '') . '>' . rrp_h($label) . ($on ? ' ↓' : '') . '</a></th>';
};

rrp_admin_shell_start('Accounts', 'accounts');
?>
  <h1>Accounts</h1>
  <p class="lede">Find an owner, see how much SIPO they use, and manage their account. Names and emails are masked.</p>
  <?php rrp_render_flash(); ?>

  <div class="ad-kpis">
    <div class="ad-kpi"><b><?= count($owners) ?></b><span>Accounts shown (<?= (int) $sum['active'] ?> active)</span></div>
    <div class="ad-kpi"><b><?= number_format($sum['msgs']) ?></b><span>Messages to SIPO, 7 days</span></div>
    <div class="ad-kpi"><b><?= number_format($sum['tokens']) ?></b><span>Tokens, 7 days</span></div>
    <div class="ad-kpi <?= $perMsg > 12000 ? 'is-bad' : ($perMsg > 7000 ? 'is-warn' : 'is-good') ?>"><b><?= number_format($perMsg) ?></b><span>Tokens per message (aim under 7,000)</span></div>
  </div>

  <form method="get" class="ad-toolbar" role="search">
    <label class="ad-field" style="flex:2 1 260px">Search<input id="q" name="q" value="<?= rrp_h($q) ?>" placeholder="Email, city or owner number" /></label>
    <label class="ad-field">Status<select name="status"><option value="">All</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option></select></label>
    <label class="ad-field">Sort by<select name="sort"><?php foreach (['joined' => 'Newest', 'tokens' => 'Most tokens', 'msgs' => 'Most messages', 'last' => 'Last chat'] as $k => $l): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <button type="submit" class="ad-btn is-primary">Apply</button>
  </form>

  <?php if (!$owners): ?>
    <div class="ad-tablewrap"><p class="ad-empty">No accounts match. Clear the search or the status filter.</p></div>
  <?php else: ?>
  <div class="ad-tablewrap">
    <table class="ad-table">
      <thead><tr>
        <th>Owner</th><th>Status</th><th>Where</th>
        <?= $th('Joined', 'joined') ?>
        <?= $th('Messages 7d', 'msgs', true) ?>
        <?= $th('Tokens 7d', 'tokens', true) ?>
        <th class="num">Per message</th>
        <?= $th('Last chat', 'last') ?>
        <th><span class="sr-only">Actions</span></th>
      </tr></thead>
      <tbody>
      <?php foreach ($owners as $o): $u = $usage[(int) $o['id']] ?? ['msgs_today' => 0, 'msgs_7d' => 0, 'msgs_all' => 0, 'tokens_today' => 0, 'tokens_7d' => 0, 'tokens_all' => 0, 'last_chat' => null];
          $pm = $u['msgs_7d'] ? (int) round($u['tokens_7d'] / $u['msgs_7d']) : 0; ?>
        <tr>
          <td class="ad-who" data-label="Owner"><b><?= rrp_h(rrp_admin_mask_name((string) $o['name'], (int) $o['id'])) ?></b><small><?= rrp_h(rrp_admin_mask_email((string) $o['email'])) ?><?= !empty($o['phone']) ? ' · phone on file' : '' ?></small></td>
          <td data-label="Status"><span class="ad-chip <?= $o['status'] === 'active' ? 'is-good' : 'is-bad' ?>"><?= rrp_h($o['status']) ?></span></td>
          <td data-label="Where"><?= rrp_h(trim((string) ($o['city'] ?? '') . ((!empty($o['city']) && !empty($o['country'])) ? ', ' : '') . (string) ($o['country'] ?? '')) ?: '—') ?></td>
          <td data-label="Joined"><?= rrp_h(substr((string) $o['created_at'], 0, 10)) ?></td>
          <td class="num" data-label="Messages 7d"><?= (int) $u['msgs_7d'] ?><small class="meta"> · all <?= (int) $u['msgs_all'] ?></small></td>
          <td class="num" data-label="Tokens 7d"><?= number_format($u['tokens_7d']) ?><span class="ad-bar <?= $pm > 12000 ? 'is-heavy' : '' ?>" aria-hidden="true"><i style="width:<?= max(2, (int) round(100 * $u['tokens_7d'] / $maxTok)) ?>%"></i></span></td>
          <td class="num" data-label="Per message"><?= $pm ? number_format($pm) : '—' ?></td>
          <td data-label="Last chat" style="white-space:nowrap"><?= $u['last_chat'] ? rrp_h(substr((string) $u['last_chat'], 0, 16)) . ' UTC' : 'never' ?></td>
          <td>
            <details class="ad-menu">
              <summary aria-label="Actions for <?= rrp_h(rrp_admin_mask_name((string) $o['name'], (int) $o['id'])) ?>"><?= rrp_admin_icon('dots') ?></summary>
              <div class="ad-menu-pop">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="owner_id" value="<?= (int) $o['id'] ?>" />
                  <input type="hidden" name="q" value="<?= rrp_h($q) ?>" />
                  <?php if ($o['status'] === 'active'): ?>
                    <input type="hidden" name="action" value="suspend" />
                    <button type="submit" class="is-danger" onclick="return confirm('Suspend this account? The owner will not be able to use SIPAW until you reactivate it.');">Suspend account</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="unsuspend" />
                    <button type="submit">Reactivate account</button>
                  <?php endif; ?>
                </form>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
<?php rrp_admin_shell_end(); ?>
