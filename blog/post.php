<?php
// Nakilab blog — a single post at /blog/{slug}.
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';

$slug = (string) ($_GET['slug'] ?? '');
$post = null;
if (preg_match('/^[a-z0-9-]{1,160}$/', $slug)) {
    try { $post = nk_find_post(null, $slug); } catch (Throwable $e) { error_log('blog: ' . $e->getMessage()); }
}
if (!$post || !nk_is_live($post)) {
    http_response_code(404);
    nk_page_start('Not found | Nakilab', 'This post does not exist.', NK_SITE . '/blog/');
    echo '<section class="blog-head"><div class="wrap"><div class="label">404</div><h1>Post not found</h1><div class="btns" style="margin-top:32px"><a class="btn" href="/blog/">All posts</a></div></div></section>';
    nk_page_end();
    exit;
}

$date = $post['publish_at'] ?: $post['created_at'];
$iso = str_replace(' ', 'T', (string) $date) . 'Z';
$desc = $post['excerpt'] !== '' ? $post['excerpt'] : mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags(nk_markdown($post['body'])))), 0, 160);
$ld = [
    '@context' => 'https://schema.org', '@type' => 'BlogPosting',
    'headline' => $post['title'], 'description' => $desc, 'datePublished' => $iso,
    'dateModified' => str_replace(' ', 'T', (string) $post['updated_at']) . 'Z',
    'author' => ['@type' => 'Organization', 'name' => $post['author'] ?: 'Nakilab'],
    'publisher' => ['@type' => 'Organization', 'name' => 'Nakilab', 'logo' => ['@type' => 'ImageObject', 'url' => NK_SITE . '/assets/nakilab-logo.png']],
    'mainEntityOfPage' => nk_post_url($post),
];
$extra = '<meta property="og:type" content="article" /><script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';

nk_page_start($post['title'] . ' | Nakilab', $desc, nk_post_url($post), $extra);
?>
<article class="post">
  <header class="post-head">
    <div class="wrap narrow">
      <a class="label back" href="/blog/">← Blog</a>
      <h1><?= nk_h($post['title']) ?></h1>
      <div class="meta"><time datetime="<?= nk_h($iso) ?>"><?= nk_h(nk_date($date)) ?></time> · <?= nk_reading_minutes($post['body']) ?> min read · <?= nk_h($post['author'] ?: 'Nakilab') ?></div>
    </div>
  </header>
  <div class="wrap narrow prose">
    <?= nk_markdown($post['body']) ?>
  </div>
  <?php if ($post['tags'] !== ''): ?>
    <div class="wrap narrow tags"><?php foreach (array_filter(array_map('trim', explode(',', $post['tags']))) as $t): ?><span><?= nk_h($t) ?></span><?php endforeach; ?></div>
  <?php endif; ?>
  <aside class="wrap narrow post-cta">
    <div>
      <div class="label">Got an idea?</div>
      <h2>We turn ideas into companies</h2>
    </div>
    <a class="btn solid" href="/#intake">Submit your idea
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
  </aside>
</article>
<?php nk_page_end();
