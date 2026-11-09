<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/marketing_chrome.php';

$owner = rrp_config_ready() ? rrp_current_owner() : null;
$year = (int) date('Y');
$post = null;
$preview = false;

// An admin can look at a draft first: blog-post.php?preview=<id> (needs the admin to be signed in).
if (isset($_GET['preview'])) {
    $admin = rrp_current_admin();
    $post = $admin ? rrp_blog_by_id((int) $_GET['preview']) : null;
    $preview = $post !== null;
} else {
    $slug = $_GET['slug'] ?? '';
    if (is_string($slug) && preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $post = rrp_blog_by_slug($slug);
    }
}

if ($post === null) {
    http_response_code(404);
    rrp_marketing_head('Not found — SIPAW', '', ['noindex' => true]);
    rrp_marketing_nav((bool) $owner, 'blog');
    echo '<main id="main"><header class="page-head"><div class="wrap"><p class="label">Blog</p><h1>Post not found</h1><p><a class="back" href="' . rrp_h(rrp_asset('blog')) . '">← Back to blog</a></p></div></header></main>';
    rrp_marketing_footer($year);
    exit;
}

$url = rrp_blog_url((string) $post['slug']);
$img = !empty($post['cover']) ? rrp_app_url((string) $post['cover']) : null;
$pubIso = gmdate('c', (int) strtotime((string) ($post['published_at'] ?: $post['created_at']) . ' UTC'));
$ld = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'], 'description' => $post['excerpt'],
    'datePublished' => $pubIso, 'dateModified' => gmdate('c', (int) strtotime((string) ($post['updated_at'] ?? $post['created_at']) . ' UTC')),
    'author' => ['@type' => 'Organization', 'name' => $post['author']], 'mainEntityOfPage' => $url,
    'publisher' => ['@type' => 'Organization', 'name' => 'NAKILAB', 'url' => 'https://nakilab.com']];
if ($img) {
    $ld['image'] = [$img];
}

rrp_marketing_head($post['title'] . ' — SIPAW', (string) $post['excerpt'], array_filter([
    'canonical' => $url, 'og_type' => 'article', 'og_image' => $img, 'published' => $pubIso, 'json_ld' => $ld, 'noindex' => $preview ?: null,
]));
rrp_marketing_nav((bool) $owner, 'blog');
?>
<main id="main">
  <article class="wrap blog-post">
<?php if ($preview): ?>
    <p class="blog-preview">Preview. <?= $post['status'] === 'published' ? 'This post is published.' : 'This post is a draft and is not on the site yet.' ?></p>
<?php endif; ?>
    <p class="data"><?= rrp_h(rrp_blog_format_date((string) $post['date'])) ?> · <?= rrp_h((string) $post['author']) ?></p>
    <h1><?= rrp_h((string) $post['title']) ?></h1>
<?php if ($img): ?>
    <figure class="blog-hero"><img src="<?= rrp_h(rrp_asset((string) $post['cover'])) ?>" alt="<?= rrp_h((string) ($post['cover_alt'] ?? '')) ?>" width="1600" height="900"></figure>
<?php endif; ?>
    <div class="body">
      <?= rrp_blog_render((string) $post['body']) ?>
    </div>
    <a class="back" href="<?= rrp_h(rrp_asset('blog')) ?>">← Back to blog</a>
  </article>
</main>
<?php rrp_marketing_footer($year); ?>
