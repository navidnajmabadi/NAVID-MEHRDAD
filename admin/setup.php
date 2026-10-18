<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';

/**
 * One-time bootstrap for the first two admin accounts (Navid + Mehrdad).
 * Requires admin_setup_secret from config. Disabled once 2+ admins exist
 * (or still allows adding until 2 if count < 2).
 */

$cfg = rrp_load_config();
$secret = (string) ($cfg['admin_setup_secret'] ?? '');
$count = rrp_admin_count();
$error = null;
$okMsg = null;

if ($count >= 2) {
    rrp_layout_head('Admin setup — Ring Ring Paw');
    echo '<div class="shell"><h1>Setup closed</h1><p class="lede">Two admin accounts already exist. Use <a href="login.php">admin login</a>.</p></div>';
    rrp_layout_foot();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSecret = (string) ($_POST['setup_secret'] ?? '');
    if ($secret === '' || $secret === 'CHANGE_ME_ADMIN_SETUP_SECRET' || !hash_equals($secret, $postedSecret)) {
        $error = 'Invalid setup secret. Set admin_setup_secret in ringringpaw.php.';
    } elseif (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $result = rrp_admin_create(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if (!empty($result['ok'])) {
            rrp_admin_log(null, 'admin_setup_create', 'admin_users', (int) $result['admin_id'], 'Created via setup.php');
            $count = rrp_admin_count();
            if ($count >= 2) {
                rrp_flash_set('ok', 'Second admin created. Setup is now closed. Log in below.');
                rrp_redirect('login.php');
            }
            $okMsg = 'Admin created (' . $count . '/2). Create the second account (Navid or Mehrdad).';
            $_POST = [];
        } else {
            $error = $result['error'] ?? 'Could not create admin.';
        }
    }
}

rrp_layout_head('Create admin — Ring Ring Paw', ['body_class' => 'admin-body']);
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="../index.php">
      <img src="../assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="login.php">Login</a>
  </div>
  <h1>Create admin (<?= (int) $count ?>/2)</h1>
  <p class="lede">Create Navid and Mehrdad as separate admin users. Requires <code>admin_setup_secret</code> from config.</p>
  <?php if ($okMsg): ?><div class="flash flash-ok" role="status"><?= rrp_h($okMsg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
  <form method="post" class="panel" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <label for="setup_secret">Setup secret</label>
    <input id="setup_secret" name="setup_secret" type="password" required autocomplete="off" />
    <label for="name">Name</label>
    <input id="name" name="name" required value="<?= rrp_h($_POST['name'] ?? '') ?>" placeholder="Navid or Mehrdad" />
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required value="<?= rrp_h($_POST['email'] ?? '') ?>" />
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" />
    <p class="field-hint">At least 10 characters.</p>
    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block">Create admin</button>
    </div>
  </form>
</div>
<?php rrp_layout_foot(); ?>
