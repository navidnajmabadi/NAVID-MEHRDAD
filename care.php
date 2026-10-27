<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/views/health_dashboard.php';

// Health dashboard for the current pet (brief §A + manual dashboards).
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
if (!$pets) {
    rrp_redirect('si-chat.php?new=1');
}
$petId = rrp_page_pet_id($owner, $pets);
$pet = rrp_pet_by_id($petId, (int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

$newLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'share') {
        $r = rrp_share_create($owner, $pet);
        if (!empty($r['ok'])) {
            $_SESSION['share_once'] = $r['url'];
        } else {
            rrp_flash_set('error', $r['error'] ?? 'Could not create the link.');
        }
    } elseif ($action === 'revoke') {
        rrp_share_revoke($owner, (int) ($_POST['id'] ?? 0));
        rrp_flash_set('ok', 'Link turned off.');
    }
    rrp_redirect('care.php');
}
if (!empty($_SESSION['share_once'])) {
    $newLink = (string) $_SESSION['share_once'];
    unset($_SESSION['share_once']);
}

$vm = rrp_vm_health($owner, $pet);
$thread = rrp_sipo_thread_for_pet((int) $owner['id'], (int) $pet['id']);
$links = rrp_share_list((int) $pet['id']);
$csrf = rrp_csrf_token();

rrp_layout_head('Health — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'health']);
?>
<div class="topbar care-top">
  <div class="center" style="text-align:left"><h2>Health</h2></div>
  <?php rrp_pet_switcher(rrp_vm_pet_switcher($owner)); ?>
</div>

<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>
  <nav class="daily-tabs" aria-label="Health sections">
    <a href="care.php" aria-current="page">Health</a><a href="daily.php">Daily</a>
  </nav>

  <div class="care-actions">
    <label class="btn primary care-upload">
      <input type="file" id="careFile" accept="image/jpeg,image/png,image/webp,application/pdf" hidden />
      <svg aria-hidden="true"><use href="#i-doc"/></svg><span>Add lab report or label</span>
    </label>
    <a class="btn outline" href="si-chat.php">Ask SIPO</a>
    <a class="btn outline" href="health-care.php">Edit records</a>
  </div>
  <p class="meta" id="careUploadNote" aria-live="polite">Photo or PDF, up to 10 MB. SIPO saves the numbers and deletes the file.</p>

  <?php rrp_view_health_dashboard($vm, false); ?>

  <section class="care-card">
    <h3 class="label">Share with your vet</h3>
    <p class="sub">A private, read-only link to this page. Works for <?= RRP_SHARE_DAYS ?> days; turn it off anytime.</p>
    <?php if ($newLink): ?>
      <div class="care-sharebox">
        <input type="text" readonly value="<?= rrp_h($newLink) ?>" id="shareUrl" aria-label="Share link" />
        <button type="button" class="btn primary" id="copyShare">Copy</button>
      </div>
      <p class="meta">Copy it now — for privacy it is shown only once.</p>
    <?php endif; ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="action" value="share" />
      <button class="btn outline" type="submit">Create vet link</button></form>
    <?php foreach ($links as $l): ?>
      <form method="post" class="care-linkrow">
        <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="action" value="revoke" /><input type="hidden" name="id" value="<?= (int) $l['id'] ?>" />
        <span class="meta">Link from <?= rrp_h(substr((string) $l['created_at'], 0, 10)) ?> · opened <?= (int) $l['view_count'] ?>× · until <?= rrp_h(substr((string) $l['expires_at'], 0, 10)) ?></span>
        <button class="link" type="submit">Turn off</button>
      </form>
    <?php endforeach; ?>
  </section>
</div>
<script>
(function(){
  var input = document.getElementById('careFile');
  var note = document.getElementById('careUploadNote');
  input && input.addEventListener('change', function(){
    var f = input.files && input.files[0];
    if (!f) return;
    if (f.size > 10 * 1024 * 1024) { note.textContent = 'That file is over 10 MB.'; return; }
    var fd = new FormData();
    fd.append('csrf', <?= json_encode($csrf) ?>);
    fd.append('thread_id', <?= (int) ($thread['id'] ?? 0) ?>);
    fd.append('file', f);
    note.textContent = 'SIPO is reading it…';
    fetch(<?= json_encode(rrp_asset('api/si/upload.php')) ?>, {method:'POST', credentials:'same-origin', body: fd})
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j.ok) { note.textContent = j.error || 'Could not read that file.'; return; }
        note.textContent = 'Saved. SIPO replied in your chat.';
        setTimeout(function(){ location.reload(); }, 600);
      })
      .catch(function(){ note.textContent = 'Upload failed. Check your connection.'; });
  });
  var copy = document.getElementById('copyShare');
  copy && copy.addEventListener('click', function(){
    var u = document.getElementById('shareUrl');
    u.select();
    (navigator.clipboard ? navigator.clipboard.writeText(u.value) : Promise.reject()).then(function(){ copy.textContent = 'Copied'; }, function(){ document.execCommand('copy'); copy.textContent = 'Copied'; });
  });
})();
</script>
<?php
rrp_app_end(['nav' => 'health', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
