<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_config_ready() ? rrp_current_owner() : null;

rrp_layout_head('Ring Ring Paw — Lost pet? Scan. Call. Home.', [
    'body_class' => 'landing',
    'canonical' => rrp_app_url(),
]);
?>
<main class="landing-hero">
  <img class="brand-mark" src="assets/logo.jpg" width="168" height="168" alt="Ring Ring Paw logo" />
  <p class="brand-name">Ring Ring Paw</p>
  <h1 class="landing-headline">Lost pet? Scan. Call. Home.</h1>
  <p class="landing-sub">A collar QR that reconnects finders with owners — fast, calm, and on your phone.</p>
  <div class="cta-group">
    <?php if ($owner): ?>
      <a class="btn btn-primary" href="dashboard.php">Open dashboard</a>
      <a class="btn btn-ghost" href="pet-form.php">Add a pet</a>
    <?php else: ?>
      <a class="btn btn-primary" href="signup.php">Get started</a>
      <a class="btn btn-ghost" href="login.php">Log in</a>
    <?php endif; ?>
  </div>
  <div class="hero-visual" role="img" aria-label="Product demo placeholder — video coming soon"></div>
</main>
<p class="landing-foot">A Nakilab product · Add to Home Screen for quick access</p>

<div class="install-banner" id="installBanner" role="dialog" aria-label="Install app">
  <p id="installText">Install Ring Ring Paw on your phone for faster rescues.</p>
  <button type="button" class="btn btn-primary" id="installBtn">Install</button>
  <button type="button" class="btn btn-ghost" id="installDismiss" aria-label="Dismiss">Not now</button>
</div>
<?php
rrp_layout_foot();
