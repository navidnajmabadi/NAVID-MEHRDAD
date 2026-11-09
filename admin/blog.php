<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// Blog: write a post (or upload a text file), add a cover picture, keep it as a draft or publish it on the public site.
$admin = rrp_require_admin();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'save') {
        $d = $_POST;
        $d['status'] = ($_POST['publish'] ?? '') === '1' ? 'published' : 'draft';
        $d['remove_cover'] = !empty($_POST['remove_cover']);
        $r = rrp_blog_save((int) $admin['id'], $id, $d, $_FILES['cover'] ?? null);
        if (!empty($r['ok'])) {
            rrp_flash_set('ok', $d['status'] === 'published' ? 'Saved and published on the site.' : 'Saved as a draft. It is not on the site yet.');
            rrp_redirect('blog.php?edit=' . (int) $r['id']);
        }
        $error = $r['error'] ?? 'Could not save the post.';
        $editPost = ['id' => $id] + $_POST;
    } elseif (in_array($action, ['publish', 'unpublish'], true)) {
        $r = rrp_blog_set_status((int) $admin['id'], $id, $action === 'publish' ? 'published' : 'draft');
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? ($action === 'publish' ? 'Published on the site.' : 'Hidden from the site (kept as a draft).') : ($r['error'] ?? 'Failed'));
        rrp_redirect('blog.php');
    } elseif ($action === 'delete') {
        $r = rrp_blog_delete((int) $admin['id'], $id);
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Post deleted.' : ($r['error'] ?? 'Failed'));
        rrp_redirect('blog.php');
    }
}

$editing = isset($_GET['edit']) || isset($_GET['new']) || isset($editPost);
$post = $editPost ?? (isset($_GET['edit']) ? rrp_blog_by_id((int) $_GET['edit']) : null);
if (isset($_GET['edit']) && !$post) {
    rrp_flash_set('error', 'That post was not found.');
    rrp_redirect('blog.php');
}
$posts = $editing ? [] : rrp_blog_all();
$csrf = rrp_csrf_token();
$val = static fn(string $k, string $d = '') => rrp_h((string) ($post[$k] ?? $d));

rrp_admin_shell_start('Blog', 'blog');
?>
  <h1>Blog</h1>
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

<?php if (!$editing): ?>
  <p class="lede">Posts you publish appear on the public site at <a class="link" href="<?= rrp_h(rrp_asset('blog.php')) ?>" target="_blank" rel="noopener">/blog</a>, in the sitemap and in <code>llms.txt</code>, so search engines and assistants can read them.</p>
  <p><a class="ad-btn is-primary" href="blog.php?new=1">Write a new post</a></p>

  <?php if (!$posts): ?>
    <div class="ad-tablewrap"><p class="ad-empty">No posts yet. Press "Write a new post".</p></div>
  <?php else: ?>
  <div class="ad-tablewrap">
    <table class="ad-table">
      <thead><tr><th>Post</th><th>Status</th><th>Date</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($posts as $p): $live = $p['status'] === 'published' && (empty($p['published_at']) || strtotime((string) $p['published_at'] . ' UTC') <= time()); ?>
        <tr>
          <td class="ad-who" data-label="Post"><b><?= rrp_h((string) $p['title']) ?></b><small>/blog/<?= rrp_h((string) $p['slug']) ?></small></td>
          <td data-label="Status"><span class="ad-chip <?= $live ? 'is-good' : ($p['status'] === 'published' ? 'is-warn' : '') ?>"><?= $live ? 'Published' : ($p['status'] === 'published' ? 'Scheduled' : 'Draft') ?></span></td>
          <td data-label="Date"><?= rrp_h((string) $p['date']) ?></td>
          <td style="white-space:nowrap">
            <a class="ad-btn" href="blog.php?edit=<?= (int) $p['id'] ?>">Edit</a>
            <?php if ($live): ?><a class="ad-btn" href="<?= rrp_h(rrp_asset('blog/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <?php else: ?><a class="ad-btn" href="<?= rrp_h(rrp_asset('blog-post.php?preview=' . (int) $p['id'])) ?>" target="_blank" rel="noopener">Preview</a><?php endif; ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="id" value="<?= (int) $p['id'] ?>" />
              <?php if ($p['status'] === 'published'): ?><input type="hidden" name="action" value="unpublish" /><button class="ad-btn" type="submit">Hide</button>
              <?php else: ?><input type="hidden" name="action" value="publish" /><button class="ad-btn is-primary" type="submit">Publish</button><?php endif; ?></form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this post for good? To only take it off the site, press Hide instead.');"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="id" value="<?= (int) $p['id'] ?>" /><input type="hidden" name="action" value="delete" /><button class="ad-btn" type="submit" style="color:var(--ad-bad)">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php else: ?>
  <p class="lede"><a class="link" href="blog.php">← All posts</a> · <?= !empty($post['id']) ? 'Editing a post' : 'New post' ?></p>
  <form method="post" enctype="multipart/form-data" class="dash-card" style="max-width:820px" id="blogForm">
    <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" />
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="id" value="<?= (int) ($post['id'] ?? 0) ?>" />

    <div class="field"><label for="bt">Title</label><div class="in"><input id="bt" name="title" required maxlength="160" value="<?= $val('title') ?>" /></div></div>
    <div class="field"><label for="bs">Web address</label><div class="in"><span class="meta" style="white-space:nowrap">/blog/</span><input id="bs" name="slug" maxlength="80" placeholder="made from the title" value="<?= $val('slug') ?>" /></div>
      <p class="meta">Letters, numbers and dashes. Changing it later breaks links people already have.</p></div>
    <div class="field"><label for="be">Short summary</label><div class="in area"><textarea id="be" name="excerpt" rows="2" maxlength="300" style="width:100%;border:0;background:transparent;color:inherit;font:inherit;padding:10px 0"><?= $val('excerpt') ?></textarea></div>
      <p class="meta">Shown in the blog list and used as the description for Google and assistants. <span id="exCount"></span></p></div>

    <div class="field"><label for="bc">Cover picture (optional)</label>
      <?php if (!empty($post['cover'])): ?><p><img src="<?= rrp_h(rrp_asset((string) $post['cover'])) ?>" alt="" style="max-width:260px;border-radius:12px;display:block" /></p>
        <label class="meta"><input type="checkbox" name="remove_cover" value="1" /> Remove this picture</label><?php endif; ?>
      <div class="in"><input id="bc" name="cover" type="file" accept="image/jpeg,image/png,image/webp" /></div>
      <p class="meta">JPG, PNG or WebP. It is shrunk and cleaned automatically.</p></div>
    <div class="field"><label for="ba">What the picture shows (for screen readers and Google)</label><div class="in"><input id="ba" name="cover_alt" maxlength="160" value="<?= $val('cover_alt') ?>" /></div></div>

    <div class="field"><label for="bb">The post</label>
      <div class="in area"><textarea id="bb" name="body" rows="18" required style="width:100%;border:0;background:transparent;color:inherit;font:inherit;padding:10px 0;font-family:ui-monospace,Menlo,monospace;font-size:14px"><?= $val('body') ?></textarea></div>
      <p class="meta" id="aiWarn" style="color:var(--ad-warn)" hidden>Use "SI" or "Super Intelligence" instead of the word "AI" in SIPAW posts.</p></div>
    <div class="field"><label for="bf">Or upload a file (.md, .txt or .html) to fill the box</label><div class="in"><input id="bf" type="file" accept=".md,.markdown,.txt,.html,.htm,text/plain,text/markdown,text/html" /></div>
      <p class="meta">The file is read in your browser and put in the box above so you can check and edit it before saving.</p></div>
    <details style="margin:6px 0 14px"><summary class="meta" style="cursor:pointer">How to format</summary>
      <pre class="mono" style="white-space:pre-wrap;font-size:13px">## A heading
### A smaller heading

A paragraph. **Bold**, *italic*, [a link](https://example.com).

- A list item
- Another one

1. A numbered step

> A quote

![What the picture shows](https://example.com/picture.jpg)</pre>
      <p class="meta">Leave a blank line between blocks. HTML typed in is shown as text, never run, so a post cannot break the site.</p></details>

    <div class="field"><label for="bu">Author</label><div class="in"><input id="bu" name="author" maxlength="80" value="<?= $val('author', 'The SIPAW team') ?>" /></div></div>
    <div class="field"><label for="bd">Publish date (optional, UTC)</label><div class="in"><input id="bd" name="published_at" type="datetime-local" value="<?= !empty($post['published_at']) ? rrp_h(str_replace(' ', 'T', substr((string) $post['published_at'], 0, 16))) : '' ?>" /></div>
      <p class="meta">Empty = the moment you publish. A date in the future keeps the post hidden until then.</p></div>

    <p class="meta"><b>Status now:</b> <?= ($post['status'] ?? 'draft') === 'published' ? 'Published' : 'Draft (not on the site)' ?></p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="ad-btn" type="submit" name="publish" value="0">Save as draft</button>
      <button class="ad-btn is-primary" type="submit" name="publish" value="1">Save and publish</button>
      <?php if (!empty($post['id'])): ?><a class="ad-btn" href="<?= rrp_h(rrp_asset('blog-post.php?preview=' . (int) $post['id'])) ?>" target="_blank" rel="noopener">Preview saved version</a><?php endif; ?>
    </div>
  </form>
<script>
(function () {
  var form = document.getElementById('blogForm'), t = document.getElementById('bt'), s = document.getElementById('bs'), e = document.getElementById('be'), b = document.getElementById('bb'), f = document.getElementById('bf');
  var slugTouched = !!s.value;
  function slugify(v) { return v.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80); }
  s.addEventListener('input', function () { slugTouched = true; });
  t.addEventListener('input', function () { if (!slugTouched) s.value = slugify(t.value); });
  function count() { document.getElementById('exCount').textContent = e.value.length + '/300'; }
  e.addEventListener('input', count); count();
  function aiCheck() { document.getElementById('aiWarn').hidden = !/\bAI\b/.test(t.value + ' ' + b.value); }
  t.addEventListener('input', aiCheck); b.addEventListener('input', aiCheck); aiCheck();

  // A file becomes the text in the box (HTML is turned into the simple format first).
  function htmlToText(html) {
    var doc = new DOMParser().parseFromString(html, 'text/html'), out = [];
    function inline(n) {
      var r = '';
      n.childNodes.forEach(function (c) {
        if (c.nodeType === 3) r += c.textContent;
        else if (c.nodeType === 1) {
          var tag = c.tagName.toLowerCase(), inner = inline(c);
          if (tag === 'strong' || tag === 'b') r += '**' + inner + '**';
          else if (tag === 'em' || tag === 'i') r += '*' + inner + '*';
          else if (tag === 'a' && /^(https?:|mailto:|\/)/i.test(c.getAttribute('href') || '')) r += '[' + inner + '](' + c.getAttribute('href') + ')';
          else if (tag === 'br') r += ' ';
          else r += inner;
        }
      });
      return r.replace(/\s+/g, ' ').trim();
    }
    function walk(n) {
      n.childNodes.forEach(function (c) {
        if (c.nodeType !== 1) return;
        var tag = c.tagName.toLowerCase();
        if (tag === 'h1') { if (!t.value) t.value = inline(c); }
        else if (tag === 'h2') out.push('## ' + inline(c));
        else if (/^h[3-6]$/.test(tag)) out.push('### ' + inline(c));
        else if (tag === 'p') { var x = inline(c); if (x) out.push(x); }
        else if (tag === 'ul') out.push([].map.call(c.children, function (li) { return '- ' + inline(li); }).join('\n'));
        else if (tag === 'ol') out.push([].map.call(c.children, function (li, i) { return (i + 1) + '. ' + inline(li); }).join('\n'));
        else if (tag === 'blockquote') out.push('> ' + inline(c));
        else if (tag === 'hr') out.push('---');
        else if (tag === 'script' || tag === 'style') { /* skip */ }
        else walk(c);
      });
    }
    walk(doc.body);
    return out.join('\n\n');
  }
  f.addEventListener('change', function () {
    var file = f.files && f.files[0];
    if (!file) return;
    if (file.size > 400 * 1024) { alert('That file is too big for a post (limit 400 KB).'); f.value = ''; return; }
    var r = new FileReader();
    r.onload = function () {
      var text = String(r.result || '');
      if (/\.html?$/i.test(file.name) || /^\s*<(!doctype|html|body|h1|h2|p)\b/i.test(text)) text = htmlToText(text);
      if (b.value.trim() && !confirm('Replace what is in the box with the file?')) return;
      b.value = text;
      if (!t.value) t.value = file.name.replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ');
      t.dispatchEvent(new Event('input')); aiCheck();
    };
    r.readAsText(file);
  });
})();
</script>
<?php endif; ?>
<?php rrp_admin_shell_end(); ?>
