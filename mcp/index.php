<?php
// Nakilab MCP server — https://nakilab.com/mcp
// A stateless Model Context Protocol endpoint (Streamable HTTP, JSON responses) so any MCP client
// (Claude, Cursor, ChatGPT, …) can write and publish blog posts and read intake submissions.
// Auth: "Authorization: Bearer <token>". Only the token's SHA-256 is stored, in config/mcp.php.
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/posts.php';

const NK_PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];
const NK_SUB_STATUSES = ['new', 'reviewing', 'accepted', 'declined'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function out(int $code, $body = null): void {
    http_response_code($code);
    if ($body !== null) echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); out(405, ['error' => 'Use POST (MCP Streamable HTTP).']); }

// ---- auth ----
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : ($_SERVER['HTTP_X_API_KEY'] ?? '');
$expected = (string) (nk_config('mcp')['token_sha256'] ?? '');
if ($expected === '' || $token === '' || !hash_equals($expected, hash('sha256', $token))) {
    header('WWW-Authenticate: Bearer realm="nakilab"');
    out(401, ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'Unauthorized']]);
}

$raw = file_get_contents('php://input', false, null, 0, 1_000_000);
$req = json_decode((string) $raw, true);
if (!is_array($req)) out(400, ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);

$client = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'mcp-client'), 0, 120);

// ---- tool definitions ----
function tools(): array {
    $postStatus = ['type' => 'string', 'enum' => NK_POST_STATUSES];
    return [
        ['name' => 'get_blog_guidelines', 'description' => 'Read this FIRST before writing a post. Returns Nakilab\'s editorial rules, voice, topics, and the titles of recent posts (to avoid repeating topics).',
         'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'list_posts', 'description' => 'List blog posts (newest first), optionally filtered by status.',
         'inputSchema' => ['type' => 'object', 'properties' => [
            'status' => ['type' => 'string', 'enum' => array_merge(NK_POST_STATUSES, ['all']), 'default' => 'all'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0]]]],
        ['name' => 'get_post', 'description' => 'Get one blog post (with its full Markdown body) by id or slug.',
         'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'slug' => ['type' => 'string']]]],
        ['name' => 'create_post', 'description' => 'Create a blog post. Body is Markdown (## headings, lists, **bold**, links). Defaults to a draft; set status "published" to go live now, or "scheduled" with publish_at.',
         'inputSchema' => ['type' => 'object', 'required' => ['title', 'body_markdown'], 'properties' => [
            'title' => ['type' => 'string', 'maxLength' => 200],
            'body_markdown' => ['type' => 'string'],
            'excerpt' => ['type' => 'string', 'maxLength' => 400, 'description' => 'One or two sentences shown on the blog list and in search results.'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
            'slug' => ['type' => 'string', 'description' => 'Optional URL slug; generated from the title if omitted.'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'scheduled'], 'default' => 'draft'],
            'publish_at' => ['type' => 'string', 'description' => 'UTC date-time (ISO 8601) — required when status is "scheduled".'],
            'author' => ['type' => 'string', 'maxLength' => 80, 'default' => 'Nakilab']]]],
        ['name' => 'update_post', 'description' => 'Edit an existing post. Only the fields you pass are changed.',
         'inputSchema' => ['type' => 'object', 'required' => ['id'], 'properties' => [
            'id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'body_markdown' => ['type' => 'string'],
            'excerpt' => ['type' => 'string'], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'slug' => ['type' => 'string'], 'status' => $postStatus, 'publish_at' => ['type' => 'string'], 'author' => ['type' => 'string']]]],
        ['name' => 'publish_post', 'description' => 'Publish a post now, or schedule it by passing publish_at (UTC ISO 8601).',
         'inputSchema' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer'], 'publish_at' => ['type' => 'string']]]],
        ['name' => 'unpublish_post', 'description' => 'Take a post off the site (status becomes "archived"). It can be published again later.',
         'inputSchema' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer']]]],
        ['name' => 'list_submissions', 'description' => 'List intake-form submissions from nakilab.com (people with an idea, people who need an idea, investors). Contains personal data — use only for Nakilab business.',
         'inputSchema' => ['type' => 'object', 'properties' => [
            'type' => ['type' => 'string', 'enum' => ['have', 'need', 'invest', 'all'], 'default' => 'all'],
            'status' => ['type' => 'string', 'enum' => array_merge(NK_SUB_STATUSES, ['all']), 'default' => 'all'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]]]],
        ['name' => 'update_submission', 'description' => 'Set the review status of a submission.',
         'inputSchema' => ['type' => 'object', 'required' => ['id', 'status'], 'properties' => [
            'id' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => NK_SUB_STATUSES]]]],
    ];
}

class ToolError extends RuntimeException {}

function utc(?string $s): ?string {
    if ($s === null || $s === '') return null;
    $t = strtotime($s);
    if ($t === false) throw new ToolError("Could not read date \"$s\". Use ISO 8601, e.g. 2026-10-20T09:00:00Z.");
    return gmdate('Y-m-d H:i:s', $t);
}

function post_out(array $p, bool $withBody = false): array {
    $o = ['id' => (int) $p['id'], 'slug' => $p['slug'], 'title' => $p['title'], 'excerpt' => $p['excerpt'],
        'tags' => array_values(array_filter(array_map('trim', explode(',', $p['tags'])))), 'author' => $p['author'],
        'status' => $p['status'], 'live' => nk_is_live($p), 'publish_at' => $p['publish_at'] ? $p['publish_at'] . ' UTC' : null,
        'created_at' => $p['created_at'] . ' UTC', 'updated_at' => $p['updated_at'] . ' UTC', 'url' => nk_post_url($p)];
    if ($withBody) $o['body_markdown'] = $p['body'];
    return $o;
}

function tags_str($tags): string {
    if (!is_array($tags)) return '';
    $clean = array_slice(array_values(array_filter(array_map(fn($t) => mb_substr(trim(str_replace(',', ' ', (string) $t)), 0, 40), $tags))), 0, 8);
    return implode(', ', $clean);
}

function call_tool(string $name, array $a, string $client) {
    $db = nk_db();
    switch ($name) {
        case 'get_blog_guidelines':
            $recent = array_map(fn($p) => $p['title'] . ' (' . $p['status'] . ')', $db->query('SELECT title, status FROM posts ORDER BY id DESC LIMIT 40')->fetchAll());
            return [
                'site' => 'Nakilab (nakilab.com) is a startup studio: "We turn ideas into companies." People submit ideas; Nakilab evaluates, builds and launches them on its own infrastructure, powered by SI, Super Intelligence. Founders: Mehrdad and Navid (both Co-Founder). Current project: siPaw, product 01 (SI pet care companion: Sipo chat, reminders and owner boards; say "S-I Paw"; formerly RingRingPaw; live at sipaw.nakilab.com).',
                'rules' => [
                    'Write in English.',
                    'NEVER use the words "AI" or "artificial intelligence". Always say "SI" or "Super Intelligence" instead.',
                    'Do not invent statistics, customers, funding, partnerships or quotes. If you cite a fact, it must be real and verifiable; otherwise speak from first principles.',
                    'Do not publish email addresses or personal data from submissions.',
                    'Calm, direct, a little dry: engineers who ship. One idea per sentence, most sentences under 12 words. Verbs first. Numbers over adjectives, and every number has a source. No hype words (revolutionary, game-changing, disrupt, magic).',
                    'No em dashes. Use a full stop, a comma or a colon. No exclamation marks.',
                    'Spell the pet product "siPaw" in text, never "SiPaw", "SIPAW" or "RingRingPaw". Write "SI, Super Intelligence" on first use.',
                    'Pick a topic that is NOT already covered in recent_posts.',
                ],
                'format' => [
                    'Title: clear and specific, under 70 characters.',
                    'Excerpt: 1–2 sentences, under 160 characters.',
                    'Body: Markdown, 700–1,200 words, with 3–5 "##" sections and at least one list. Do not repeat the title as a heading.',
                    'End with a short call to action inviting readers to submit their idea at https://nakilab.com/#intake.',
                    'Tags: 2–5 short tags.',
                ],
                'topic_areas' => ['Turning an idea into a company', 'Validating ideas before building', 'Building and launching with SI', 'Startup studio model', 'Founder lessons', 'Product launch playbooks', 'Behind the scenes of Nakilab projects like siPaw', 'Pets and technology (siPaw)'],
                'publishing' => 'Create posts as "draft" unless a human has asked you to publish. A human reviews drafts and calls publish_post.',
                'recent_posts' => $recent,
            ];
        case 'list_posts':
            $status = $a['status'] ?? 'all';
            $limit = min(100, max(1, (int) ($a['limit'] ?? 20))); $offset = max(0, (int) ($a['offset'] ?? 0));
            if ($status !== 'all' && !in_array($status, NK_POST_STATUSES, true)) throw new ToolError('Unknown status.');
            $q = $db->prepare('SELECT * FROM posts' . ($status === 'all' ? '' : ' WHERE status = ?') . " ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $q->execute($status === 'all' ? [] : [$status]);
            return ['posts' => array_map('post_out', $q->fetchAll())];
        case 'get_post':
            $p = nk_find_post(isset($a['id']) ? (int) $a['id'] : null, $a['slug'] ?? null);
            if (!$p) throw new ToolError('Post not found.');
            return post_out($p, true);
        case 'create_post':
            $title = trim((string) ($a['title'] ?? '')); $body = trim((string) ($a['body_markdown'] ?? ''));
            if ($title === '' || $body === '') throw new ToolError('title and body_markdown are required.');
            $status = $a['status'] ?? 'draft';
            if (!in_array($status, ['draft', 'published', 'scheduled'], true)) throw new ToolError('status must be draft, published or scheduled.');
            $publishAt = utc($a['publish_at'] ?? null);
            if ($status === 'scheduled' && !$publishAt) throw new ToolError('publish_at is required for scheduled posts.');
            if ($status === 'published') $publishAt = nk_now();
            $now = nk_now();
            $slug = nk_unique_slug((string) ($a['slug'] ?? '') ?: $title);
            $db->prepare('INSERT INTO posts (slug, title, excerpt, body, tags, author, status, publish_at, created_at, updated_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$slug, mb_substr($title, 0, 200), mb_substr(trim((string) ($a['excerpt'] ?? '')), 0, 400), $body, tags_str($a['tags'] ?? []),
                          mb_substr(trim((string) ($a['author'] ?? '')) ?: 'Nakilab', 0, 80), $status, $publishAt, $now, $now, $client]);
            return post_out(nk_find_post((int) $db->lastInsertId()));
        case 'update_post':
            $p = nk_find_post((int) ($a['id'] ?? 0));
            if (!$p) throw new ToolError('Post not found.');
            $set = []; $vals = [];
            foreach (['title' => 200, 'excerpt' => 400, 'author' => 80] as $f => $max) if (array_key_exists($f, $a)) { $set[] = "$f = ?"; $vals[] = mb_substr(trim((string) $a[$f]), 0, $max); }
            if (array_key_exists('body_markdown', $a)) { $set[] = 'body = ?'; $vals[] = trim((string) $a['body_markdown']); }
            if (array_key_exists('tags', $a)) { $set[] = 'tags = ?'; $vals[] = tags_str($a['tags']); }
            if (array_key_exists('slug', $a)) { $set[] = 'slug = ?'; $vals[] = nk_unique_slug((string) $a['slug'], (int) $p['id']); }
            if (array_key_exists('status', $a)) {
                if (!in_array($a['status'], NK_POST_STATUSES, true)) throw new ToolError('Unknown status.');
                $set[] = 'status = ?'; $vals[] = $a['status'];
                if ($a['status'] === 'published' && !$p['publish_at'] && !array_key_exists('publish_at', $a)) { $set[] = 'publish_at = ?'; $vals[] = nk_now(); }
            }
            if (array_key_exists('publish_at', $a)) { $set[] = 'publish_at = ?'; $vals[] = utc($a['publish_at']); }
            if (!$set) throw new ToolError('Nothing to update.');
            $set[] = 'updated_at = ?'; $vals[] = nk_now(); $vals[] = (int) $p['id'];
            $db->prepare('UPDATE posts SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
            return post_out(nk_find_post((int) $p['id']));
        case 'publish_post':
            $p = nk_find_post((int) ($a['id'] ?? 0));
            if (!$p) throw new ToolError('Post not found.');
            $at = utc($a['publish_at'] ?? null);
            $status = $at && $at > nk_now() ? 'scheduled' : 'published';
            $db->prepare('UPDATE posts SET status = ?, publish_at = ?, updated_at = ? WHERE id = ?')->execute([$status, $at ?: nk_now(), nk_now(), (int) $p['id']]);
            return post_out(nk_find_post((int) $p['id']));
        case 'unpublish_post':
            $p = nk_find_post((int) ($a['id'] ?? 0));
            if (!$p) throw new ToolError('Post not found.');
            $db->prepare("UPDATE posts SET status = 'archived', updated_at = ? WHERE id = ?")->execute([nk_now(), (int) $p['id']]);
            return post_out(nk_find_post((int) $p['id']));
        case 'list_submissions':
            $where = []; $vals = [];
            $type = $a['type'] ?? 'all'; $status = $a['status'] ?? 'all';
            if ($type !== 'all') { if (!in_array($type, ['have', 'need', 'invest'], true)) throw new ToolError('Unknown type.'); $where[] = 'type = ?'; $vals[] = $type; }
            if ($status !== 'all') { if (!in_array($status, NK_SUB_STATUSES, true)) throw new ToolError('Unknown status.'); $where[] = 'status = ?'; $vals[] = $status; }
            $limit = min(100, max(1, (int) ($a['limit'] ?? 20)));
            $q = $db->prepare('SELECT id, created_at, type, name, email, details, status FROM submissions' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC LIMIT $limit");
            $q->execute($vals);
            return ['submissions' => array_map(fn($r) => ['id' => (int) $r['id'], 'created_at' => $r['created_at'] . ' UTC', 'type' => $r['type'], 'name' => $r['name'],
                'email' => $r['email'], 'status' => $r['status'], 'details' => json_decode($r['details'], true)], $q->fetchAll())];
        case 'update_submission':
            if (!in_array($a['status'] ?? '', NK_SUB_STATUSES, true)) throw new ToolError('Unknown status.');
            $q = $db->prepare('UPDATE submissions SET status = ? WHERE id = ?');
            $q->execute([$a['status'], (int) ($a['id'] ?? 0)]);
            if ($q->rowCount() === 0) throw new ToolError('Submission not found (or already has that status).');
            return ['id' => (int) $a['id'], 'status' => $a['status']];
    }
    throw new ToolError("Unknown tool: $name");
}

// ---- JSON-RPC dispatch ----
function handle(array $msg, string $client): ?array {
    $id = $msg['id'] ?? null;
    $method = (string) ($msg['method'] ?? '');
    if (!array_key_exists('id', $msg)) return null; // notification (e.g. notifications/initialized)
    $ok = fn($result) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    $err = fn(int $code, string $m) => ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $m]];
    switch ($method) {
        case 'initialize':
            $want = (string) ($msg['params']['protocolVersion'] ?? '');
            return $ok([
                'protocolVersion' => in_array($want, NK_PROTOCOLS, true) ? $want : NK_PROTOCOLS[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'nakilab', 'title' => 'Nakilab', 'version' => '1.0.0'],
                'instructions' => 'Nakilab site tools: blog posts and intake submissions. Before writing a blog post, call get_blog_guidelines and follow it (never use the word "AI"; say "SI" / "Super Intelligence").',
            ]);
        case 'ping':
            return $ok(new stdClass());
        case 'tools/list':
            return $ok(['tools' => tools()]);
        case 'tools/call':
            $name = (string) ($msg['params']['name'] ?? '');
            $args = $msg['params']['arguments'] ?? [];
            if (!is_array($args)) $args = [];
            try {
                $result = call_tool($name, $args, $client);
                return $ok(['content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]], 'structuredContent' => $result, 'isError' => false]);
            } catch (ToolError $e) {
                return $ok(['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true]);
            } catch (Throwable $e) {
                error_log('mcp: ' . $e->getMessage());
                return $ok(['content' => [['type' => 'text', 'text' => 'Server error while running the tool.']], 'isError' => true]);
            }
        default:
            return $err(-32601, "Method not found: $method");
    }
}

$isBatch = array_is_list($req);
$replies = [];
foreach ($isBatch ? $req : [$req] as $msg) {
    if (!is_array($msg)) { $replies[] = ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => 'Invalid Request']]; continue; }
    $r = handle($msg, $client);
    if ($r !== null) $replies[] = $r;
}
if (!$replies) out(202);
out(200, $isBatch ? $replies : $replies[0]);
