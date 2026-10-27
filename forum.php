<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Boards (brief §E): pet posts with one photo, likes/dislikes, follows, reports. ?pet=<public_id> = pet profile.
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
$profile = !empty($_GET['pet']) ? rrp_forum_pet_profile((string) $_GET['pet'], (int) $owner['id']) : null;
$scope = ($_GET['scope'] ?? 'all') === 'following' ? 'following' : 'all';
$posts = rrp_forum_feed(20, null, (int) $owner['id'], $profile ? 'all' : $scope, 0, $profile['id'] ?? null);
$current = rrp_sipo_current_pet($owner, $pets);
$csrf = rrp_csrf_token();

rrp_layout_head(($profile ? $profile['name'] . ' — ' : '') . 'Boards — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/boards.css', true)) . '" />',
    'body_class' => 'page-boards',
]);
rrp_app_start(['nav' => 'home']);
?>
<div class="topbar">
  <a class="iconbtn" href="<?= $profile ? 'forum.php' : 'dashboard.php' ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2><?= $profile ? rrp_h($profile['name']) : 'Boards' ?></h2></div>
  <a class="iconbtn" href="si-chat.php" aria-label="SIPO">SI</a>
</div>

<div class="pad stack boards">
  <?php rrp_render_flash(); ?>

  <?php if ($profile): ?>
    <section class="b-profile">
      <?php rrp_pet_avatar($profile); ?>
      <div class="b-profile-main">
        <h1><?= rrp_h($profile['name']) ?></h1>
        <p class="meta"><?= rrp_h($profile['kind']) ?><?= $profile['breed'] ? ' · ' . rrp_h($profile['breed']) : '' ?> · with <?= rrp_h((string) $profile['owner_nickname']) ?></p>
        <?php if ($profile['bio']): ?><p class="sub"><?= rrp_h($profile['bio']) ?></p><?php endif; ?>
        <p class="b-stats"><b class="data"><?= (int) $profile['posts'] ?></b> posts · <b class="data" id="followerCount"><?= (int) $profile['followers'] ?></b> followers</p>
      </div>
      <?php if (!$profile['is_mine']): ?>
        <button type="button" class="btn <?= $profile['following'] ? 'outline' : 'primary' ?> b-follow" data-follow="<?= (int) $profile['id'] ?>" data-on="<?= $profile['following'] ? '1' : '0' ?>"><?= $profile['following'] ? 'Following' : 'Follow' ?></button>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <?php if ($pets): ?>
      <form class="b-compose" id="composer" enctype="multipart/form-data">
        <div class="b-compose-top">
          <?php rrp_pet_avatar($current ? rrp_sipo_pet_brief($current) : null); ?>
          <select name="pet_id" aria-label="Post as">
            <?php foreach ($pets as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $current && (int) $current['id'] === (int) $p['id'] ? 'selected' : '' ?>>Post as <?= rrp_h($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <textarea name="body" maxlength="<?= RRP_FORUM_BODY_MAX ?>" rows="2" placeholder="What's <?= rrp_h($current['name'] ?? 'your pet') ?> up to? 🐾"></textarea>
        <img class="b-preview" id="preview" alt="" hidden />
        <div class="b-compose-bar">
          <label class="b-photo"><input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp" hidden /><svg aria-hidden="true"><use href="#i-camera"/></svg><span>Photo</span></label>
          <span class="meta" id="count">0/<?= RRP_FORUM_BODY_MAX ?></span>
          <button class="btn primary" type="submit" id="postBtn">Post</button>
        </div>
        <p class="meta b-note" id="composeNote" aria-live="polite">One photo per post. We shrink big photos and remove location data.</p>
      </form>
    <?php endif; ?>
    <nav class="daily-tabs b-tabs" aria-label="Feed">
      <a href="forum.php" <?= $scope === 'all' ? 'aria-current="page"' : '' ?>>For you</a>
      <a href="forum.php?scope=following" <?= $scope === 'following' ? 'aria-current="page"' : '' ?>>Following</a>
    </nav>
  <?php endif; ?>

  <div id="feed" class="b-feed" aria-live="polite"></div>
  <p class="sub b-empty" id="empty" hidden><?= $scope === 'following' && !$profile ? 'Follow pets you like and their posts land here.' : 'No posts yet. Be the first! 🐾' ?></p>
  <button type="button" class="btn outline" id="more" hidden>Load more</button>
</div>

<dialog class="petsheet" id="reportSheet" aria-label="Report post">
  <div class="petsheet-head"><span class="label">Report this post</span><button type="button" class="iconbtn" data-close aria-label="Close">×</button></div>
  <div class="b-report">
    <?php foreach (['porn' => 'Sexual content', 'drugs' => 'Drugs', 'fraud' => 'Scam or fraud', 'spam' => 'Spam or ads', 'abuse' => 'Bullying or hate', 'animal_harm' => 'Animal cruelty', 'other' => 'Something else'] as $k => $v): ?>
      <button type="button" class="petsheet-row" data-reason="<?= rrp_h($k) ?>"><span><b><?= rrp_h($v) ?></b></span></button>
    <?php endforeach; ?>
  </div>
</dialog>

<script>
window.BOARDS = {
  csrf: <?= json_encode($csrf) ?>,
  me: <?= (int) $owner['id'] ?>,
  scope: <?= json_encode($profile ? 'all' : $scope) ?>,
  pet: <?= json_encode($profile['public_id'] ?? null) ?>,
  posts: <?= json_encode(array_map('rrp_forum_present', $posts), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  api: { post: <?= json_encode(rrp_asset('api/forum/post.php')) ?>, feed: <?= json_encode(rrp_asset('api/forum/feed.php')) ?> }
};
</script>
<?php
rrp_app_end(['nav' => 'home', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active'], 'scripts' => ['assets/js/boards.js']]);
