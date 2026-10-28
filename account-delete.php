<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Last step of deleting an account: emailed link + typed DELETE.
$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$ownerId = rrp_account_delete_token_owner($token);
$error = null;
$done = false;

if ($ownerId && $_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    if (trim((string) ($_POST['confirm'] ?? '')) !== 'DELETE') {
        $error = 'Type DELETE in capitals to confirm.';
    } elseif (rrp_account_delete($ownerId)) {
        rrp_logout();
        $done = true;
    } else {
        $error = 'Something went wrong. Nothing was deleted. Please try again or contact support.';
    }
}

rrp_layout_head('Delete account — SIPAW', ['body_class' => 'page-auth']);
?>
<main class="pad stack" style="max-width:460px;margin:40px auto;gap:16px">
  <?php rrp_logo_endorsed(); ?>
  <?php if ($done): ?>
    <h1>Account deleted</h1>
    <p class="sub">Your account and all its data are gone. Thank you for caring for your pets with SIPAW 💙</p>
    <a class="btn outline" href="index.php">Back to SIPAW</a>
  <?php elseif (!$ownerId): ?>
    <h1>Link expired</h1>
    <p class="sub">This link is not valid any more (it works for 30 minutes and stops if the password changed). Ask for a new one in Settings → Danger zone.</p>
    <a class="btn outline" href="settings.php">Open Settings</a>
  <?php else: ?>
    <h1 style="color:#e5484d">Delete your account?</h1>
    <p class="sub">Your profile, every pet, chats with SIPO, health records, routines, posts and paw coins will be removed for good. This cannot be undone.</p>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
    <form method="post" class="stack" style="gap:10px">
      <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
      <input type="hidden" name="t" value="<?= rrp_h($token) ?>" />
      <label for="confirm">Type <b>DELETE</b> to confirm</label>
      <input id="confirm" name="confirm" autocomplete="off" autocapitalize="characters" required style="min-height:44px" />
      <button class="btn" type="submit" style="background:#e5484d;border-color:#e5484d;color:#fff">Delete my account forever</button>
      <a class="btn outline" href="settings.php">Keep my account</a>
    </form>
  <?php endif; ?>
</main>
<?php
rrp_layout_foot(['push' => false]);
