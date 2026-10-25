<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';

if (rrp_current_admin()) {
    rrp_redirect('index.php');
}

$error = null;
$next = (string) ($_GET['next'] ?? 'index.php');
if (str_contains($next, '://') || str_starts_with($next, '//')) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $result = rrp_admin_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!empty($result['ok'])) {
            rrp_redirect($next !== '' ? $next : 'index.php');
        }
        $error = $result['error'] ?? 'Login failed.';
    }
}

rrp_layout_head('Admin login — SIPAW', ['body_class' => 'admin-body']);
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="<?= rrp_h(rrp_asset('index.php')) ?>">
      <img src="<?= rrp_h(rrp_asset('assets/logo.jpg')) ?>" alt="" width="40" height="40" />
      <span>siPaw</span>
    </a>
  </div>
  <h1>Admin</h1>
  <p class="lede">Navid &amp; Mehrdad ops console. Separate from owner accounts.</p>
  <p class="meta" style="margin:-8px 0 16px">First time? Create admins at <a href="setup.php">admin setup</a> (needs <code>admin_setup_secret</code> in config).</p>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
  <form method="post" class="panel" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <label for="email">Admin email</label>
    <input id="email" name="email" type="email" required autocomplete="username" value="<?= rrp_h($_POST['email'] ?? '') ?>" />
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password" />
    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </div>
  </form>
  <p class="meta">First time? Use <a href="setup.php">admin setup</a> while <code>admin_users</code> is empty.</p>
</div>
<?php rrp_layout_foot(); ?>
