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
if (!empty($_GET['ref']) && preg_match('/^[A-Za-z0-9]{6}$/', (string) $_GET['ref'])) {
    setcookie('sipaw_ref', strtoupper((string) $_GET['ref']), ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => rrp_request_is_https()]);
    $_COOKIE['sipaw_ref'] = strtoupper((string) $_GET['ref']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
            $error = 'Session expired. Try again.';
        } else {
            $result = rrp_register_owner($_POST);
            if (!empty($result['ok'])) {
                rrp_referral_attach((int) $result['owner_id'], $_COOKIE['sipaw_ref'] ?? null);
                rrp_redirect('si-chat.php?new=1');
            }
            $error = $result['error'] ?? 'Could not create account.';
        }
    } catch (Throwable $e) {
        error_log('signup POST: ' . $e->getMessage());
        $error = rrp_safe_db_error($e, 'Could not create account');
    }
}

rrp_layout_head('Create account — SIPAW', [
    'description' => 'Create your SIPAW account — SI pet care companion by NAKILAB.',
    'body_class' => 'page-auth',
]);
rrp_app_start(['nav' => false]);
?>
<div class="auth-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Join SIPAW</p>
  <p class="sub" style="font-size:14px">SIPO keeps track of your pet's care so you don't have to. No real name needed.</p>
</div>

<?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

<form method="post" class="auth-form" novalidate>
  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
  <input type="hidden" name="timezone" id="tz" value="" />
  <div class="field">
    <label for="nickname">What should SIPO call you?</label>
    <div class="in"><input id="nickname" name="nickname" required maxlength="60" autocomplete="nickname" placeholder="A nickname is perfect" value="<?= rrp_h($_POST['nickname'] ?? '') ?>" /></div>
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
  <label class="checkrow" style="align-items:flex-start;font-size:13px;color:var(--sub)">
    <input type="checkbox" name="accept_terms" value="1" required <?= !empty($_POST['accept_terms']) ? 'checked' : '' ?> />
    <span>I accept the <a class="link" href="legal.php?doc=terms">Terms</a> and <a class="link" href="legal.php?doc=privacy">Privacy Policy</a>.</span>
  </label>
  <button class="btn primary" type="submit">Create account</button>
  <p class="sub" style="font-size:14px;text-align:center;margin-top:6px">
    Already have an account? <a class="link" href="login.php">Log in</a>
  </p>
</form>
<script>try{document.getElementById('tz').value=Intl.DateTimeFormat().resolvedOptions().timeZone||'';}catch(e){}</script>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
