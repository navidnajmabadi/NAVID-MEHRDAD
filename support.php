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
            rrp_flash_set('ok', 'Ticket sent. The siPaw team will follow up.');
            rrp_redirect('support.php');
        }
        $error = $result['error'] ?? 'Could not send.';
    }
}

$tickets = rrp_tickets_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

rrp_layout_head('Support — SIPAW');
rrp_app_start(['nav' => 'settings']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Support</h2></div>
  <span style="width:40px"></span>
</div>

<div class="pad stack" style="gap:16px">
  <p class="sub" style="font-size:14px">Send a note to the siPaw team (Nakilab). We’ll reply by email when we can.</p>
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="stack" style="gap:16px" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <div class="field"><label for="subject">Subject</label><div class="in"><input id="subject" name="subject" required maxlength="200" value="<?= rrp_h($_POST['subject'] ?? '') ?>" /></div></div>
    <div class="field"><label for="body">Message</label><div class="in area"><textarea id="body" name="body" required rows="5"><?= rrp_h($_POST['body'] ?? '') ?></textarea></div></div>
    <button type="submit" class="btn primary">Send ticket</button>
  </form>

  <div class="section">
    <span class="label">Your tickets</span>
    <?php if (!$tickets): ?>
      <p class="sub" style="font-size:13px">No tickets yet.</p>
    <?php else: ?>
      <div class="list">
        <?php foreach ($tickets as $t): ?>
          <div class="li">
            <div>
              <b><?= rrp_h($t['subject']) ?></b>
              <small><?= rrp_h(rrp_clip((string) $t['body'], 80)) ?></small>
            </div>
            <span class="data"><?= rrp_h($t['status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="gap"></div>
</div>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
