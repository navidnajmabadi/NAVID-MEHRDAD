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
$year = (int) date('Y');

rrp_layout_head('siPaw — SI pet care companion', [
    'body_class' => 'landing',
    'theme' => 'dark',
    'canonical' => rrp_app_url(),
    'description' => 'siPaw by NAKILAB — caring SI chat, health reminders, and a pet-owner community in one PWA.',
    'extra_head' => '<script>window.SIPO_ASSET_BASE=' . json_encode($sipoBase) . ';</script>',
]);
?>
<div class="landing-page">
  <header class="landing-top">
    <a class="landing-brand" href="index.php" aria-label="siPaw home">
      <?php rrp_wordmark_sipaw(['class' => 'landing-logo', 'size' => 'sm', 'endorsed' => false]); ?>
    </a>
    <nav class="landing-nav" aria-label="Primary">
      <a class="landing-nav-link" href="#features">Features</a>
      <a class="landing-nav-link" href="#how">How it works</a>
      <?php if ($owner): ?>
        <a class="btn outline landing-nav-btn" href="dashboard.php">Open app</a>
      <?php else: ?>
        <a class="btn outline landing-nav-btn" href="login.php">Log in</a>
        <a class="btn primary landing-nav-btn" href="signup.php">Register</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <section class="landing-hero" aria-labelledby="landing-headline">
      <div class="landing-hero-copy">
        <div class="landing-hero-brand" aria-label="siPaw by NAKILAB">
          <?php rrp_mark_si('landing-hero-si', 'xl'); ?>
          <div class="landing-hero-lockup">
            <p class="landing-hero-product" aria-label="siPaw">
              <span class="sipaw-wm__si">SI</span><span class="sipaw-wm__paw">PAW</span>
            </p>
            <p class="landing-hero-by data">by <span class="sipaw-wm__co">NAKILAB</span></p>
          </div>
        </div>
        <h1 id="landing-headline" class="landing-headline">Your pet’s SI sidekick</h1>
        <p class="landing-lede">
          Chatty care, reminders that actually fire, community that gets it — one PWA.
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
        <ul class="landing-chips" aria-label="Quick hits">
          <li class="landing-chip">Dinner? Already nagging you</li>
          <li class="landing-chip">Milo mood-check in one tap</li>
          <li class="landing-chip">Owners who get the chaos</li>
        </ul>
      </div>

      <div class="landing-hero-visual" aria-label="Sipo trying to talk">
        <div class="landing-hero-glow" aria-hidden="true"></div>
        <div class="landing-talk" id="landingTalk">
          <div class="landing-bubbles" aria-live="polite">
            <p class="landing-bubble is-active" data-state="happy" data-index="0">
              Hi — I’m <strong>SIPO</strong>, your pet assistant 👋
            </p>
            <p class="landing-bubble" data-state="listening" data-index="1" hidden>
              Forgot dinner again? I got reminders.
            </p>
            <p class="landing-bubble" data-state="thinking" data-index="2" hidden>
              Rough day for Milo? Let’s check it.
            </p>
            <p class="landing-bubble" data-state="writing" data-index="3" hidden>
              Want a recovery routine? I can draft one.
            </p>
            <p class="landing-bubble" data-state="idle" data-index="4" hidden>
              I’m right here when you need a paw.
            </p>
          </div>
          <div class="landing-sipo-stage">
            <sipo-mascot
              id="landingSipo"
              state="happy"
              theme="ice-blue"
              size="280"
              animated="true"
              label="Sipo, SI companion"
            ></sipo-mascot>
          </div>
        </div>
      </div>
    </section>

    <section class="landing-section landing-what" aria-labelledby="what-title">
      <div class="landing-section-inner landing-what-grid">
        <div class="landing-what-copy">
          <p class="label">What it is</p>
          <h2 id="what-title" class="landing-section-title">SI for real pet life</h2>
          <p class="landing-section-copy">
            Phone-first PWA from NAKILAB. Talk to Super Intelligence about your pets,
            keep routines tight, tap owner boards — no metal-tag gimmick.
          </p>
        </div>
        <div class="landing-visual-pair" aria-hidden="true">
          <figure class="landing-pet-card landing-pet-card--dog">
            <svg class="landing-pet-svg" viewBox="0 0 120 100" role="img"><title>Dog</title>
              <ellipse cx="60" cy="62" rx="34" ry="28" fill="#18181B" stroke="#3F3F46" stroke-width="2"/>
              <ellipse cx="28" cy="38" rx="14" ry="18" fill="#18181B" stroke="#3F3F46" stroke-width="2"/>
              <ellipse cx="92" cy="38" rx="14" ry="18" fill="#18181B" stroke="#3F3F46" stroke-width="2"/>
              <circle cx="48" cy="58" r="4" fill="#2E6AFF"/><circle cx="72" cy="58" r="4" fill="#2E6AFF"/>
              <ellipse cx="60" cy="70" rx="8" ry="5" fill="#3F3F46"/>
            </svg>
            <figcaption class="data">Care notes</figcaption>
          </figure>
          <figure class="landing-pet-card landing-pet-card--cat">
            <svg class="landing-pet-svg" viewBox="0 0 120 100" role="img"><title>Cat</title>
              <path d="M30 78 C30 42 90 42 90 78 Z" fill="#18181B" stroke="#3F3F46" stroke-width="2"/>
              <path d="M38 48 L28 22 L52 42 Z" fill="#18181B" stroke="#3F3F46" stroke-width="2"/>
              <path d="M82 48 L92 22 L68 42 Z" fill="#18181B" stroke="#3F3F46" stroke-width="2"/>
              <circle cx="48" cy="58" r="3.5" fill="#2E6AFF"/><circle cx="72" cy="58" r="3.5" fill="#2E6AFF"/>
              <path d="M60 64 L56 72 L64 72 Z" fill="#3F3F46"/>
            </svg>
            <figcaption class="data">Mood check</figcaption>
          </figure>
        </div>
      </div>
    </section>

    <section class="landing-section landing-how" id="how" aria-labelledby="how-title">
      <div class="landing-section-inner">
        <p class="label">How it works</p>
        <h2 id="how-title" class="landing-section-title">Three taps. Less chaos.</h2>
        <ol class="landing-steps">
          <li class="landing-step">
            <span class="landing-step-num data">01</span>
            <h3 class="landing-step-title">Register</h3>
            <p class="landing-step-copy">Account + pet profiles. Fast.</p>
          </li>
          <li class="landing-step">
            <span class="landing-step-num data">02</span>
            <h3 class="landing-step-title">Talk with SI</h3>
            <p class="landing-step-copy">Ask Sipo. Get straight answers.</p>
          </li>
          <li class="landing-step">
            <span class="landing-step-num data">03</span>
            <h3 class="landing-step-title">Stay on track</h3>
            <p class="landing-step-copy">Reminders + boards on your home screen.</p>
          </li>
        </ol>
      </div>
    </section>

    <section class="landing-section landing-features" id="features" aria-labelledby="features-title">
      <div class="landing-section-inner">
        <p class="label">Features</p>
        <h2 id="features-title" class="landing-section-title">Built for everyday paws</h2>
        <ul class="landing-feature-list">
          <li class="landing-feature">
            <span class="landing-feature-icon" aria-hidden="true">
              <svg width="28" height="28"><use href="#i-heart"/></svg>
            </span>
            <h3 class="landing-feature-title">Care</h3>
            <p class="landing-feature-copy">Profiles + notes SI can actually use.</p>
          </li>
          <li class="landing-feature">
            <span class="landing-feature-icon" aria-hidden="true">
              <svg width="28" height="28"><use href="#i-bell"/></svg>
            </span>
            <h3 class="landing-feature-title">Reminders</h3>
            <p class="landing-feature-copy">Walks, meds, check-ins — visible.</p>
          </li>
          <li class="landing-feature">
            <span class="landing-feature-icon" aria-hidden="true">
              <svg width="28" height="28"><use href="#i-chat"/></svg>
            </span>
            <h3 class="landing-feature-title">Community</h3>
            <p class="landing-feature-copy">Species boards. Tips, not doomscroll.</p>
          </li>
          <li class="landing-feature">
            <span class="landing-feature-icon landing-feature-icon--si" aria-hidden="true">SI</span>
            <h3 class="landing-feature-title">SI</h3>
            <p class="landing-feature-copy">Caring Super Intelligence on tap.</p>
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
          Pets’ details stay private. SI helps — it doesn’t sell your story.
        </h2>
      </div>
    </section>

    <section class="landing-section landing-final-cta" aria-labelledby="final-cta-title">
      <div class="landing-section-inner landing-final-inner">
        <h2 id="final-cta-title" class="landing-section-title">Ready when you are</h2>
        <p class="landing-section-copy">Join siPaw. Start caring with SI.</p>
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
    <div class="landing-foot-inner">
      <div class="landing-foot-brand">
        <a class="landing-foot-logo" href="index.php" aria-label="siPaw home">
          <?php rrp_wordmark_sipaw(['size' => 'sm', 'endorsed' => true]); ?>
        </a>
        <p class="landing-foot-tag">SI pet care companion + community</p>
      </div>
      <div class="landing-foot-col">
        <p class="landing-foot-heading">Product</p>
        <ul class="landing-foot-links">
          <li><a href="#features">Features</a></li>
          <li><a href="#how">How it works</a></li>
          <li><a href="signup.php">Register</a></li>
          <li><a href="login.php">Log in</a></li>
        </ul>
      </div>
      <div class="landing-foot-col">
        <p class="landing-foot-heading">Company</p>
        <ul class="landing-foot-links">
          <li>
            <a class="landing-foot-nakilab" href="https://nakilab.com" target="_blank" rel="noopener noreferrer">NAKILAB</a>
          </li>
          <li><a href="support.php">Contact</a></li>
        </ul>
      </div>
      <div class="landing-foot-col">
        <p class="landing-foot-heading">Legal</p>
        <ul class="landing-foot-links">
          <li><a href="#privacy">Privacy</a></li>
          <li><a href="#terms">Terms</a></li>
        </ul>
      </div>
    </div>
    <div class="landing-foot-bottom">
      <p class="landing-foot-copy">© <?= $year ?> NAKILAB · siPaw</p>
      <p class="landing-foot-meta data">Add to Home Screen for the PWA</p>
    </div>
  </footer>
</div>

<div class="install-banner" id="installBanner" role="dialog" aria-label="Install app">
  <p id="installText">Install siPaw on your phone for faster care reminders.</p>
  <button type="button" class="btn primary" id="installBtn" style="width:auto;padding:0 16px;height:44px">Install</button>
  <button type="button" class="btn outline" id="installDismiss" style="width:auto;padding:0 12px;height:44px" aria-label="Dismiss">Not now</button>
</div>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<script src="<?= rrp_h(rrp_asset('assets/js/landing.js', true)) ?>" defer></script>
<?php
rrp_layout_foot(['push' => false]);
