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

rrp_layout_head('Create account — Ring Ring Paw');
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="index.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
    <a href="login.php">Log in</a>
  </div>

  <h1>Create your account</h1>
  <p class="lede">Name, address, phone, and email — so a finder can reach you when your pet’s tag is scanned.</p>

  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="panel" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <label for="name">Your name</label>
    <input id="name" name="name" required autocomplete="name" value="<?= rrp_h($_POST['name'] ?? '') ?>" />

    <label for="address">Address</label>
    <input id="address" name="address" required autocomplete="street-address" value="<?= rrp_h($_POST['address'] ?? '') ?>" />
    <p class="field-hint">Used later for nearby help alerts. Kept private from finders on the scan page.</p>

    <label for="phone">Phone</label>
    <input id="phone" name="phone" type="tel" required autocomplete="tel" value="<?= rrp_h($_POST['phone'] ?? '') ?>" />

    <label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= rrp_h($_POST['email'] ?? '') ?>" />

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" />
    <p class="field-hint">At least 8 characters. Stored hashed — never plain text.</p>

    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </div>
  </form>
</div>
<?php rrp_layout_foot(); ?>
