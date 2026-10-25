<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/marketing_chrome.php';

$owner = rrp_config_ready() ? rrp_current_owner() : null;
$year = (int) date('Y');
$posts = rrp_blog_posts();

rrp_marketing_head('Blog — SIPAW', 'News and notes from SIPAW and SIPO.');
rrp_marketing_nav((bool) $owner, 'blog');
?>
<main id="main">
  <header class="page-head">
    <div class="wrap">
      <p class="label">Blog</p>
      <h1>Notes from SIPAW</h1>
      <p>Short updates on SIPO, care reminders, and owner boards. Placeholder posts while we ship.</p>
    </div>
  </header>
  <div class="wrap blog-list">
<?php foreach ($posts as $post): ?>
    <a class="blog-card" href="<?= rrp_h(rrp_asset('blog-post.php?slug=' . rawurlencode($post['slug']))) ?>">
      <p class="data"><?= rrp_h(rrp_blog_format_date($post['date'])) ?></p>
      <h2><?= rrp_h($post['title']) ?></h2>
      <p><?= rrp_h($post['excerpt']) ?></p>
    </a>
<?php endforeach; ?>
  </div>
</main>
<?php rrp_marketing_footer($year); ?>
