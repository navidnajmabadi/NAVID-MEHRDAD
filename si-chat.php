<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// One chat per pet — the heart of SIPAW. ?new=1 opens a new-pet onboarding chat.
$owner = rrp_require_owner();
if (!empty($_GET['pet'])) {
    // Push notification links open the pet they are about.
    $linked = rrp_pet_by_id((int) $_GET['pet'], (int) $owner['id']);
    if ($linked && (int) ($linked['is_active'] ?? 1) === 1) {
        rrp_sipo_set_current_pet((int) $owner['id'], (int) $linked['id']);
    }
}
$vm = rrp_vm_chat($owner, !empty($_GET['new']));
$nav = rrp_owner_nav_context((int) $owner['id']);
$csrf = rrp_csrf_token();
$petName = $vm['thread']['pet']['name'] ?? null;

rrp_layout_head('SIPAW', [
    'description' => 'SIPO — your pet\'s SI companion.',
    'body_class' => 'page-sipo',
]);
rrp_app_start(['nav' => 'chat', 'class' => 'chat-active app-wide']);
?>
<div class="sipo-shell">
  <section class="sipo-chat" aria-label="Chat with SIPO">
    <header class="sipo-top">
      <div class="sipo-who">
        <sipo-mascot id="sipoFace" state="idle" theme="ice-blue" size="64" animated="true" label="SIPO" class="sipo-face-lg"></sipo-mascot>
        <div>
          <h1 class="sipo-title">SIPO</h1>
          <p class="sipo-status" id="sipoStatus" aria-live="polite">
            <?= $petName ? 'Looking after ' . rrp_h($petName) : 'Let’s meet your pet' ?>
          </p>
          <?php $pg = $vm['thread']['progress']; ?>
          <div class="sipo-progress" id="sipoProgress" <?= $pg && $pg['done'] < $pg['total'] ? '' : 'hidden' ?>
            role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) ($pg['total'] ?? 7) ?>" aria-valuenow="<?= (int) ($pg['done'] ?? 0) ?>" aria-label="Profile progress">
            <span style="width:<?= (int) ($pg['percent'] ?? 0) ?>%"></span>
            <small>Setup <b><?= (int) ($pg['done'] ?? 0) ?>/<?= (int) ($pg['total'] ?? 4) ?></b> <em data-setup-section><?= !empty($pg['section']) ? '· ' . rrp_h($pg['section']) : '' ?></em></small>
          </div>
        </div>
      </div>
      <div class="sipo-top-right"><?php rrp_coin_badge($owner); ?><?php rrp_pet_switcher($vm['switcher'], $vm['thread']['pet']); ?></div>
    </header>

    <?php
    $openLost = null;
    if (!empty($vm['thread']['pet']['id'])) {
        try {
            $st = rrp_db()->prepare("SELECT id FROM lost_alerts WHERE pet_id = ? AND status = 'open' LIMIT 1");
            $st->execute([(int) $vm['thread']['pet']['id']]);
            $openLost = $st->fetchColumn() ?: null;
        } catch (Throwable $e) {
        }
    }
    ?>
    <?php if ($openLost): ?>
      <a class="lost-banner" href="lost.php?pet=<?= (int) $vm['thread']['pet']['id'] ?>">🔴 <?= rrp_h($petName) ?> is in Lost mode. Home? Tap Found <span aria-hidden="true">›</span></a>
    <?php endif; ?>
    <?php if ($vm['note']): ?><div class="sipo-note" role="status"><?= rrp_h($vm['note']) ?></div><?php endif; ?>

    <div class="sipo-log" id="sipoLog" role="log" aria-live="polite" aria-label="Messages"></div>

    <div class="sipo-gate" id="sipoGate" role="status" hidden></div>

    <form class="sipo-composer" id="sipoForm" autocomplete="off">
      <?php if (!$vm['thread']['onboarding']): ?>
      <label class="sipo-attach" title="Send a photo or PDF (lab report, food label, prescription, pet photo)">
        <input type="file" id="sipoFile" accept="image/jpeg,image/png,image/webp,application/pdf" hidden />
        <svg aria-hidden="true"><use href="#i-camera"/></svg><span class="sr-only">Attach photo or PDF</span>
      </label>
      <?php endif; ?>
      <label for="sipoInput" class="sr-only">Message SIPO</label>
      <textarea id="sipoInput" rows="1" maxlength="4000" enterkeyhint="send"
        placeholder="<?= $petName ? 'Tell SIPO about ' . rrp_h($petName) . '…' : 'Say hi to SIPO…' ?>"></textarea>
      <button class="sipo-send" type="submit" aria-label="Send"><svg aria-hidden="true"><use href="#i-send"/></svg></button>
    </form>
  </section>

  <?php if ($vm['panel']): ?>
  <aside class="sipo-panel" aria-label="<?= rrp_h($petName) ?> at a glance">
    <div class="sipo-panel-card">
      <?php rrp_pet_avatar($vm['thread']['pet']); ?>
      <div><b><?= rrp_h($petName) ?></b><small><?= rrp_h($vm['thread']['pet']['kind']) ?></small></div>
    </div>
    <dl class="sipo-panel-facts">
      <?php foreach ($vm['panel']['profile'] as $row): ?>
        <div><dt><?= rrp_h($row['label']) ?></dt><dd><?= $row['value'] !== null ? rrp_h($row['value']) : '<span class="meta">Ask SIPO</span>' ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <p class="label" style="margin-top:20px">Coming up</p>
    <?php if ($vm['panel']['upcoming']): ?>
      <ul class="sipo-panel-list" role="list">
        <?php foreach ($vm['panel']['upcoming'] as $u): ?>
          <li><span class="data"><?= rrp_h($u['due']) ?></span> <?= rrp_h($u['title']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sub" style="font-size:13px">Nothing yet. SIPO will set reminders with you.</p>
    <?php endif; ?>
    <p class="meta sipo-panel-mem">SIPO remembers <?= (int) $vm['panel']['facts_count'] ?> <?= (int) $vm['panel']['facts_count'] === 1 ? 'thing' : 'things' ?> about <?= rrp_h($petName) ?>.</p>
  </aside>
  <?php endif; ?>
</div>

<script>
window.SIPO_VM = <?= json_encode($vm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.SIPO_API = {
  csrf: <?= json_encode($csrf) ?>,
  send: <?= json_encode(rrp_asset('api/si/send.php')) ?>,
  greet: <?= json_encode(rrp_asset('api/si/greet.php')) ?>,
  messages: <?= json_encode(rrp_asset('api/si/messages.php')) ?>,
  upload: <?= json_encode(rrp_asset('api/si/upload.php')) ?>,
  reminderFired: <?= json_encode(rrp_asset('api/si/reminder-fired.php')) ?>,
  switchPet: <?= json_encode(rrp_asset('api/pets/switch.php')) ?>
};
window.SIPO_ASSET_BASE = <?= json_encode(rtrim(rrp_asset('assets/mascot/public'), '/')) ?>;
</script>
<?php
rrp_app_end([
    'nav' => 'chat',
    'unread' => $nav['unread'],
    'lost_active' => $nav['lost_active'],
    'scripts' => ['assets/js/sipo.js', 'assets/js/sipo-chat.js'],
]);
