<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_config_ready() ? rrp_current_owner() : null;

rrp_layout_head('siPaw — Home is one scan away.', [
    'body_class' => 'landing',
    'canonical' => rrp_app_url(),
    'description' => 'siPaw by Nakilab. Tag, scan, find. Home is one scan away.',
]);
?>
<main class="landing-hero">
  <?php rrp_logo_endorsed(); ?>
  <p class="tagline">Home is one scan away.</p>
  <p class="sub" style="font-size:15px;max-width:32ch">
    A collar QR that reconnects finders with owners — Tag, Scan, Find.
  </p>
  <div class="cta-group">
    <?php if ($owner): ?>
      <a class="btn primary" href="dashboard.php">Open home</a>
      <a class="btn outline" href="pet-form.php">Add a pet</a>
    <?php else: ?>
      <a class="btn primary" href="signup.php">Get started</a>
      <a class="btn outline" href="login.php">Log in</a>
    <?php endif; ?>
  </div>
</main>
<p class="landing-foot">siPaw by Nakilab · Add to Home Screen for quick access</p>

<div class="install-banner" id="installBanner" role="dialog" aria-label="Install app">
  <p id="installText">Install siPaw on your phone for faster rescues.</p>
  <button type="button" class="btn primary" id="installBtn" style="width:auto;padding:0 16px;height:44px">Install</button>
  <button type="button" class="btn outline" id="installDismiss" style="width:auto;padding:0 12px;height:44px" aria-label="Dismiss">Not now</button>
</div>
<?php
rrp_layout_foot();
