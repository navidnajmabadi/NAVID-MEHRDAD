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

/**
 * HTML/CSS SI mark + SIPAW wordmark (never “Si”).
 */
function rrp_landing_si_mark(string $class = '', bool $showWord = true, bool $showBy = false): void
{
    $cls = trim('si-mark ' . $class);
    echo '<span class="' . rrp_h($cls) . '" aria-hidden="true">';
    echo '<span class="si-mark__badge">SI</span>';
    if ($showWord) {
        echo '<span class="si-mark__word"><span class="si-mark__sipaw">SIPAW</span>';
        if ($showBy) {
            echo '<span class="si-mark__by">BY NAKILAB</span>';
        }
        echo '</span>';
    }
    echo '</span>';
}

rrp_layout_head('siPaw — AI pet care companion', [
    'body_class' => 'landing',
    'canonical' => rrp_config_ready() ? rrp_app_url() : 'https://sipaw.nakilab.com/',
    'description' => 'siPaw by NAKILAB — caring SI chat, health reminders, and a pet-owner community in one PWA.',
    'extra_head' => '<script>window.SIPO_ASSET_BASE=' . json_encode($sipoBase) . ';</script>',
]);
?>
<div class="landing-page">
  <header class="landing-top">
    <a class="landing-brand" href="index.php" aria-label="siPaw home">
      <?php rrp_landing_si_mark('landing-nav-mark', true, false); ?>
      <span class="visually-hidden">siPaw</span>
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
      <div class="landing-hero-stage">
        <div class="landing-hero-glow" aria-hidden="true"></div>

        <div class="landing-hero-brand">
          <?php rrp_landing_si_mark('landing-hero-mark', true, true); ?>
        </div>

        <h1 id="landing-headline" class="landing-headline">Your pet’s SI wingman</h1>
        <p class="landing-lede">
          Chat care, reminders, and owner vibes — one PWA, zero metal-tag energy.
        </p>

        <div class="cta-group landing-hero-ctas">
          <?php if ($owner): ?>
            <a class="btn primary" href="dashboard.php">Open home</a>
            <a class="btn outline" href="chat.php">Talk to SI</a>
          <?php else: ?>
            <a class="btn primary" href="signup.php">Register</a>
            <a class="btn outline" href="login.php">Log in</a>
          <?php endif; ?>
        </div>

        <div class="landing-sipo-stage" id="landingSipoStage">
          <div class="landing-sipo-dialog" aria-live="polite">
            <p class="landing-bubble" id="landingBubble" data-line="0">
              Hi I’m SIPO — your Pet Assistant. Ask me anything. I’ll roast your schedule gently.
            </p>
          </div>
          <div class="landing-sipo-focal">
            <sipo-mascot
              id="landingSipo"
              state="happy"
              theme="ice-blue"
              size="280"
              animated="true"
              label="Sipo, SI pet assistant"
            ></sipo-mascot>
          </div>
          <div class="landing-sipo-controls">
            <button type="button" class="landing-sipo-pause data" id="landingSipoPause" aria-pressed="false">
              Pause chat
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="landing-section landing-how" id="how" aria-labelledby="how-title">
      <div class="landing-section-inner">
        <p class="label">How it works</p>
        <h2 id="how-title" class="landing-section-title">Three taps. Less panic.</h2>
        <ol class="landing-steps">
          <li class="landing-step">
            <span class="landing-step-num data">01</span>
            <h3 class="landing-step-title">Register</h3>
            <p class="landing-step-copy">Drop in. Add your crew. Free = 1 pet energy.</p>
          </li>
          <li class="landing-step">
            <span class="landing-step-num data">02</span>
            <h3 class="landing-step-title">Talk SI</h3>
            <p class="landing-step-copy">Health, vibes, “is this normal?” — Sipo’s on it.</p>
          </li>
          <li class="landing-step">
            <span class="landing-step-num data">03</span>
            <h3 class="landing-step-title">Stay sharp</h3>
            <p class="landing-step-copy">Reminders + boards. Home-screen PWA flex.</p>
          </li>
        </ol>
      </div>
    </section>

    <section class="landing-section landing-features" id="features" aria-labelledby="features-title">
      <div class="landing-section-inner">
        <p class="label">Features</p>
        <h2 id="features-title" class="landing-section-title">Software with pets inside</h2>
        <p class="landing-section-copy landing-features-lede">
          Short, funny, useful — not a policy PDF.
        </p>

        <ul class="landing-mock-grid">
          <li class="landing-mock">
            <div class="landing-mock-chrome" aria-hidden="true">
              <span class="landing-mock-dot"></span><span class="landing-mock-dot"></span><span class="landing-mock-dot"></span>
              <span class="landing-mock-title data">care.app</span>
            </div>
            <div class="landing-mock-body">
              <div class="landing-mock-icon" aria-hidden="true">
                <svg width="28" height="28"><use href="#i-heart"/></svg>
              </div>
              <div class="landing-mock-pet" aria-hidden="true">
                <svg viewBox="0 0 200 200" width="72" height="72"><use href="#pet-dog"/></svg>
              </div>
              <h3 class="landing-mock-heading">Care dossiers</h3>
              <p class="landing-mock-copy">Profiles SI actually reads — so you don’t retype “Milo hates baths” every chat.</p>
            </div>
          </li>
          <li class="landing-mock">
            <div class="landing-mock-chrome" aria-hidden="true">
              <span class="landing-mock-dot"></span><span class="landing-mock-dot"></span><span class="landing-mock-dot"></span>
              <span class="landing-mock-title data">ping.app</span>
            </div>
            <div class="landing-mock-body">
              <div class="landing-mock-icon" aria-hidden="true">
                <svg width="28" height="28"><use href="#i-clock"/></svg>
              </div>
              <div class="landing-mock-pet" aria-hidden="true">
                <svg viewBox="0 0 200 200" width="72" height="72"><use href="#pet-cat"/></svg>
              </div>
              <h3 class="landing-mock-heading">Nudge mode</h3>
              <p class="landing-mock-copy">Walks, meds, check-ins. Your cat judges; we just remind.</p>
            </div>
          </li>
          <li class="landing-mock">
            <div class="landing-mock-chrome" aria-hidden="true">
              <span class="landing-mock-dot"></span><span class="landing-mock-dot"></span><span class="landing-mock-dot"></span>
              <span class="landing-mock-title data">chat.si</span>
            </div>
            <div class="landing-mock-body">
              <div class="landing-mock-icon" aria-hidden="true">
                <svg width="28" height="28"><use href="#i-chat"/></svg>
              </div>
              <div class="landing-mock-pet landing-mock-pet--sipo" aria-hidden="true">
                <sipo-mascot state="writing" theme="ice-blue" size="64" animated="true" label=""></sipo-mascot>
              </div>
              <h3 class="landing-mock-heading">SI on tap</h3>
              <p class="landing-mock-copy">Fact-minded Super Intelligence. Cares hard. Sells nothing.</p>
            </div>
          </li>
          <li class="landing-mock">
            <div class="landing-mock-chrome" aria-hidden="true">
              <span class="landing-mock-dot"></span><span class="landing-mock-dot"></span><span class="landing-mock-dot"></span>
              <span class="landing-mock-title data">boards.app</span>
            </div>
            <div class="landing-mock-body">
              <div class="landing-mock-icon" aria-hidden="true">
                <svg width="28" height="28"><use href="#i-pin"/></svg>
              </div>
              <div class="landing-mock-pet" aria-hidden="true">
                <svg viewBox="0 0 200 200" width="64" height="64"><use href="#pet-dog"/></svg>
                <svg viewBox="0 0 200 200" width="48" height="48" class="landing-mock-pet-overlap"><use href="#pet-cat"/></svg>
              </div>
              <h3 class="landing-mock-heading">Owner boards</h3>
              <p class="landing-mock-copy">Species tips, not doomscroll. Social lite for people with lint rollers.</p>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <section class="landing-demo" id="demo" aria-labelledby="demo-title">
      <div class="landing-demo-inner">
        <p class="label">See it</p>
        <h2 id="demo-title" class="landing-demo-title">Sipo in motion</h2>
        <p class="landing-demo-sub">
          Demo placeholder — drop your cut in when ready.
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
    <div class="landing-foot-grid">
      <div class="landing-foot-brand">
        <?php rrp_landing_si_mark('landing-foot-mark', true, true); ?>
        <p class="landing-foot-tag">SI pet care companion + community</p>
      </div>
      <div class="landing-foot-col">
        <p class="landing-foot-heading data">Product</p>
        <a href="#features">Features</a>
        <a href="#how">How it works</a>
        <a href="signup.php">Register</a>
      </div>
      <div class="landing-foot-col">
        <p class="landing-foot-heading data">Company</p>
        <a href="https://nakilab.com" target="_blank" rel="noopener noreferrer">NAKILAB</a>
        <a href="https://nakilab.com" target="_blank" rel="noopener noreferrer">Contact</a>
      </div>
      <div class="landing-foot-col">
        <p class="landing-foot-heading data">Legal</p>
        <a href="#privacy">Privacy</a>
        <a href="#terms">Terms</a>
      </div>
    </div>
    <div class="landing-foot-bar">
      <p>© <?= (int) date('Y') ?> NAKILAB · siPaw</p>
      <p>Add to Home Screen for the PWA</p>
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
