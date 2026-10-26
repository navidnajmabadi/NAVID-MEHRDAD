<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$valid = $token !== '' ? rrp_password_reset_validate($token) : ['ok' => false, 'error' => 'Missing reset token.'];
$error = empty($valid['ok']) ? ($valid['error'] ?? 'Invalid link.') : null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if ($pass !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            $result = rrp_password_reset_complete($token, $pass);
            if (!empty($result['ok'])) {
                $done = true;
            } else {
                $error = $result['error'] ?? 'Could not reset password.';
            }
        }
    }
}

rrp_layout_head('Reset password — SIPAW', [
    'description' => 'Choose a new SIPAW password.',
    'body_class' => 'page-auth',
]);
rrp_app_start(['nav' => false]);
?>
<div class="auth-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Reset password</p>
</div>

<?php if ($done): ?>
  <div class="flash flash-ok" role="status">Password updated. You can log in now.</div>
  <p class="sub" style="text-align:center"><a class="btn primary" href="login.php">Log in</a></p>
<?php else: ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
  <?php if (empty($valid['ok'])): ?>
    <p class="sub" style="text-align:center"><a class="link" href="forgot-password.php">Request a new link</a></p>
  <?php else: ?>
<form method="post" class="auth-form" novalidate>
  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
  <input type="hidden" name="token" value="<?= rrp_h($token) ?>" />
  <div class="field">
    <label for="password">New password</label>
    <div class="in">
      <svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg>
      <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" />
    </div>
  </div>
  <div class="field">
    <label for="password2">Confirm password</label>
    <div class="in">
      <svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg>
      <input id="password2" name="password2" type="password" required autocomplete="new-password" minlength="8" />
    </div>
  </div>
  <button class="btn primary" type="submit">Save new password</button>
</form>
  <?php endif; ?>
<?php endif; ?>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
