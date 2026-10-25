<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/marketing_chrome.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(404);
    $slug = '';
}

$post = $slug !== '' ? rrp_blog_by_slug($slug) : null;
if ($post === null) {
    http_response_code(404);
    rrp_marketing_head('Not found — SIPAW');
    $owner = rrp_config_ready() ? rrp_current_owner() : null;
    rrp_marketing_nav((bool) $owner, 'blog');
    echo '<main id="main"><header class="page-head"><div class="wrap"><p class="label">Blog</p><h1>Post not found</h1><p><a class="back" href="' . rrp_h(rrp_asset('blog.php')) . '">← Back to blog</a></p></div></header></main>';
    rrp_marketing_footer((int) date('Y'));
    exit;
}

$owner = rrp_config_ready() ? rrp_current_owner() : null;
$year = (int) date('Y');

rrp_marketing_head($post['title'] . ' — SIPAW', $post['excerpt']);
rrp_marketing_nav((bool) $owner, 'blog');
?>
<main id="main">
  <article class="wrap blog-post">
    <p class="data"><?= rrp_h(rrp_blog_format_date($post['date'])) ?></p>
    <h1><?= rrp_h($post['title']) ?></h1>
    <div class="body">
      <?= $post['body'] ?>
    </div>
    <a class="back" href="<?= rrp_h(rrp_asset('blog.php')) ?>">← Back to blog</a>
  </article>
</main>
<?php rrp_marketing_footer($year); ?>
