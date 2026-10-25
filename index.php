<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

/**
 * Marketing landing — entry for `/` and `index.php`.
 * Does NOT redirect to login; CTAs open signup / login / app routes.
 */
$owner = rrp_config_ready() ? rrp_current_owner() : null;
$demoVideo = __DIR__ . '/assets/video/sipaw-demo.mp4';
$hasDemoVideo = is_readable($demoVideo);
$sipoBase = rtrim(rrp_asset('assets/mascot/public'), '/');

rrp_layout_head('siPaw — AI pet care companion', [
    'body_class' => 'landing',
    'canonical' => rrp_app_url(),
    'description' => 'siPaw by Nakilab — your AI pet care companion and community for pet owners.',
    'extra_head' => '<script>window.SIPO_ASSET_BASE=' . json_encode($sipoBase) . ';</script>',
]);
?>
<div class="landing-page">
  <header class="landing-top">
    <a class="landing-brand" href="index.php" aria-label="siPaw home">
      <?php rrp_logo_endorsed('landing-logo'); ?>
    </a>
    <nav class="landing-nav" aria-label="Account">
      <?php if ($owner): ?>
        <a class="btn outline landing-nav-btn" href="dashboard.php">Open app</a>
      <?php else: ?>
        <a class="btn outline landing-nav-btn" href="login.php">Log in</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <section class="landing-hero" aria-labelledby="landing-headline">
      <div class="landing-hero-copy">
        <?php rrp_logo_endorsed('landing-hero-mark'); ?>
        <h1 id="landing-headline" class="landing-headline">Your AI pet care companion</h1>
        <p class="landing-lede">
          Caring SI chat and a community built for pet owners — health, routines, and peace of mind in one PWA.
        </p>
        <div class="cta-group">
          <?php if ($owner): ?>
            <a class="btn primary" href="dashboard.php">Open home</a>
            <a class="btn outline" href="chat.php">Talk to SI</a>
          <?php else: ?>
            <a class="btn primary" href="signup.php">Register</a>
            <a class="btn outline" href="login.php">Log in</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="landing-hero-visual" aria-hidden="true">
        <div class="landing-hero-glow"></div>
        <sipo-mascot
          state="idle"
          theme="ice-blue"
          size="220"
          animated="true"
          label="Sipo"
        ></sipo-mascot>
      </div>
    </section>

    <section class="landing-demo" id="demo" aria-labelledby="demo-title">
      <div class="landing-demo-inner">
        <p class="label">See it</p>
        <h2 id="demo-title" class="landing-demo-title">Sipo in motion</h2>
        <p class="landing-demo-sub">
          A short look at the AI companion experience. Replace the placeholder video when your cut is ready.
        </p>

        <div class="landing-demo-stage">
          <?php if ($hasDemoVideo): ?>
            <video
              class="landing-demo-video"
              controls
              playsinline
              preload="metadata"
              poster="<?= rrp_h(rrp_asset('assets/img/landing/demo-poster.png')) ?>"
            >
              <source src="<?= rrp_h(rrp_asset('assets/video/sipaw-demo.mp4')) ?>" type="video/mp4" />
            </video>
          <?php else: ?>
            <div class="landing-demo-motion" role="img" aria-label="Animated siPaw demo with Sipo">
              <div class="landing-demo-poster-bg"></div>
              <div class="landing-demo-chat">
                <div class="landing-demo-bubble landing-demo-bubble--user">
                  Milo seems tired after walks — what should I watch for?
                </div>
                <div class="landing-demo-bubble landing-demo-bubble--si">
                  <sipo-mascot state="writing" theme="ice-blue" size="40" animated="true" label=""></sipo-mascot>
                  <span>Let’s check pace, water, and rest. I can set a gentle recovery routine — want me to draft one?</span>
                </div>
                <div class="landing-demo-community">
                  <span class="data">Community</span>
                  <span>Owners nearby share tips · species boards · caring SI on tap</span>
                </div>
              </div>
              <div class="landing-demo-sipo-large">
                <sipo-mascot state="happy" theme="ice-blue" size="160" animated="true" label="Sipo happy"></sipo-mascot>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <p class="landing-demo-note data">
          Demo file: <code>assets/video/sipaw-demo.mp4</code> · poster: <code>assets/img/landing/demo-poster.png</code>
        </p>
      </div>
    </section>

    <section class="landing-routes" aria-labelledby="routes-title">
      <div class="landing-routes-inner">
        <p class="label">Already in the app</p>
        <h2 id="routes-title" class="landing-routes-title">Jump to what you need</h2>
        <ul class="landing-route-list">
          <li><a href="signup.php">Register</a></li>
          <li><a href="login.php">Log in</a></li>
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="chat.php">SI Chat</a></li>
        </ul>
      </div>
    </section>
  </main>

  <footer class="landing-foot">
    <p>siPaw by Nakilab · AI pet care companion + community · Add to Home Screen for the PWA</p>
  </footer>
</div>

<div class="install-banner" id="installBanner" role="dialog" aria-label="Install app">
  <p id="installText">Install siPaw on your phone for faster care reminders.</p>
  <button type="button" class="btn primary" id="installBtn" style="width:auto;padding:0 16px;height:44px">Install</button>
  <button type="button" class="btn outline" id="installDismiss" style="width:auto;padding:0 12px;height:44px" aria-label="Dismiss">Not now</button>
</div>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<?php
rrp_layout_foot(['push' => false]);
