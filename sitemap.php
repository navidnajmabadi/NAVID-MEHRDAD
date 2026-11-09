<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

/** sitemap.xml: the public pages and every published blog post, for search engines and assistants. */
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$posts = rrp_blog_posts(200);
$latest = $posts ? max(array_map(static fn($p) => (string) $p['date'], $posts)) : null;
$urls = [
    [rrp_app_url(), $latest, 'weekly', '1.0'],
    [rrp_app_url('blog'), $latest, 'weekly', '0.8'],
    [rrp_app_url('signup.php'), null, 'monthly', '0.5'],
    [rrp_app_url('legal.php?doc=terms'), null, 'yearly', '0.2'],
    [rrp_app_url('legal.php?doc=privacy'), null, 'yearly', '0.2'],
];
foreach ($posts as $p) {
    $mod = substr((string) ($p['updated_at'] ?? $p['published_at'] ?? $p['date']), 0, 10);
    $urls[] = [rrp_blog_url((string) $p['slug']), $mod, 'monthly', '0.7'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $freq, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
        . ($mod ? '<lastmod>' . htmlspecialchars((string) $mod, ENT_XML1) . '</lastmod>' : '')
        . '<changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
