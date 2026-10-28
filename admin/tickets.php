<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $r = rrp_admin_update_ticket(
        (int) $admin['id'],
        (int) ($_POST['ticket_id'] ?? 0),
        (string) ($_POST['status'] ?? 'open'),
        trim((string) ($_POST['admin_note'] ?? '')) ?: null
    );
    rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', $r['ok'] ? 'Ticket updated.' : ($r['error'] ?? 'Failed'));
    rrp_redirect('tickets.php?status=' . rawurlencode((string) ($_GET['status'] ?? 'all')));
}

$status = (string) ($_GET['status'] ?? 'all');
$tickets = rrp_admin_list_tickets($status === 'all' ? null : $status);

rrp_admin_shell_start('Tickets', 'tickets');
?>
  <h1>Support tickets</h1>
  <p class="lede">Manage user support requests.</p>
  <?php rrp_render_flash(); ?>

  <div class="nav-links">
    <?php foreach (['all', 'open', 'in_progress', 'resolved', 'closed'] as $s): ?>
      <a href="?status=<?= rrp_h($s) ?>"<?= $status === $s ? ' class="is-active" style="text-decoration:underline"' : '' ?>><?= rrp_h($s) ?></a>
    <?php endforeach; ?>
  </div>

  <?php foreach ($tickets as $t): ?>
    <div class="panel" style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
        <strong>#<?= (int) $t['id'] ?> · <?= rrp_h($t['subject']) ?></strong>
        <span class="pill"><?= rrp_h($t['status']) ?></span>
      </div>
      <p class="meta" style="margin:6px 0">
        <?= rrp_h(rrp_admin_mask_name((string) $t['owner_name'], (int) ($t['owner_id'] ?? 0))) ?> · <?= rrp_h(rrp_admin_mask_email((string) $t['owner_email'])) ?> · <?= rrp_h($t['created_at']) ?> UTC
      </p>
      <p style="white-space:pre-wrap;margin:0 0 12px"><?= rrp_h($t['body']) ?></p>
      <form method="post" class="panel panel-soft" style="margin:0">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>" />
        <label for="status_<?= (int) $t['id'] ?>">Status</label>
        <select id="status_<?= (int) $t['id'] ?>" name="status">
          <?php foreach (['open', 'in_progress', 'resolved', 'closed'] as $s): ?>
            <option value="<?= $s ?>"<?= $t['status'] === $s ? ' selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <label for="note_<?= (int) $t['id'] ?>">Admin note</label>
        <textarea id="note_<?= (int) $t['id'] ?>" name="admin_note" rows="2"><?= rrp_h((string) ($t['admin_note'] ?? '')) ?></textarea>
        <div class="stack-actions">
          <button type="submit" class="btn btn-secondary">Save</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$tickets): ?><p class="empty">No tickets.</p><?php endif; ?>
<?php rrp_admin_shell_end(); ?>
