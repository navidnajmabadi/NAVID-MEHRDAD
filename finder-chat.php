<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$scanId = (int) ($_GET['scan'] ?? 0);
$publicId = trim((string) ($_GET['pet'] ?? ''));
$error = null;
$thread = null;
$messages = [];
$pet = null;
$ownerName = 'Owner';

rrp_start_session();
rrp_finder_token();

try {
    if ($scanId <= 0 && $publicId !== '') {
        $pet = rrp_pet_by_public_id($publicId);
        if ($pet) {
            $seenKey = 'scanned_' . $pet['public_id'];
            if (!empty($_SESSION[$seenKey])) {
                $scanId = (int) $_SESSION[$seenKey];
            } else {
                $created = rrp_record_scan((int) $pet['id'], 'none');
                $scanId = (int) $created['scan_id'];
                $_SESSION[$seenKey] = $scanId;
                $ownerStmt = rrp_db()->prepare('SELECT * FROM owners WHERE id = ? LIMIT 1');
                $ownerStmt->execute([(int) $pet['owner_id']]);
                $own = $ownerStmt->fetch();
                if ($own && $own['status'] === 'active') {
                    rrp_notify_scan($pet, $own, $scanId, 'none');
                }
            }
        }
    }

    if ($scanId > 0) {
        $name = isset($_GET['name']) ? trim((string) $_GET['name']) : null;
        $result = rrp_chat_start_for_scan($scanId, $name !== '' ? $name : null);
        if (!empty($result['ok'])) {
            $thread = rrp_chat_thread_by_id((int) $result['thread']['id']);
            if ($thread) {
                $messages = rrp_chat_messages((int) $thread['id']);
                $pet = rrp_pet_by_id((int) $thread['pet_id']);
                $st = rrp_db()->prepare('SELECT name FROM owners WHERE id = ? LIMIT 1');
                $st->execute([(int) $thread['owner_id']]);
                $ownerName = (string) ($st->fetchColumn() ?: 'Owner');
            }
        } else {
            $error = $result['error'] ?? 'Could not open chat.';
        }
    } else {
        $error = 'Missing scan. Open the pet tag page first, then tap Message owner.';
    }
} catch (Throwable $e) {
    error_log('finder-chat.php: ' . $e->getMessage());
    $error = 'Chat is not available yet. The owner may still need to run sql/004_chat.sql.';
}

$petName = $pet ? (string) $pet['name'] : 'pet';
$lastId = $messages ? (int) $messages[count($messages) - 1]['id'] : 0;
$av = strtoupper(mb_substr($ownerName, 0, 1));

rrp_layout_head('Message owner — SIPAW', ['body_class' => 'page-finder-chat']);
rrp_app_start(['nav' => false, 'class' => 'chat-active']);
?>
<div class="chat-screen">
  <div class="topbar">
    <?php if ($pet): ?>
      <a class="iconbtn" href="pet.php?id=<?= rrp_h(rawurlencode((string) $pet['public_id'])) ?>" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
    <?php else: ?>
      <a class="iconbtn" href="index.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
    <?php endif; ?>
    <div class="center"><h2>Message owner</h2></div>
    <span style="width:40px"></span>
  </div>

  <?php if ($error || !$thread): ?>
    <div class="pad"><div class="box"><p class="sub" style="margin:0"><?= rrp_h($error ?: 'Chat unavailable.') ?></p></div></div>
  <?php else: ?>
    <div class="chathead">
      <sipo-mascot id="sipo" class="sipo-chat" state="idle" theme="ice-blue" size="48" animated="true" label="Sipo, SIPaw assistant"></sipo-mascot>
      <span class="av"><?= rrp_h($av) ?></span>
      <div>
        <b><?= rrp_h($ownerName) ?></b>
        <small>About <?= rrp_h($petName) ?></small>
      </div>
      <span class="status live data">Open</span>
    </div>

    <p class="privacy">
      <svg aria-hidden="true"><use href="#i-shield"/></svg>
      You can message without sharing your phone number. Keep <?= rrp_h($petName) ?> calm and safe.
    </p>

    <div class="msgs" id="chatMsgs">
      <?php if (!$messages): ?>
        <p class="chat-empty sub">Say hello — the owner gets a notification when you send.</p>
      <?php else: ?>
        <?php foreach ($messages as $m): ?>
          <div class="msg <?= $m['sender_role'] === 'finder' ? 'me' : 'them' ?>" id="msg-<?= (int) $m['id'] ?>">
            <?= rrp_h((string) $m['body']) ?>
            <time><?= rrp_h(substr((string) $m['created_at'], 11, 5)) ?></time>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="quick" id="chatQuick">
      <button type="button" data-quick="I found <?= rrp_h($petName) ?>">I found <?= rrp_h($petName) ?></button>
      <button type="button" data-quick="They seem safe">They seem safe</button>
      <button type="button" data-quick="Where should we meet?">Where should we meet?</button>
    </div>

    <form class="composer" id="chatComposer" aria-label="Message composer">
      <input id="chatInput" type="text" maxlength="2000" placeholder="Message <?= rrp_h($ownerName) ?>" autocomplete="off" aria-label="Message" />
      <button type="submit" class="send" id="chatSend" aria-label="Send"><svg aria-hidden="true"><use href="#i-send"/></svg></button>
    </form>

    <script>
    window.SIPO_ASSET_BASE = <?= json_encode(rtrim(rrp_asset('assets/mascot/public'), '/')) ?>;
    window.RRP_CHAT = {
      threadId: <?= (int) $thread['id'] ?>,
      role: "finder",
      csrf: <?= json_encode(rrp_csrf_token()) ?>,
      lastId: <?= (int) $lastId ?>,
      apiUrl: <?= json_encode(rrp_asset('api/chat.php')) ?>
    };
    </script>
    <script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
    <script src="<?= rrp_h(rrp_asset('assets/js/chat.js', true)) ?>" defer></script>
  <?php endif; ?>
</div>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
