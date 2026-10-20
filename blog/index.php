<?php
// Nakilab blog — list of live posts, newest first.
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';

$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
try {
    $total = nk_live_count();
    $posts = nk_live_posts($perPage, ($page - 1) * $perPage);
} catch (Throwable $e) {
    error_log('blog: ' . $e->getMessage());
    $total = 0; $posts = [];
}
$pages = max(1, (int) ceil($total / $perPage));

nk_page_start('Blog | Nakilab', 'Notes from Nakilab on turning ideas into companies, building with SI, Super Intelligence, and launching products.', NK_SITE . '/blog/' . ($page > 1 ? "?page=$page" : ''));
?>
<section class="blog-head">
  <div class="wrap">
    <div class="label">Blog</div>
    <h1>Notes from the lab</h1>
    <p class="sub">Ideas, launches and lessons from building companies with SI, Super Intelligence.</p>
  </div>
</section>

<section class="blog-list">
  <div class="wrap">
    <?php if (!$posts): ?>
      <div class="blog-empty">
        <h2>First post coming soon</h2>
        <p>Have an idea worth building in the meantime?</p>
        <a class="btn" href="/#intake">Submit your idea</a>
      </div>
    <?php else: ?>
      <div class="post-grid">
        <?php foreach ($posts as $i => $p): ?>
          <a class="post-card<?= $i === 0 && $page === 1 ? ' featured' : '' ?>" href="/blog/<?= nk_h($p['slug']) ?>">
            <div class="meta"><time datetime="<?= nk_h(str_replace(' ', 'T', (string) ($p['publish_at'] ?: $p['created_at']))) ?>Z"><?= nk_h(nk_date($p['publish_at'] ?: $p['created_at'])) ?></time> · <?= nk_reading_minutes($p['body']) ?> min read</div>
            <h2><?= nk_h($p['title']) ?></h2>
            <?php if ($p['excerpt'] !== ''): ?><p><?= nk_h($p['excerpt']) ?></p><?php endif; ?>
            <span class="more">Read
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Blog pages">
          <?php if ($page > 1): ?><a class="btn small" href="/blog/<?= $page > 2 ? '?page=' . ($page - 1) : '' ?>">Newer</a><?php endif; ?>
          <span>Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?><a class="btn small" href="/blog/?page=<?= $page + 1 ?>">Older</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php nk_page_end();
