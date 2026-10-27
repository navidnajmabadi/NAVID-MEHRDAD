<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

try {
    if (rrp_current_owner()) {
        rrp_redirect(rrp_safe_next($_GET['next'] ?? null));
    }
} catch (Throwable $e) {
    error_log('login current_owner: ' . $e->getMessage());
}

$next = rrp_safe_next($_GET['next'] ?? null);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
            $error = 'Session expired. Try again.';
        } else {
            $result = rrp_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), !empty($_POST['remember']));
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

rrp_layout_head('Log in — SIPAW', [
    'description' => 'Log in to SIPAW — SI pet care companion by NAKILAB.',
    'body_class' => 'page-auth',
]);
rrp_app_start(['nav' => false]);
?>
<div class="auth-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Welcome back</p>
  <p class="sub" style="font-size:14px">SIPO has been keeping an eye on things. Log in to catch up.</p>
</div>

<?php rrp_render_flash(); ?>
<?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

<form method="post" class="auth-form" id="login-form" novalidate>
  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
  <div class="field">
    <label for="email">Email</label>
    <div class="in">
      <svg class="ico" aria-hidden="true"><use href="#i-mail"/></svg>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= rrp_h($_POST['email'] ?? '') ?>" />
    </div>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <div class="in">
      <svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg>
      <input id="password" name="password" type="password" required autocomplete="current-password" />
      <button type="button" id="peek" aria-label="Show password"><svg class="ico" aria-hidden="true"><use href="#i-eye"/></svg></button>
    </div>
  </div>
  <label class="checkrow">
    <input type="checkbox" name="remember" value="1" <?= ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['remember'])) ? 'checked' : '' ?> />
    <span>Keep me logged in on this device</span>
  </label>
  <button class="btn primary" type="submit">Log in</button>
  <p class="sub" style="font-size:14px;text-align:center;margin-top:6px">
    <a class="link" href="forgot-password.php">Forgot password?</a>
  </p>
  <p class="sub" style="font-size:14px;text-align:center;margin-top:6px">
    New to SIPAW? <a class="link" href="signup.php">Create an account</a>
  </p>
</form>
<script>
(function(){
  var peek=document.getElementById('peek');
  var pass=document.getElementById('password');
  if(peek&&pass){peek.addEventListener('click',function(){pass.type=pass.type==='password'?'text':'password';});}
})();
</script>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
