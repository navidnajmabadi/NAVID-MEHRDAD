<?php
declare(strict_types=1);

/**
 * Minimal shared chrome for public marketing pages (blog).
 * Landing (index.php) stays self-contained; this mirrors its nav/footer rules.
 */

function rrp_marketing_head(string $title, string $description = ''): void
{
    $canonical = rrp_config_ready() ? rrp_app_url() : 'https://sipaw.nakilab.com/';
    $desc = $description !== '' ? $description : 'siPaw by Nakilab. Chat with SIPO, get reminders on time, and ask other owners.';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= rrp_h($title) ?></title>
<meta name="description" content="<?= rrp_h($desc) ?>">
<meta property="og:title" content="<?= rrp_h($title) ?>">
<meta property="og:site_name" content="SIPAW">
<meta name="theme-color" content="#000000">
<link rel="canonical" href="<?= rrp_h($canonical) ?>">
<link rel="manifest" href="<?= rrp_h(rrp_asset('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" href="<?= rrp_h(rrp_asset('assets/icons/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= rrp_h(rrp_asset('assets/icons/apple-touch-icon.png')) ?>">
<meta name="apple-mobile-web-app-title" content="SIPAW">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/marketing-site.css', true)) ?>">
</head>
<body>
<?php
}

function rrp_marketing_nav(bool $owner, string $active = ''): void
{
    $home = rrp_asset('index.php');
    ?>
<nav class="nav" aria-label="Main">
  <div class="wrap">
    <a href="<?= rrp_h($home) ?>" aria-label="siPaw by Nakilab home">
      <img class="logo-endorsed" src="<?= rrp_h(rrp_asset('assets/img/logos/sipaw-wordmark-endorsed-on-black.svg')) ?>" width="118" height="87" alt="siPaw by Nakilab">
    </a>
    <ul>
      <li><a href="<?= rrp_h($home) ?>#features">What SIPO does</a></li>
      <li><a href="<?= rrp_h($home) ?>#demo">Demo</a></li>
      <li><a href="<?= rrp_h(rrp_asset('blog.php')) ?>"<?= $active === 'blog' ? ' aria-current="page"' : '' ?>>Blog</a></li>
    </ul>
    <div class="acts">
<?php if ($owner): ?>
      <a class="btn primary" href="<?= rrp_h(rrp_asset('dashboard.php')) ?>">Open app</a>
<?php else: ?>
      <a class="btn outline" href="<?= rrp_h(rrp_asset('login.php')) ?>">Log in</a>
      <a class="btn primary" href="<?= rrp_h(rrp_asset('signup.php')) ?>">Register</a>
<?php endif; ?>
    </div>
  </div>
</nav>
<?php
}

function rrp_marketing_footer(int $year): void
{
    $home = rrp_asset('index.php');
    $tile = rrp_asset('assets/img/logos/sipaw-app-icon-1024.svg', true);
    ?>
<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <a class="foot-tile" href="<?= rrp_h($home) ?>" aria-label="SIPAW home">
          <img src="<?= rrp_h($tile) ?>" width="160" height="160" alt="SI^PAW element tile">
        </a>
      </div>
      <div class="foot-col">
        <p class="foot-h">Product</p>
        <a href="<?= rrp_h($home) ?>#features">What SIPO does</a>
        <a href="<?= rrp_h($home) ?>#demo">Demo</a>
        <a href="<?= rrp_h($home) ?>#pets">Features</a>
        <a href="<?= rrp_h(rrp_asset('signup.php')) ?>">Register</a>
      </div>
      <div class="foot-col">
        <p class="foot-h">Company</p>
        <a href="https://nakilab.com" target="_blank" rel="noopener noreferrer">NAKILAB</a>
        <a href="https://nakilab.com" target="_blank" rel="noopener noreferrer">Contact</a>
      </div>
      <div class="foot-col">
        <p class="foot-h">Social</p>
        <div class="social" aria-label="Social links">
          <a href="#" aria-label="X (Twitter)" title="X (Twitter)">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter"><path d="M4 4l16 16M20 4L4 20"/></svg>
          </a>
          <a href="#" aria-label="LinkedIn" title="LinkedIn">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter"><rect x="3" y="3" width="18" height="18"/><path d="M8 11v6M8 8v.01M12 17v-4.5a2.5 2.5 0 015 0V17"/></svg>
          </a>
          <a href="#" aria-label="Facebook" title="Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.03H7.9v-2.9h2.4V9.85c0-2.37 1.4-3.68 3.56-3.68 1.03 0 2.11.18 2.11.18v2.32h-1.19c-1.17 0-1.54.73-1.54 1.48v1.78h2.62l-.42 2.9h-2.2V22c4.78-.75 8.44-4.91 8.44-9.93z"/></svg>
          </a>
          <a href="#" aria-label="Instagram" title="Instagram">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 01-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 017.8 2zm-.2 2A3.6 3.6 0 004 7.6v8.8A3.6 3.6 0 007.6 20h8.8a3.6 3.6 0 003.6-3.6V7.6A3.6 3.6 0 0016.4 4H7.6zm9.65 1.5a1.25 1.25 0 110 2.5 1.25 1.25 0 010-2.5zM12 7a5 5 0 110 10 5 5 0 010-10zm0 2a3 3 0 100 6 3 3 0 000-6z"/></svg>
          </a>
        </div>
      </div>
      <div class="foot-col">
        <p class="foot-h">Blog</p>
        <a href="<?= rrp_h(rrp_asset('blog.php')) ?>">Blog</a>
      </div>
    </div>
    <div class="foot-bar">
      <p>© <?= (int) $year ?> NAKILAB · siPaw</p>
      <p>Add to Home Screen for the PWA</p>
    </div>
  </div>
</footer>
</body>
</html>
<?php
}
