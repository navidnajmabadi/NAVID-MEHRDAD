<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

try {
    if (rrp_current_owner()) {
        rrp_redirect('dashboard.php');
    }
} catch (Throwable $e) {
    error_log('login current_owner: ' . $e->getMessage());
}

$next = $_GET['next'] ?? 'dashboard.php';
if (!is_string($next) || !preg_match('#^[a-z0-9_\-./]+\.php(\?.*)?$#i', $next)) {
    $next = 'dashboard.php';
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
            $error = 'Session expired. Try again.';
        } else {
            $result = rrp_login($_POST['email'] ?? '', $_POST['password'] ?? '');
            if (!empty($result['ok'])) {
                rrp_redirect($next);
            }
            $error = $result['error'] ?? 'Login failed.';
        }
    } catch (Throwable $e) {
        error_log('login POST: ' . $e->getMessage());
        $error = rrp_safe_db_error($e, 'Could not log in');
    }
}

rrp_layout_head('Log in — Ring Ring Paw');
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="index.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="signup.php">Sign up</a>
  </div>

  <h1>Welcome back</h1>
  <p class="lede">See scans, live maps, and your pets’ QR tags.</p>

  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="panel">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= rrp_h($_POST['email'] ?? '') ?>" />

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password" />

    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </div>
  </form>
</div>
<?php rrp_layout_foot(); ?>
