<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $result = rrp_ticket_create((int) $owner['id'], (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''));
        if (!empty($result['ok'])) {
            rrp_flash_set('ok', 'Ticket sent. Navid & Mehrdad will follow up.');
            rrp_redirect('support.php');
        }
        $error = $result['error'] ?? 'Could not send.';
    }
}

$tickets = rrp_tickets_for_owner((int) $owner['id']);

rrp_layout_head('Support — Ring Ring Paw');
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="dashboard.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="dashboard.php">Dashboard</a>
  </div>

  <h1>Support</h1>
  <p class="lede">Send a note to the Ring Ring Paw team. We’ll reply by email when we can.</p>
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="panel" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <label for="subject">Subject</label>
    <input id="subject" name="subject" required maxlength="200" value="<?= rrp_h($_POST['subject'] ?? '') ?>" />
    <label for="body">Message</label>
    <textarea id="body" name="body" required rows="5"><?= rrp_h($_POST['body'] ?? '') ?></textarea>
    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block">Send ticket</button>
    </div>
  </form>

  <section class="panel" aria-labelledby="histHeading">
    <h2 id="histHeading">Your tickets</h2>
    <?php if (!$tickets): ?>
      <p class="empty">No tickets yet.</p>
    <?php else: ?>
      <?php foreach ($tickets as $t): ?>
        <div class="panel panel-soft" style="margin-top:10px;margin-bottom:0">
          <div style="display:flex;justify-content:space-between;gap:8px">
            <strong><?= rrp_h($t['subject']) ?></strong>
            <span class="pill"><?= rrp_h($t['status']) ?></span>
          </div>
          <p class="meta" style="white-space:pre-wrap;margin:6px 0 0"><?= rrp_h($t['body']) ?></p>
          <?php if (!empty($t['admin_note'])): ?>
            <p class="meta" style="margin:8px 0 0"><strong>Team note:</strong> <?= rrp_h($t['admin_note']) ?></p>
          <?php endif; ?>
          <p class="meta" style="margin:6px 0 0"><?= rrp_h($t['created_at']) ?> UTC</p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>
<?php rrp_layout_foot(); ?>
