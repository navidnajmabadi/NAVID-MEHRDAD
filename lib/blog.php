<?php
declare(strict_types=1);

/**
 * The public SIPAW blog. Posts live in `blog_posts` (migration 030) and are written in Admin → Blog.
 * The text is stored as simple "markdown-lite" (see rrp_blog_render) and turned into safe HTML when shown:
 * raw HTML typed into a post is never output as HTML.
 * Before migration 030 has run, three built-in starter posts are shown so the page is never empty.
 */

/** @return list<array<string,mixed>> */
function rrp_blog_builtin(): array
{
    return [
        ['id' => 0, 'slug' => 'meet-sipo', 'title' => "Meet SIPO, your pet's SI companion", 'date' => '2026-10-20', 'published_at' => '2026-10-20 09:00:00',
            'excerpt' => 'SIPO remembers breed, habits, and health notes so you never retype the same story in every chat.', 'cover' => null, 'cover_alt' => null,
            'author' => 'The SIPAW team', 'status' => 'published',
            'body' => "SIPO is the SI assistant inside SIPAW. Ask about meals, meds, or \"is this normal?\" and get a clear answer grounded in your pet's profile.\n\nReady to try it? Register, add your first pet, and say hello to SIPO."],
    ];
}

/** Add the fields pages use (date as Y-m-d). */
function rrp_blog_shape(array $r): array
{
    $when = (string) ($r['published_at'] ?: $r['created_at'] ?? gmdate('Y-m-d H:i:s'));
    $r['date'] = substr($when, 0, 10);
    $r['id'] = (int) $r['id'];
    return $r;
}

/**
 * Published posts, newest first. Posts dated in the future (scheduled) stay hidden until their time.
 * @return list<array<string,mixed>>
 */
function rrp_blog_posts(int $limit = 50): array
{
    try {
        $st = rrp_db()->prepare("SELECT * FROM blog_posts WHERE status = 'published' AND (published_at IS NULL OR published_at <= UTC_TIMESTAMP())
                                 ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT " . max(1, min(200, $limit)));
        $st->execute();
        return array_map('rrp_blog_shape', $st->fetchAll());
    } catch (Throwable $e) {
        return rrp_blog_builtin(); // table not there yet (migration 030)
    }
}

/** A published post by its web address, or null. @return array<string,mixed>|null */
function rrp_blog_by_slug(string $slug): ?array
{
    try {
        $st = rrp_db()->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' AND (published_at IS NULL OR published_at <= UTC_TIMESTAMP()) LIMIT 1");
        $st->execute([$slug]);
        $r = $st->fetch();
        return $r ? rrp_blog_shape($r) : null;
    } catch (Throwable $e) {
        foreach (rrp_blog_builtin() as $p) {
            if ($p['slug'] === $slug) {
                return $p;
            }
        }
        return null;
    }
}

/** Any post (draft too) for the admin editor and preview. @return array<string,mixed>|null */
function rrp_blog_by_id(int $id): ?array
{
    try {
        $st = rrp_db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ? rrp_blog_shape($r) : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Every post for the admin list (drafts first). @return list<array<string,mixed>> */
function rrp_blog_all(): array
{
    try {
        return array_map('rrp_blog_shape', rrp_db()->query("SELECT * FROM blog_posts ORDER BY (status = 'draft') DESC, COALESCE(published_at, created_at) DESC, id DESC LIMIT 500")->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

function rrp_blog_format_date(string $ymd): string
{
    $ts = strtotime($ymd);
    if ($ts === false) {
        return $ymd;
    }
    return date('j F Y', $ts);
}

/** Web address piece from a title: "Meet SIPO!" → "meet-sipo". */
function rrp_blog_slugify(string $s): string
{
    $s = strtolower((string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s));
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s, 0, 80) ?: 'post';
}

/** The public address of a post. */
function rrp_blog_url(string $slug): string
{
    return rrp_app_url('blog/' . $slug);
}

// ---------------------------------------------------------------------------
// Text → safe HTML ("markdown-lite")
// ---------------------------------------------------------------------------

/** Escape, then turn **bold**, *italic*, `code` and [text](link) into HTML. Links must be https, http, mailto or start with "/". */
function rrp_blog_inline(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $s = preg_replace_callback('/\[([^\]]{1,200})\]\(([^)\s]{1,500})\)/', static function (array $m): string {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^(https?://|mailto:|/)#i', $url) || str_starts_with($url, '//')) {
            return $m[0];
        }
        $ext = preg_match('#^https?://#i', $url) ? ' rel="noopener" target="_blank"' : '';
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $ext . '>' . $m[1] . '</a>';
    }, $s) ?? $s;
    $s = preg_replace('/`([^`\n]{1,200})`/', '<code>$1</code>', $s) ?? $s;
    $s = preg_replace('/\*\*([^*\n]{1,300})\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![\w*])\*([^*\n]{1,300})\*(?![\w*])/', '<em>$1</em>', $s) ?? $s;
    return $s;
}

/**
 * Blocks are separated by a blank line.
 *   ## Heading, ### Smaller heading, - list, 1. numbered list, > quote, --- line, ![alt](https://…/image.jpg) image, otherwise a paragraph.
 */
function rrp_blog_render(string $src): string
{
    $src = str_replace(["\r\n", "\r"], "\n", trim($src));
    $out = [];
    foreach (preg_split('/\n{2,}/', $src) ?: [] as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $lines = explode("\n", $block);
        if (preg_match('/^(#{2,3})\s+(.+)$/', $lines[0], $m) && count($lines) === 1) {
            $n = strlen($m[1]);
            $out[] = "<h{$n}>" . rrp_blog_inline($m[2]) . "</h{$n}>";
        } elseif ($block === '---') {
            $out[] = '<hr>';
        } elseif (preg_match('/^!\[([^\]]{0,160})\]\(((?:https?:\/\/|\/)[^)\s]{1,500})\)$/', $block, $m) && !str_starts_with($m[2], '//')) {
            $out[] = '<figure><img src="' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '" loading="lazy"></figure>';
        } elseif (count(array_filter($lines, static fn($l) => preg_match('/^[-*]\s+/', $l))) === count($lines)) {
            $out[] = '<ul>' . implode('', array_map(static fn($l) => '<li>' . rrp_blog_inline((string) preg_replace('/^[-*]\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
        } elseif (count(array_filter($lines, static fn($l) => preg_match('/^\d+[.)]\s+/', $l))) === count($lines)) {
            $out[] = '<ol>' . implode('', array_map(static fn($l) => '<li>' . rrp_blog_inline((string) preg_replace('/^\d+[.)]\s+/', '', $l)) . '</li>', $lines)) . '</ol>';
        } elseif (count(array_filter($lines, static fn($l) => str_starts_with($l, '>'))) === count($lines)) {
            $out[] = '<blockquote><p>' . rrp_blog_inline(trim(implode(' ', array_map(static fn($l) => ltrim(substr($l, 1)), $lines)))) . '</p></blockquote>';
        } else {
            $out[] = '<p>' . rrp_blog_inline(implode(' ', array_map('trim', $lines))) . '</p>';
        }
    }
    return implode("\n", $out);
}

/** Plain text of a post (for the description and the llms files): markdown marks removed. */
function rrp_blog_plain(string $src): string
{
    $t = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $src);
    $t = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $t);
    $t = (string) preg_replace('/^[#>\-*\d.)\s]+/m', '', $t);
    $t = str_replace(['**', '`'], '', $t);
    $t = (string) preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '$1', strip_tags($t)); // drop *italic* marks and any typed HTML tags
    return trim((string) preg_replace('/\s+/', ' ', $t));
}

// ---------------------------------------------------------------------------
// Saving (Admin → Blog)
// ---------------------------------------------------------------------------

/**
 * Create or update a post. $id = 0 creates.
 * @param array{title?:string, slug?:string, excerpt?:string, body?:string, cover_alt?:string, author?:string, status?:string, published_at?:string, remove_cover?:bool} $d
 * @param array|null $coverFile one $_FILES entry, if a cover was chosen
 * @return array{ok:bool, id?:int, error?:string}
 */
function rrp_blog_save(int $adminId, int $id, array $d, ?array $coverFile = null): array
{
    $title = trim((string) ($d['title'] ?? ''));
    $body = trim((string) ($d['body'] ?? ''));
    $excerpt = trim(preg_replace('/\s+/', ' ', (string) ($d['excerpt'] ?? '')) ?? '');
    $status = ($d['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    if ($title === '' || mb_strlen($title) > 160) {
        return ['ok' => false, 'error' => 'Give the post a title (up to 160 characters).'];
    }
    if ($body === '') {
        return ['ok' => false, 'error' => 'The post is empty. Write something or upload a file.'];
    }
    if (mb_strlen($body) > 200000) {
        return ['ok' => false, 'error' => 'The post is too long.'];
    }
    if ($excerpt === '') {
        // First real paragraph (skip headings, lists, quotes and pictures), cut at a word.
        $first = '';
        foreach (preg_split('/\n{2,}/', str_replace("\r", '', $body)) ?: [] as $blk) {
            $blk = trim($blk);
            if ($blk !== '' && !preg_match('/^(#|>|[-*]\s|\d+[.)]\s|!\[|---)/', $blk)) {
                $first = $blk;
                break;
            }
        }
        $plain = rrp_blog_plain($first !== '' ? $first : $body);
        $excerpt = mb_strlen($plain) > 200 ? rtrim(mb_substr($plain, 0, 197), " ,.;:-") . '…' : $plain;
    }
    $excerpt = mb_substr($excerpt, 0, 300);
    $slug = strtolower(trim((string) ($d['slug'] ?? '')));
    $slug = $slug !== '' ? rrp_blog_slugify($slug) : rrp_blog_slugify($title);
    $author = mb_substr(trim((string) ($d['author'] ?? '')) ?: 'The SIPAW team', 0, 80);
    $alt = mb_substr(trim((string) ($d['cover_alt'] ?? '')), 0, 160);
    try {
        $db = rrp_db();
        $cur = $id > 0 ? rrp_blog_by_id($id) : null;
        if ($id > 0 && !$cur) {
            return ['ok' => false, 'error' => 'That post no longer exists.'];
        }
        $dup = $db->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id <> ?');
        $dup->execute([$slug, $id]);
        if ($dup->fetchColumn()) {
            return ['ok' => false, 'error' => 'Another post already uses the address "' . $slug . '". Change the address.'];
        }
        // Publish date: what the admin typed, else now the first time it goes live, else keep.
        $pub = trim((string) ($d['published_at'] ?? ''));
        $pubAt = null;
        if ($pub !== '') {
            $t = strtotime($pub . ' UTC');
            $pubAt = $t === false ? null : gmdate('Y-m-d H:i:s', $t);
        }
        if ($status === 'published' && $pubAt === null) {
            $pubAt = !empty($cur['published_at']) ? (string) $cur['published_at'] : gmdate('Y-m-d H:i:s');
        }
        $cover = $cur['cover'] ?? null;
        if (!empty($d['remove_cover']) && $cover) {
            rrp_social_delete_photo($cover);
            $cover = null;
        }
        if ($coverFile && ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = rrp_social_compress_photo($coverFile, 'blog');
            if (empty($up['ok'])) {
                return ['ok' => false, 'error' => (string) ($up['error'] ?? 'The cover image could not be used.')];
            }
            if ($cover) {
                rrp_social_delete_photo($cover);
            }
            $cover = (string) $up['path'];
        }
        if ($id > 0) {
            $db->prepare('UPDATE blog_posts SET slug = ?, title = ?, excerpt = ?, body = ?, cover = ?, cover_alt = ?, author = ?, status = ?, published_at = ? WHERE id = ?')
                ->execute([$slug, $title, $excerpt, $body, $cover, $alt !== '' ? $alt : null, $author, $status, $pubAt, $id]);
            rrp_admin_log($adminId, 'blog_update', 'blog_posts', $id, "slug=$slug status=$status");
            return ['ok' => true, 'id' => $id];
        }
        $db->prepare('INSERT INTO blog_posts (slug, title, excerpt, body, cover, cover_alt, author, status, published_at, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$slug, $title, $excerpt, $body, $cover, $alt !== '' ? $alt : null, $author, $status, $pubAt, $adminId]);
        $newId = (int) $db->lastInsertId();
        rrp_admin_log($adminId, 'blog_create', 'blog_posts', $newId, "slug=$slug status=$status");
        return ['ok' => true, 'id' => $newId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => rrp_safe_db_error($e, 'Could not save the post')];
    }
}

/** Publish or hide a post. @return array{ok:bool, error?:string} */
function rrp_blog_set_status(int $adminId, int $id, string $status): array
{
    $status = $status === 'published' ? 'published' : 'draft';
    try {
        $st = rrp_db()->prepare("UPDATE blog_posts SET status = ?, published_at = IF(? = 'published', COALESCE(published_at, UTC_TIMESTAMP()), published_at) WHERE id = ?");
        $st->execute([$status, $status, $id]);
        rrp_admin_log($adminId, 'blog_status', 'blog_posts', $id, "status=$status");
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not change the post.'];
    }
}

/** Delete a post and its cover for good. @return array{ok:bool, error?:string} */
function rrp_blog_delete(int $adminId, int $id): array
{
    $p = rrp_blog_by_id($id);
    if (!$p) {
        return ['ok' => false, 'error' => 'Post not found.'];
    }
    try {
        rrp_db()->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
        if (!empty($p['cover'])) {
            rrp_social_delete_photo((string) $p['cover']);
        }
        rrp_admin_log($adminId, 'blog_delete', 'blog_posts', $id, 'slug=' . $p['slug']);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not delete the post.'];
    }
}
