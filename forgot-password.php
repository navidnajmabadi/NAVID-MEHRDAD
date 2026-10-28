<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

if (rrp_current_owner()) {
    rrp_redirect('dashboard.php');
}

$error = null;
$ok = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $result = rrp_password_reset_request((string) ($_POST['email'] ?? ''));
        if (empty($result['ok'])) {
            $raw = (string) ($result['error'] ?? 'Could not send reset email.');
            // Mail setup problems (SMTP, config paths, CLI tips) are logged, not shown to visitors.
            if (preg_match('/smtp|config|php scripts|mail\(\)|hostinger/i', $raw)) {
                error_log('forgot-password: ' . $raw);
                $raw = 'We couldn’t send the email right now. Please try again later.';
            }
            $error = $raw;
        } else {
            $ok = $result['message'] ?? 'If that email is registered, we sent a reset link.';
        }
    }
}

rrp_layout_head('Forgot password — SIPAW', [
    'description' => 'Reset your SIPAW password via email.',
    'body_class' => 'page-auth',
]);
rrp_app_start(['nav' => false, 'auth' => true]);
?>
<div class="auth-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Forgot password</p>
  <p class="sub" style="font-size:14px">Enter your email and we’ll send you a link to choose a new password.</p>
</div>

<?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="flash flash-ok" role="status"><?= rrp_h($ok) ?></div><?php endif; ?>
<?php
$smtpHint = rrp_password_reset_smtp_ready();
if (empty($smtpHint['ok']) && !$ok && !$error):
    // Setup details (server paths, config keys) go to the log, never to visitors.
    error_log('forgot-password: email not ready: ' . (string) ($smtpHint['error'] ?? ''));
?>
  <div class="flash flash-error" role="status">Password reset by email isn’t available right now. Please <?= rrp_google_configured() ? 'use <b>Continue with Google</b> on the Log in page, or ' : '' ?>try again later.</div>
<?php endif; ?>

<form method="post" class="auth-form" novalidate>
  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
  <div class="field">
    <label for="email">Email</label>
    <div class="in">
      <svg class="ico" aria-hidden="true"><use href="#i-mail"/></svg>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= rrp_h($_POST['email'] ?? '') ?>" />
    </div>
  </div>
  <button class="btn primary" type="submit">Send reset link</button>
  <p class="sub" style="font-size:14px;text-align:center;margin-top:6px">
    <a class="link" href="login.php">Back to log in</a>
  </p>
</form>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
