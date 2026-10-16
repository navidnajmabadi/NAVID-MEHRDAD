<?php
// Nakilab — sitemap at /sitemap.xml (home page + every live blog post).
declare(strict_types=1);
require_once __DIR__ . '/lib/posts.php';

header('Content-Type: application/xml; charset=utf-8');
try { $posts = nk_live_posts(1000); } catch (Throwable $e) { $posts = []; }
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= NK_SITE ?>/</loc></url>
  <url><loc><?= NK_SITE ?>/blog/</loc></url>
<?php foreach ($posts as $p): ?>
  <url><loc><?= htmlspecialchars(nk_post_url($p), ENT_XML1) ?></loc><lastmod><?= gmdate('Y-m-d', strtotime($p['updated_at'] . ' UTC')) ?></lastmod></url>
<?php endforeach; ?>
</urlset>
