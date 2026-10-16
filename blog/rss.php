<?php
// Nakilab blog — RSS feed at /blog/rss.xml
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/posts.php';

header('Content-Type: application/rss+xml; charset=utf-8');
try { $posts = nk_live_posts(30); } catch (Throwable $e) { $posts = []; }
$x = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>Nakilab Blog</title>
  <link><?= NK_SITE ?>/blog/</link>
  <atom:link href="<?= NK_SITE ?>/blog/rss.xml" rel="self" type="application/rss+xml" />
  <description>Notes from Nakilab on turning ideas into companies with SI — Super Intelligence.</description>
  <language>en</language>
<?php foreach ($posts as $p): $d = $p['publish_at'] ?: $p['created_at']; ?>
  <item>
    <title><?= $x($p['title']) ?></title>
    <link><?= $x(nk_post_url($p)) ?></link>
    <guid isPermaLink="true"><?= $x(nk_post_url($p)) ?></guid>
    <pubDate><?= gmdate(DATE_RSS, strtotime($d . ' UTC')) ?></pubDate>
    <description><?= $x($p['excerpt']) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
