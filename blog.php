<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/marketing_chrome.php';

$owner = rrp_config_ready() ? rrp_current_owner() : null;
$year = (int) date('Y');
$posts = rrp_blog_posts();

rrp_marketing_head('Blog — SIPAW', 'News and notes from SIPAW and SIPO: pet care, reminders and Super Intelligence for pet owners.', [
    'canonical' => rrp_app_url('blog'),
    'json_ld' => ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => 'SIPAW blog', 'url' => rrp_app_url('blog'),
        'publisher' => ['@type' => 'Organization', 'name' => 'NAKILAB', 'url' => 'https://nakilab.com'],
        'blogPost' => array_map(static fn($p) => ['@type' => 'BlogPosting', 'headline' => $p['title'], 'url' => rrp_blog_url((string) $p['slug']), 'datePublished' => $p['date']], $posts)],
]);
rrp_marketing_nav((bool) $owner, 'blog');
?>
<main id="main">
  <header class="page-head">
    <div class="wrap">
      <p class="label">Blog</p>
      <h1>Notes from SIPAW</h1>
      <p>Short updates on SIPO, care reminders, and caring for your pet.</p>
    </div>
  </header>
  <div class="wrap blog-list">
<?php if (!$posts): ?>
    <p class="blog-empty">No posts yet. Check back soon.</p>
<?php endif; ?>
<?php foreach ($posts as $post): ?>
    <a class="blog-card" href="<?= rrp_h(rrp_asset('blog/' . $post['slug'])) ?>">
<?php if (!empty($post['cover'])): ?>
      <img class="blog-cover" src="<?= rrp_h(rrp_asset((string) $post['cover'])) ?>" alt="<?= rrp_h((string) ($post['cover_alt'] ?? '')) ?>" loading="lazy" width="1600" height="900">
<?php endif; ?>
      <p class="data"><?= rrp_h(rrp_blog_format_date((string) $post['date'])) ?></p>
      <h2><?= rrp_h((string) $post['title']) ?></h2>
      <p><?= rrp_h((string) $post['excerpt']) ?></p>
    </a>
<?php endforeach; ?>
  </div>
</main>
<?php rrp_marketing_footer($year); ?>
