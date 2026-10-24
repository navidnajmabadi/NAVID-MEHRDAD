<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$nav = rrp_owner_nav_context((int) $owner['id']);
$threadId = isset($_GET['thread']) ? (int) $_GET['thread'] : 0;

$threads = [];
$thread = null;
$messages = [];
$dbReady = true;

try {
    $threads = rrp_chat_threads_for_owner((int) $owner['id']);
    if ($threadId) {
        $thread = rrp_chat_thread_by_id($threadId);
        if (!$thread || !rrp_chat_owner_can_access($thread, (int) $owner['id'])) {
            rrp_flash_set('error', 'Chat not found.');
            rrp_redirect('chat.php');
        }
        $messages = rrp_chat_messages((int) $thread['id']);
    }
} catch (Throwable $e) {
    error_log('chat.php: ' . $e->getMessage());
    $dbReady = false;
}

$finderLabel = 'Finder';
if ($thread) {
    $fn = trim((string) ($thread['finder_name'] ?? ''));
    $finderLabel = $fn !== '' ? $fn : 'Finder';
}
$av = strtoupper(mb_substr($finderLabel, 0, 1));
$lastId = $messages ? (int) $messages[count($messages) - 1]['id'] : 0;

rrp_layout_head($thread ? ('Chat — ' . $finderLabel . ' — siPaw') : 'Chat — siPaw');
rrp_app_start(['nav' => 'chat', 'class' => $thread ? 'chat-active' : '']);
?>
<?php if ($thread): ?>
<div class="chat-screen">
  <div class="topbar">
    <a class="iconbtn" href="chat.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
    <div class="center"><h2>Finder chat</h2></div>
    <span style="width:40px"></span>
  </div>

  <div class="chathead">
    <sipo-mascot id="sipo" class="sipo-chat" state="idle" theme="ice-blue" size="48" animated="true" label="Sipo, SIPaw assistant"></sipo-mascot>
    <span class="av"><?= rrp_h($av) ?></span>
    <div>
      <b><?= rrp_h($finderLabel) ?></b>
      <small>About <?= rrp_h((string) $thread['pet_name']) ?><?= !empty($thread['scan_id']) ? ' · scan #' . (int) $thread['scan_id'] : '' ?></small>
    </div>
    <span class="status live data">Open</span>
  </div>

  <p class="privacy">
    <svg aria-hidden="true"><use href="#i-shield"/></svg>
    Phone numbers stay private. Location is shared only if you agree.
  </p>

  <div class="msgs" id="chatMsgs">
    <?php if (!$messages): ?>
      <p class="chat-empty sub">No messages yet. Reply when the finder writes, or say hello.</p>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <div class="msg <?= $m['sender_role'] === 'owner' ? 'me' : 'them' ?>" id="msg-<?= (int) $m['id'] ?>">
          <?= rrp_h((string) $m['body']) ?>
          <time><?= rrp_h(substr((string) $m['created_at'], 11, 5)) ?></time>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="quick" id="chatQuick">
    <button type="button" data-quick="On my way">On my way</button>
    <button type="button" data-quick="Keep them safe">Keep them safe</button>
    <button type="button" data-quick="What’s the magic word?">What’s the magic word?</button>
  </div>

  <form class="composer" id="chatComposer" aria-label="Message composer">
    <input id="chatInput" type="text" name="body" maxlength="2000" placeholder="Message <?= rrp_h($finderLabel) ?>" autocomplete="off" aria-label="Message" />
    <button type="submit" class="send" id="chatSend" aria-label="Send"><svg aria-hidden="true"><use href="#i-send"/></svg></button>
  </form>
</div>
<script>
window.SIPO_ASSET_BASE = <?= json_encode(rtrim(rrp_asset('assets/mascot/public'), '/')) ?>;
window.RRP_CHAT = {
  threadId: <?= (int) $thread['id'] ?>,
  role: "owner",
  csrf: <?= json_encode(rrp_csrf_token()) ?>,
  lastId: <?= (int) $lastId ?>,
  apiUrl: <?= json_encode(rrp_asset('api/chat.php')) ?>
};
</script>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<script src="<?= rrp_h(rrp_asset('assets/js/chat.js', true)) ?>" defer></script>
<?php else: ?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Chat</h2></div>
  <span style="width:40px"></span>
</div>

<?php rrp_render_flash(); ?>

<div class="pad stack" style="gap:10px;padding-top:12px">
  <?php if (!$dbReady): ?>
    <div class="box">
      <p style="margin:0 0 6px;font-weight:500">Chat tables not installed yet</p>
      <p class="sub" style="margin:0;font-size:13px">
        Run <span class="data">sql/004_chat.sql</span> in Hostinger phpMyAdmin, then refresh.
      </p>
    </div>
  <?php elseif (!$threads): ?>
    <div class="box">
      <p style="margin:0 0 6px;font-weight:500">No finder chats yet</p>
      <p class="sub" style="margin:0;font-size:13px">
        When someone scans a tag and taps Message owner, the thread appears here.
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($threads as $t):
      $who = trim((string) ($t['finder_name'] ?? ''));
      $who = $who !== '' ? $who : 'Finder';
      $preview = trim((string) ($t['last_body'] ?? ''));
      $when = (string) ($t['last_message_at'] ?? $t['created_at'] ?? '');
    ?>
      <a class="event" href="chat.php?thread=<?= (int) $t['id'] ?>">
        <span class="d on"></span>
        <div>
          <b><?= rrp_h($who) ?> · <?= rrp_h((string) $t['pet_name']) ?></b>
          <small><?= rrp_h($preview !== '' ? rrp_clip($preview, 80) : 'Open thread') ?></small>
        </div>
        <time><?= rrp_h($when !== '' ? substr($when, 5, 11) : '') ?></time>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="gap"></div>
</div>
<?php endif; ?>
<?php
rrp_app_end(['nav' => 'chat', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
