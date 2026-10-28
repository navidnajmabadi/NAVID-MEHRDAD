<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$id = (int) ($_GET['id'] ?? $_POST['post_id'] ?? 0);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'vote') {
        rrp_forum_vote((int) $owner['id'], (int) ($_POST['post_id'] ?? 0), (int) ($_POST['vote'] ?? 0));
        rrp_redirect('forum-post.php?id=' . (int) ($_POST['post_id'] ?? 0));
    }
    if ($action === 'comment') {
        $result = rrp_forum_add_comment((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0), $id, (string) ($_POST['body'] ?? ''));
        if (empty($result['ok'])) {
            $error = $result['error'] ?? 'Could not comment.';
        } else {
            rrp_redirect('forum-post.php?id=' . $id);
        }
    }
    if ($action === 'report') {
        $result = rrp_forum_report((int) $owner['id'], 'post', $id, (string) ($_POST['reason'] ?? 'abuse'), (string) ($_POST['detail'] ?? ''));
        rrp_flash_set(!empty($result['ok']) ? 'ok' : 'error', !empty($result['ok']) ? 'Report submitted.' : ($result['error'] ?? 'Failed'));
        rrp_redirect('forum-post.php?id=' . $id);
    }
}

$post = rrp_forum_post_by_id($id, (int) $owner['id']);
if (!$post || ($post['moderation_status'] !== 'visible' && (int) $post['owner_id'] !== (int) $owner['id'])) {
    rrp_flash_set('error', 'Post not found.');
    rrp_redirect('forum.php');
}
$comments = rrp_forum_comments($id);
$nav = rrp_owner_nav_context((int) $owner['id']);

rrp_layout_head('Post — SIPAW');
rrp_app_start(['nav' => 'social']);
?>
<div class="topbar">
  <a class="iconbtn" href="forum.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2><?= rrp_h($post['pet_name'] ?? 'Post') ?></h2></div>
  <span></span>
</div>
<div class="pad stack" style="gap:16px">
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error"><?= rrp_h($error) ?></div><?php endif; ?>
  <div class="box">
    <p class="label"><?= rrp_h($post['pet_name']) ?></p>
    <?php if (!empty($post['image_path'])): ?><img src="<?= rrp_h(rrp_asset((string) $post['image_path'])) ?>" alt="" style="width:100%;height:auto;margin-top:10px;border:1px solid var(--line)" /><?php endif; ?>
    <p style="margin-top:10px;white-space:pre-wrap"><?= rrp_h($post['body']) ?></p>
    <p class="sub data" style="margin-top:10px">+<?= (int) $post['like_count'] ?> · −<?= (int) $post['dislike_count'] ?></p>
    <div class="row" style="gap:8px;margin-top:12px;flex-wrap:wrap">
      <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="action" value="vote" /><input type="hidden" name="post_id" value="<?= $id ?>" /><input type="hidden" name="vote" value="1" /><button class="btn outline" type="submit">Like</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="action" value="vote" /><input type="hidden" name="post_id" value="<?= $id ?>" /><input type="hidden" name="vote" value="-1" /><button class="btn outline" type="submit">Dislike</button></form>
    </div>
  </div>

  <div class="stack" style="gap:10px">
    <p class="label">Comments</p>
    <?php foreach ($comments as $c): ?>
      <div class="box"><p class="label"><?= rrp_h($c['pet_name'] ?? '') ?></p><p class="sub" style="margin-top:8px"><?= rrp_h($c['body']) ?></p></div>
    <?php endforeach; ?>
  </div>

  <?php if ($pets): ?>
  <form method="post" class="stack" style="gap:10px">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <input type="hidden" name="action" value="comment" />
    <select name="pet_id" style="min-height:44px;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px">
      <?php foreach ($pets as $p): ?><option value="<?= (int) $p['id'] ?>"><?= rrp_h($p['name']) ?></option><?php endforeach; ?>
    </select>
    <div class="field"><div class="in area"><textarea name="body" maxlength="500" rows="2" required placeholder="Comment as pet"></textarea></div></div>
    <button class="btn primary" type="submit">Comment</button>
  </form>
  <?php endif; ?>

  <form method="post" class="box stack" style="gap:10px">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <input type="hidden" name="action" value="report" />
    <p class="label">Report</p>
    <select name="reason" style="min-height:44px;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px">
      <option value="porn">Sexual content</option>
      <option value="drugs">Drugs</option>
      <option value="fraud">Scam or fraud</option>
      <option value="spam">Spam or ads</option>
      <option value="abuse">Bullying or hate</option>
      <option value="animal_harm">Animal cruelty</option>
      <option value="other">Something else</option>
    </select>
    <div class="field"><div class="in area"><textarea name="detail" rows="2" maxlength="500" placeholder="Optional detail"></textarea></div></div>
    <button class="btn outline" type="submit">Submit report</button>
  </form>
  <div class="gap"></div>
</div>
<?php rrp_app_end(['nav' => 'social', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]); ?>
