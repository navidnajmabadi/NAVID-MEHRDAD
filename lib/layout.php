<?php
// Nakilab — shared page chrome (head, nav, footer) for server-rendered pages like the blog.
declare(strict_types=1);
require_once __DIR__ . '/posts.php';

function nk_page_start(string $title, string $description, string $canonical, string $extraHead = ''): void {
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title><?= nk_h($title) ?></title>
<meta name="description" content="<?= nk_h($description) ?>" />
<link rel="canonical" href="<?= nk_h($canonical) ?>" />
<meta property="og:title" content="<?= nk_h($title) ?>" />
<meta property="og:description" content="<?= nk_h($description) ?>" />
<meta property="og:url" content="<?= nk_h($canonical) ?>" />
<meta property="og:image" content="<?= NK_SITE ?>/assets/nakilab-logo.png" />
<meta name="theme-color" content="#000000" />
<meta name="color-scheme" content="dark" />
<link rel="icon" type="image/png" href="/assets/favicon.png" />
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png" />
<link rel="alternate" type="application/rss+xml" title="Nakilab Blog" href="/blog/rss.xml" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/assets/studio.css?v=20261025" />
<?= $extraHead ?>
</head>
<body class="page-blog">
<a class="skip" href="#main">Skip to content</a>
<nav class="nav scrolled" aria-label="Main">
  <div class="wrap">
    <a href="/" aria-label="Nakilab home"><img src="/assets/nakilab-logo-white.png" alt="Nakilab" width="250" height="47" /></a>
    <ul>
      <li><a href="/#how">How it works</a></li>
      <li><a href="/#projects">Projects</a></li>
      <li><a href="/blog/" aria-current="page">Blog</a></li>
      <li><a href="/#investors">Investors</a></li>
    </ul>
    <a class="btn small" href="/#intake">Submit idea</a>
  </div>
</nav>
<main id="main">
<?php
}

function nk_page_end(): void {
    ?>
</main>
<footer>
  <div class="wrap">
    <img src="/assets/nakilab-logo-white.png" alt="Nakilab" width="250" height="47" />
    <ul>
      <li><a href="/#how">How it works</a></li>
      <li><a href="/#projects">Projects</a></li>
      <li><a href="/blog/">Blog</a></li>
      <li><a href="/#intake">Submit idea</a></li>
    </ul>
    <span>© <?= gmdate('Y') ?> Nakilab</span>
  </div>
</footer>
</body>
</html>
<?php
}
