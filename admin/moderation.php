<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// Boards moderation: hidden (3+ reports) and removed posts/comments, per-user log, suspensions.
$admin = rrp_require_admin();
$adminId = (int) $admin['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $type = ($_POST['type'] ?? 'post') === 'comment' ? 'comment' : 'post';
    $id = (int) ($_POST['id'] ?? 0);
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'restore') {
        rrp_mod_restore($type, $id, $adminId);
    } elseif ($act === 'remove') {
        $table = $type === 'comment' ? 'forum_comments' : 'forum_posts';
        $row = rrp_db()->query("SELECT owner_id, body FROM {$table} WHERE id = {$id}")->fetch();
        if ($row) {
            rrp_mod_remove($type, $id, (int) $row['owner_id'], 'Removed after review', 'admin', [], (string) $row['body'], $adminId);
            rrp_db()->prepare("UPDATE forum_reports SET status = 'resolved', resolved_at = UTC_TIMESTAMP() WHERE target_type = ? AND target_id = ?")->execute([$type, $id]);
        }
    } elseif ($act === 'unsuspend') {
        rrp_mod_unsuspend((int) ($_POST['owner_id'] ?? 0), $adminId);
    }
    rrp_redirect('moderation.php' . (isset($_GET['owner']) ? '?owner=' . (int) $_GET['owner'] : ''));
}

$ownerFilter = (int) ($_GET['owner'] ?? 0);
$queue = rrp_db()->query(
    "SELECT 'post' AS type, p.id, p.owner_id, p.body, p.image_path, p.moderation_status, p.removed_reason, p.created_at, o.email, pets.name AS pet_name,
            (SELECT COUNT(*) FROM forum_reports r WHERE r.target_type = 'post' AND r.target_id = p.id) AS reports
     FROM forum_posts p JOIN owners o ON o.id = p.owner_id JOIN pets ON pets.id = p.pet_id
     WHERE p.moderation_status IN ('hidden','removed') AND COALESCE(p.removed_reason,'') <> 'Deleted by author'
     UNION ALL
     SELECT 'comment', c.id, c.owner_id, c.body, NULL, c.moderation_status, c.removed_reason, c.created_at, o.email, pets.name,
            (SELECT COUNT(*) FROM forum_reports r WHERE r.target_type = 'comment' AND r.target_id = c.id)
     FROM forum_comments c JOIN owners o ON o.id = c.owner_id JOIN pets ON pets.id = c.pet_id
     WHERE c.moderation_status IN ('hidden','removed')
     ORDER BY moderation_status = 'hidden' DESC, created_at DESC LIMIT 60"
)->fetchAll();
$suspended = rrp_db()->query("SELECT id, email, nickname, suspended_reason, suspended_at FROM owners WHERE status = 'suspended' ORDER BY suspended_at DESC LIMIT 50")->fetchAll();
$log = [];
if ($ownerFilter) {
    $st = rrp_db()->prepare('SELECT * FROM moderation_log WHERE owner_id = ? ORDER BY id DESC LIMIT 100');
    $st->execute([$ownerFilter]);
    $log = $st->fetchAll();
}
$csrf = rrp_csrf_token();
rrp_admin_shell_start('Moderation', 'moderation');
?>
  <h1>Moderation</h1>
  <p class="lede">AI removes porn, drugs, fraud and junk. 3 reports hide a post for review. More than <?= RRP_MOD_SUSPEND_AFTER ?> removals suspends the account and opens a Support ticket.</p>

  <?php if ($suspended): ?>
    <h2>Suspended accounts</h2>
    <?php foreach ($suspended as $s): ?>
      <div class="panel panel-soft" style="margin-bottom:8px">
        <strong><?= rrp_h($s['nickname'] ?: $s['email']) ?></strong> · <?= rrp_h($s['email']) ?>
        <div class="meta"><?= rrp_h((string) $s['suspended_reason']) ?> · <?= rrp_h((string) $s['suspended_at']) ?> UTC · removals <?= rrp_mod_removal_count((int) $s['id']) ?></div>
        <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="unsuspend" /><input type="hidden" name="owner_id" value="<?= (int) $s['id'] ?>" />
          <button class="btn outline" type="submit" style="width:auto">Unsuspend</button></form>
        <a class="link" href="?owner=<?= (int) $s['id'] ?>">See log</a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($ownerFilter): ?>
    <h2>Log for owner #<?= $ownerFilter ?> · <?= rrp_mod_removal_count($ownerFilter) ?> standing removals</h2>
    <?php foreach ($log as $l): ?>
      <div class="meta" style="padding:6px 0;border-bottom:1px solid var(--line)"><?= rrp_h((string) $l['created_at']) ?> · <b><?= rrp_h($l['action']) ?></b> <?= rrp_h($l['target_type']) ?> #<?= (int) $l['target_id'] ?> by <?= rrp_h($l['source']) ?> · <?= rrp_h($l['reason']) ?><?= $l['excerpt'] ? ' · “' . rrp_h(mb_substr((string) $l['excerpt'], 0, 80)) . '”' : '' ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2>Queue</h2>
  <?php if (!$queue): ?><p class="meta">Nothing to review 🎉</p><?php endif; ?>
  <?php foreach ($queue as $q): ?>
    <div class="panel panel-soft" style="margin-bottom:8px;border-color:<?= $q['moderation_status'] === 'hidden' ? 'var(--blue)' : 'var(--line)' ?>">
      <span class="pill"><?= rrp_h($q['moderation_status']) ?></span> <span class="pill"><?= rrp_h($q['type']) ?></span>
      <strong><?= rrp_h($q['pet_name']) ?></strong> · <a class="link" href="?owner=<?= (int) $q['owner_id'] ?>"><?= rrp_h($q['email']) ?></a>
      <p style="margin:8px 0;white-space:pre-wrap"><?= rrp_h((string) $q['body']) ?></p>
      <?php if ($q['image_path'] && $q['moderation_status'] === 'hidden'): ?><img src="../<?= rrp_h($q['image_path']) ?>" alt="" style="max-width:220px" /><?php endif; ?>
      <div class="meta"><?= rrp_h((string) ($q['removed_reason'] ?: 'Reported')) ?> · reports <?= (int) $q['reports'] ?> · <?= rrp_h((string) $q['created_at']) ?> UTC</div>
      <form method="post" style="display:flex;gap:8px;margin-top:8px">
        <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="type" value="<?= rrp_h($q['type']) ?>" /><input type="hidden" name="id" value="<?= (int) $q['id'] ?>" />
        <button class="btn outline" name="act" value="restore" type="submit" style="width:auto">Restore</button>
        <?php if ($q['moderation_status'] === 'hidden'): ?><button class="btn primary" name="act" value="remove" type="submit" style="width:auto">Remove</button><?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
<?php
rrp_admin_shell_end();
