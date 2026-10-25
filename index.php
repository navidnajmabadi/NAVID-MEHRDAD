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
$ice = 'assets/mascot/animated/ice-blue';

/**
 * Ice-blue Sipo GIF (+ webp) for the hero communication row.
 */
function rrp_landing_sipo_frame(string $iceDir, string $state, string $label): void
{
    $gif = rrp_asset($iceDir . '/' . $state . '.gif', true);
    $webp = rrp_asset($iceDir . '/' . $state . '.webp', true);
    echo '<figure class="landing-sipo-frame landing-sipo-frame--' . rrp_h($state) . '">';
    echo '<picture>';
    echo '<source type="image/webp" srcset="' . rrp_h($webp) . '" />';
    echo '<img src="' . rrp_h($gif) . '" alt="" width="180" height="180" loading="eager" decoding="async" />';
    echo '</picture>';
    echo '<figcaption class="data">' . rrp_h($label) . '</figcaption>';
    echo '</figure>';
}

rrp_layout_head('siPaw — AI pet care companion', [
    'body_class' => 'landing',
    'canonical' => rrp_app_url(),
    'description' => 'siPaw by NAKILAB — caring SI chat, health reminders, and a pet-owner community in one PWA.',
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
        <h1 id="landing-headline" class="landing-headline">Care for every paw</h1>
        <p class="landing-lede">
          Caring SI chat, reminders, and a community built for pet owners — in one PWA.
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
      <div class="landing-hero-visual" aria-label="Sipo communicating">
        <div class="landing-hero-glow"></div>
        <div class="landing-sipo-row">
          <?php
          rrp_landing_sipo_frame($ice, 'idle', 'Idle');
          rrp_landing_sipo_frame($ice, 'thinking', 'Thinking');
          rrp_landing_sipo_frame($ice, 'writing', 'Writing');
          rrp_landing_sipo_frame($ice, 'happy', 'Happy');
          ?>
        </div>
        <p class="landing-sipo-caption data">Sipo · SI companion</p>
      </div>
    </section>

    <section class="landing-section landing-what" aria-labelledby="what-title">
      <div class="landing-section-inner">
        <p class="label">What it is</p>
        <h2 id="what-title" class="landing-section-title">siPaw is your SI pet care companion</h2>
        <p class="landing-section-copy">
          A phone-first PWA from NAKILAB: talk with a caring Super Intelligence (SI) about your pets,
          keep routines on track, and tap into owner community boards — without a metal-tag gimmick.
        </p>
      </div>
    </section>

    <section class="landing-section landing-how" aria-labelledby="how-title">
      <div class="landing-section-inner">
        <p class="label">How it works</p>
        <h2 id="how-title" class="landing-section-title">Three steps to calmer care</h2>
        <ol class="landing-steps">
          <li class="landing-step">
            <span class="landing-step-num data">01</span>
            <h3 class="landing-step-title">Register</h3>
            <p class="landing-step-copy">Create your account and add your pet profiles in minutes.</p>
          </li>
          <li class="landing-step">
            <span class="landing-step-num data">02</span>
            <h3 class="landing-step-title">Talk with SI</h3>
            <p class="landing-step-copy">Ask about health, routines, and day-to-day care — Sipo stays with you.</p>
          </li>
          <li class="landing-step">
            <span class="landing-step-num data">03</span>
            <h3 class="landing-step-title">Stay on track</h3>
            <p class="landing-step-copy">Reminders, community tips, and peace of mind on your home screen.</p>
          </li>
        </ol>
      </div>
    </section>

    <section class="landing-section landing-features" aria-labelledby="features-title">
      <div class="landing-section-inner">
        <p class="label">Features</p>
        <h2 id="features-title" class="landing-section-title">Built for everyday pet life</h2>
        <ul class="landing-feature-list">
          <li class="landing-feature">
            <h3 class="landing-feature-title">Care</h3>
            <p class="landing-feature-copy">Pet profiles, health notes, and context SI can use when you chat.</p>
          </li>
          <li class="landing-feature">
            <h3 class="landing-feature-title">Reminders</h3>
            <p class="landing-feature-copy">Keep walks, meds, and check-ins visible so nothing slips.</p>
          </li>
          <li class="landing-feature">
            <h3 class="landing-feature-title">Community</h3>
            <p class="landing-feature-copy">Species boards and owner tips — social, not a full social network.</p>
          </li>
          <li class="landing-feature">
            <h3 class="landing-feature-title">SI</h3>
            <p class="landing-feature-copy">A caring Super Intelligence companion — fact-minded, always on tap.</p>
          </li>
        </ul>
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

    <section class="landing-section landing-trust" aria-labelledby="trust-title">
      <div class="landing-section-inner landing-trust-inner">
        <p class="label">Trust</p>
        <h2 id="trust-title" class="landing-section-title landing-trust-line">
          Your pets’ details stay private — SI is here to help, not to sell your story.
        </h2>
      </div>
    </section>

    <section class="landing-section landing-final-cta" aria-labelledby="final-cta-title">
      <div class="landing-section-inner landing-final-inner">
        <h2 id="final-cta-title" class="landing-section-title">Ready when you are</h2>
        <p class="landing-section-copy">Join siPaw and start caring with SI today.</p>
        <div class="cta-group landing-final-ctas">
          <?php if ($owner): ?>
            <a class="btn primary" href="dashboard.php">Open home</a>
          <?php else: ?>
            <a class="btn primary" href="signup.php">Register</a>
            <a class="btn outline" href="login.php">Log in</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="landing-foot">
    <p>
      siPaw by
      <a class="landing-foot-link" href="https://nakilab.com" target="_blank" rel="noopener noreferrer">NAKILAB</a>
      · AI pet care companion + community · Add to Home Screen for the PWA
    </p>
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
