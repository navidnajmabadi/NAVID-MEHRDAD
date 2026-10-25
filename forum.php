<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? 'create');
    if ($action === 'create') {
        $result = rrp_forum_create_post((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0), (string) ($_POST['body'] ?? ''));
        if (!empty($result['ok'])) {
            rrp_flash_set('ok', 'Posted as your pet.');
            rrp_redirect('forum.php');
        }
        $error = $result['error'] ?? 'Could not post.';
    }
}

$posts = rrp_forum_feed(40);
rrp_layout_head('Boards — SIPAW');
rrp_app_start(['nav' => 'home']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Boards</h2></div>
  <a class="iconbtn" href="si-chat.php" aria-label="SIPO">SI</a>
</div>
<div class="pad stack" style="gap:16px">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error"><?= rrp_h($error) ?></div><?php endif; ?>
  <p class="sub" style="font-size:14px">Pet-persona posts (max 250). Public downvote + Report. Free & Plus.</p>

  <?php if ($pets): ?>
  <form method="post" class="box stack" style="gap:12px">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <input type="hidden" name="action" value="create" />
    <p class="label">Post as</p>
    <select name="pet_id" style="min-height:44px;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px">
      <?php foreach ($pets as $p): ?><option value="<?= (int) $p['id'] ?>"><?= rrp_h($p['name']) ?></option><?php endforeach; ?>
    </select>
    <div class="field"><div class="in area"><textarea name="body" maxlength="250" rows="3" required placeholder="Tip or question (250 chars)"></textarea></div></div>
    <button class="btn primary" type="submit">Post</button>
  </form>
  <?php endif; ?>

  <div class="stack" style="gap:12px">
    <?php if (!$posts): ?>
      <p class="sub">No posts yet. Be first — or import sql/006_v1_product.sql.</p>
    <?php endif; ?>
    <?php foreach ($posts as $post): ?>
      <article class="box">
        <p class="label"><?= rrp_h($post['pet_name'] ?? 'Pet') ?> · <?= rrp_h($post['pet_type'] ?? '') ?></p>
        <p style="margin-top:10px;font-size:15px"><?= rrp_h($post['body']) ?></p>
        <p class="sub data" style="margin-top:10px">+<?= (int) ($post['like_count'] ?? 0) ?> · −<?= (int) ($post['dislike_count'] ?? 0) ?> · <?= (int) ($post['comment_count'] ?? 0) ?> comments</p>
        <p style="margin-top:10px;display:flex;gap:12px;flex-wrap:wrap">
          <a class="link" href="forum-post.php?id=<?= (int) $post['id'] ?>">Open</a>
          <form method="post" action="forum-post.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
            <input type="hidden" name="action" value="vote" />
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>" />
            <input type="hidden" name="vote" value="1" />
            <button type="submit" class="link">Like</button>
          </form>
        </p>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="gap"></div>
</div>
<?php rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]); ?>
