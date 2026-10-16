<?php
// Nakilab — blog posts: shared by the public blog pages and the MCP server.
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const NK_POST_STATUSES = ['draft', 'scheduled', 'published', 'archived'];
const NK_SITE = 'https://nakilab.com';

// A post is live when it is published, or scheduled for a time that has passed.
function nk_live_sql(): string {
    return "(status = 'published' OR (status = 'scheduled' AND publish_at <= ?))";
}

function nk_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim(substr($s, 0, 90), '-');
    return $s !== '' ? $s : 'post';
}

function nk_unique_slug(string $want, ?int $exceptId = null): string {
    $db = nk_db();
    $base = nk_slugify($want);
    for ($i = 1; ; $i++) {
        $slug = $i === 1 ? $base : "$base-$i";
        $q = $db->prepare('SELECT id FROM posts WHERE slug = ?');
        $q->execute([$slug]);
        $id = $q->fetchColumn();
        if ($id === false || (int) $id === $exceptId) return $slug;
    }
}

function nk_post_url(array $p): string { return NK_SITE . '/blog/' . $p['slug']; }

function nk_is_live(array $p): bool {
    return $p['status'] === 'published' || ($p['status'] === 'scheduled' && $p['publish_at'] !== null && $p['publish_at'] <= nk_now());
}

function nk_live_posts(int $limit = 20, int $offset = 0): array {
    $q = nk_db()->prepare('SELECT * FROM posts WHERE ' . nk_live_sql() . ' ORDER BY COALESCE(publish_at, created_at) DESC, id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset));
    $q->execute([nk_now()]);
    return $q->fetchAll();
}

function nk_live_count(): int {
    $q = nk_db()->prepare('SELECT COUNT(*) FROM posts WHERE ' . nk_live_sql());
    $q->execute([nk_now()]);
    return (int) $q->fetchColumn();
}

function nk_find_post(?int $id = null, ?string $slug = null): ?array {
    $q = $id ? nk_db()->prepare('SELECT * FROM posts WHERE id = ?') : nk_db()->prepare('SELECT * FROM posts WHERE slug = ?');
    $q->execute([$id ?: (string) $slug]);
    $row = $q->fetch();
    return $row ?: null;
}

function nk_reading_minutes(string $md): int { return max(1, (int) round(str_word_count(strip_tags($md)) / 220)); }

function nk_date(?string $utc): string { return $utc ? gmdate('F j, Y', strtotime($utc . ' UTC')) : ''; }

/**
 * Minimal, safe Markdown → HTML. Everything is escaped first; only these are supported:
 * # ## ### headings, paragraphs, - / * lists, 1. lists, > quotes, ``` code blocks, `code`,
 * **bold**, *italic*, [links](https://…), and --- rules.
 */
function nk_markdown(string $md): string {
    $lines = preg_split('/\R/', str_replace("\t", '    ', $md)) ?: [];
    $out = []; $para = []; $list = null; $code = null;
    $flushPara = function () use (&$para, &$out) {
        if ($para) { $out[] = '<p>' . nk_inline(implode(' ', $para)) . '</p>'; $para = []; }
    };
    $closeList = function () use (&$list, &$out) {
        if ($list) { $out[] = "</$list>"; $list = null; }
    };
    foreach ($lines as $line) {
        if ($code !== null) {
            if (preg_match('/^```/', $line)) { $out[] = '<pre><code>' . nk_h(implode("\n", $code)) . '</code></pre>'; $code = null; }
            else $code[] = $line;
            continue;
        }
        if (preg_match('/^```/', $line)) { $flushPara(); $closeList(); $code = []; continue; }
        if (trim($line) === '') { $flushPara(); $closeList(); continue; }
        if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m)) {
            $flushPara(); $closeList();
            $lvl = strlen($m[1]) + 1; // # → h2 (the page already has the h1 title)
            $out[] = "<h$lvl>" . nk_inline($m[2]) . "</h$lvl>";
            continue;
        }
        if (preg_match('/^\s*(-{3,}|\*{3,})\s*$/', $line)) { $flushPara(); $closeList(); $out[] = '<hr />'; continue; }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) { $flushPara(); $closeList(); $out[] = '<blockquote><p>' . nk_inline($m[1]) . '</p></blockquote>'; continue; }
        if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $m) || preg_match('/^\s*\d+[.)]\s+(.+)$/', $line, $n)) {
            $flushPara();
            $type = isset($n) && $n ? 'ol' : 'ul';
            if ($list !== $type) { $closeList(); $out[] = "<$type>"; $list = $type; }
            $out[] = '<li>' . nk_inline(($m[1] ?? null) ?: $n[1]) . '</li>';
            unset($m, $n);
            continue;
        }
        $closeList();
        $para[] = trim($line);
    }
    if ($code !== null) $out[] = '<pre><code>' . nk_h(implode("\n", $code)) . '</code></pre>';
    $flushPara(); $closeList();
    return implode("\n", $out);
}

function nk_inline(string $s): string {
    $s = nk_h($s);
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $s) ?? $s;
    // links: only http(s), site-relative, or #anchors
    $s = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#)[^\s)]+)\)/', function ($m) {
        $ext = str_starts_with($m[2], 'http') && !str_starts_with($m[2], NK_SITE);
        return '<a href="' . $m[2] . '"' . ($ext ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>';
    }, $s) ?? $s;
    return $s;
}
