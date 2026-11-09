<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

/**
 * llms.txt: a plain-language guide to the public site for AI assistants (llmstxt.org format).
 * llms-full.txt (?full=1): the same plus the full text of every published blog post.
 * Public information only: nothing about any owner, pet or account.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex'); // it is for assistants, not for search results

$full = isset($_GET['full']);
$base = rtrim(rrp_app_url(), '/');
$posts = rrp_blog_posts(200);

echo "# SIPAW\n\n";
echo "> SIPAW (SI PAW, by NAKILAB) is a pet-care companion for dog and cat owners. Its chat companion, SIPO, remembers each pet, reminds owners before anything is due, "
    . "reads lab reports and can help find a lost pet. English only. Made in Canada by NAKILAB.\n\n";
echo "## What it does\n\n";
echo "- **SIPO, the companion:** one chat per pet. SIPO asks one short question at a time, remembers the answers and speaks first at the owner's usual times (never between 21:00 and 09:00 local time).\n";
echo "- **Health:** vaccines, parasite control, treatments, weight, food and calories, lab results read from photos or PDFs, and a read-only link to share with the vet.\n";
echo "- **Reminders:** vaccines, medicines, meals, walks and check-ups, with follow-ups until done.\n";
echo "- **Daily life:** walks with a GPS map, bathroom and wellness check-ins, spending per pet.\n";
echo "- **Lost pet help:** one tap alerts nearby SIPAW owners and escalates on a schedule.\n";
echo "- **Rewards:** paw coins for caring actions, and discounts for consistent care.\n";
echo "- **Plans:** a free 7-day trial, then a Plus subscription billed monthly in Canadian dollars. It can be cancelled any time in Billing.\n\n";
echo "SIPO is a companion, not a vet. It never diagnoses and never gives medicine doses; it points owners to their veterinarian and flags emergencies.\n\n";
echo "## Pages\n\n";
echo "- [Home]($base/): what SIPAW is and how it works\n";
echo "- [Blog]($base/blog): notes and updates from the SIPAW team\n";
echo "- [Sign up]($base/signup.php): create an account\n";
echo "- [Terms of Service]($base/legal.php?doc=terms)\n";
echo "- [Privacy Policy]($base/legal.php?doc=privacy)\n\n";
if ($posts) {
    echo "## Blog posts\n\n";
    foreach ($posts as $p) {
        echo '- [' . str_replace(['[', ']'], '', (string) $p['title']) . '](' . rrp_blog_url((string) $p['slug']) . '): ' . trim((string) $p['excerpt']) . ' (' . $p['date'] . ")\n";
    }
    echo "\n";
}
echo "## About\n\n";
echo "- Publisher: NAKILAB, https://nakilab.com\n";
echo "- Sitemap: $base/sitemap.xml\n";
echo "- Crawling rules: $base/robots.txt (public pages may be read; signed-in screens and pet pages are private)\n";
echo "- The full text of the blog: $base/llms-full.txt\n";
if ($full && $posts) {
    echo "\n---\n\n# SIPAW blog, full text\n";
    foreach ($posts as $p) {
        echo "\n## " . $p['title'] . "\n\n";
        echo 'URL: ' . rrp_blog_url((string) $p['slug']) . "\nPublished: " . $p['date'] . ' by ' . $p['author'] . "\n\n";
        echo trim((string) $p['body']) . "\n";
    }
}
