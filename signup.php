<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

try {
    if (rrp_current_owner()) {
        rrp_redirect('dashboard.php');
    }
} catch (Throwable $e) {
    error_log('signup current_owner: ' . $e->getMessage());
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
            $error = 'Session expired. Try again.';
        } else {
            $result = rrp_register_owner($_POST);
            if (!empty($result['ok'])) {
                rrp_flash_set('ok', 'Welcome! Add your first pet to get a QR tag.');
                rrp_redirect('pet-form.php');
            }
            $error = $result['error'] ?? 'Could not create account.';
        }
    } catch (Throwable $e) {
        error_log('signup POST: ' . $e->getMessage());
        $error = rrp_safe_db_error($e, 'Could not create account');
    }
}

rrp_layout_head('Create account — siPaw', [
    'description' => 'Create your siPaw account. Home is one scan away.',
    'body_class' => 'page-auth',
]);
rrp_app_start(['nav' => false]);
?>
<div class="auth-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Home is one scan away.</p>
  <p class="sub" style="font-size:14px">Name, address, phone, and email — so a finder can reach you.</p>
</div>

<?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

<form method="post" class="auth-form" novalidate>
  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
  <div class="field">
    <label for="name">Your name</label>
    <div class="in"><input id="name" name="name" required autocomplete="name" value="<?= rrp_h($_POST['name'] ?? '') ?>" /></div>
  </div>
  <div class="field">
    <label for="address">Address</label>
    <div class="in"><input id="address" name="address" required autocomplete="street-address" value="<?= rrp_h($_POST['address'] ?? '') ?>" /></div>
    <small>Used for nearby help alerts. Kept private from finders.</small>
  </div>
  <div class="field">
    <label for="phone">Phone</label>
    <div class="in"><input id="phone" name="phone" type="tel" required autocomplete="tel" value="<?= rrp_h($_POST['phone'] ?? '') ?>" /></div>
  </div>
  <div class="field">
    <label for="email">Email</label>
    <div class="in"><svg class="ico" aria-hidden="true"><use href="#i-mail"/></svg><input id="email" name="email" type="email" required autocomplete="email" value="<?= rrp_h($_POST['email'] ?? '') ?>" /></div>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <div class="in"><svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" /></div>
    <small>At least 8 characters.</small>
  </div>
  <button class="btn primary" type="submit">Create account</button>
  <p class="sub" style="font-size:14px;text-align:center;margin-top:6px">
    Already have an account? <a class="link" href="login.php">Log in</a>
  </p>
</form>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
