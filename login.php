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

rrp_layout_head('Log in — siPaw', [
    'description' => 'Log in to siPaw — AI pet care companion by Nakilab.',
    'body_class' => 'page-auth',
]);
rrp_app_start(['nav' => false]);
?>
<div class="auth-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Welcome back</p>
  <p class="sub" style="font-size:14px">Log in for SI chat, health, and your pet community.</p>
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
  <button class="btn primary" type="submit">Log in</button>
  <p class="sub" style="font-size:14px;text-align:center;margin-top:6px">
    New to siPaw? <a class="link" href="signup.php">Create an account</a>
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
