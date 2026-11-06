<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// Every error the app hit (server crashes, warnings, browser errors, failed requests), newest first.
$admin = rrp_require_admin();
$tab = ($_GET['tab'] ?? 'open') === 'fixed' ? 'fixed' : 'open';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $act = (string) ($_POST['act'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($act === 'fix') {
        rrp_db()->prepare('UPDATE app_errors SET resolved = 1 WHERE id = ?')->execute([$id]);
    } elseif ($act === 'reopen') {
        rrp_db()->prepare('UPDATE app_errors SET resolved = 0 WHERE id = ?')->execute([$id]);
    } elseif ($act === 'fix_all') {
        rrp_db()->exec('UPDATE app_errors SET resolved = 1 WHERE resolved = 0');
    } elseif ($act === 'test') {
        $code = rrp_ops_record_error('test', 'Test error from Admin → Errors (safe to mark fixed)', 'admin/errors.php', 1, 'created by the Send test error button');
        rrp_flash_set('success', 'Test error stored. Its code is ' . $code . '.');
    }
    rrp_redirect('errors.php' . ($tab === 'fixed' ? '?tab=fixed' : ''));
}

$rows = [];
$counts = ['open' => 0, 'day' => 0, 'fixed' => 0];
try {
    $db = rrp_db();
    $counts['open'] = (int) $db->query('SELECT COUNT(*) FROM app_errors WHERE resolved = 0')->fetchColumn();
    $counts['fixed'] = (int) $db->query('SELECT COUNT(*) FROM app_errors WHERE resolved = 1')->fetchColumn();
    $counts['day'] = (int) $db->query('SELECT COUNT(*) FROM app_errors WHERE resolved = 0 AND last_seen > (UTC_TIMESTAMP() - INTERVAL 1 DAY)')->fetchColumn();
    $rows = $db->query('SELECT * FROM app_errors WHERE resolved = ' . ($tab === 'fixed' ? '1' : '0') . ' ORDER BY last_seen DESC LIMIT 100')->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}
$log = rrp_ops_php_log_tail(40);
$csrf = rrp_csrf_token();
$logPath = rrp_ops_log_path();

rrp_admin_shell_start('Errors', 'errors');
?>
  <h1>Errors</h1>
  <p class="lede">Everything that broke, newest first. Press <b>Copy</b> on an error and paste it to whoever fixes it: it has the code, the page, the line and the trace.</p>

  <div class="kpis">
    <div class="kpi <?= $counts['open'] > 0 ? 'is-bad' : '' ?>"><b><?= $counts['open'] ?></b><span>Open</span></div>
    <div class="kpi"><b><?= $counts['day'] ?></b><span>Seen in 24 h</span></div>
    <div class="kpi"><b><?= $counts['fixed'] ?></b><span>Marked fixed</span></div>
  </div>

  <div class="nav-links" style="margin:14px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a href="?tab=open"<?= $tab === 'open' ? ' class="is-active" style="text-decoration:underline"' : '' ?>>Open (<?= $counts['open'] ?>)</a>
    <a href="?tab=fixed"<?= $tab === 'fixed' ? ' class="is-active" style="text-decoration:underline"' : '' ?>>Fixed (<?= $counts['fixed'] ?>)</a>
    <?php if ($tab === 'open' && $rows): ?><button class="ad-btn" type="button" id="copyAll">Copy all open</button><?php endif; ?>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="test" /><button class="ad-btn" type="submit">Send test error</button></form>
    <?php if ($tab === 'open' && $rows): ?><form method="post" style="display:inline" onsubmit="return confirm('Mark every open error as fixed?')"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="fix_all" /><button class="ad-btn" type="submit">Mark all fixed</button></form><?php endif; ?>
  </div>

  <?php foreach ($rows as $e): $text = rrp_ops_error_text($e); ?>
    <section class="dash-card err-card" style="margin-bottom:10px">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <b class="mono" style="letter-spacing:.08em"><?= rrp_h(rrp_ops_error_code((string) $e['fingerprint'])) ?></b>
        <span class="pill"><?= rrp_h((string) $e['level']) ?></span>
        <span class="meta"><?= (int) $e['hits'] ?>× · last <?= rrp_h(substr((string) $e['last_seen'], 0, 16)) ?> UTC · first <?= rrp_h(substr((string) $e['first_seen'], 0, 16)) ?></span>
        <span style="margin-left:auto;display:flex;gap:8px">
          <button class="ad-btn err-copy" type="button" data-text="<?= rrp_h($text) ?>">Copy</button>
          <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="<?= $tab === 'fixed' ? 'reopen' : 'fix' ?>" /><input type="hidden" name="id" value="<?= (int) $e['id'] ?>" /><button class="ad-btn" type="submit"><?= $tab === 'fixed' ? 'Reopen' : 'Fixed' ?></button></form>
        </span>
      </div>
      <p class="mono" style="margin:8px 0 4px;word-break:break-word"><?= rrp_h((string) $e['message']) ?></p>
      <p class="meta" style="margin:0"><?= rrp_h((string) $e['file']) ?><?= $e['line'] ? ':' . (int) $e['line'] : '' ?> · page <?= rrp_h((string) $e['url']) ?><?= !empty($e['owner_id']) ? ' · owner #' . (int) $e['owner_id'] : '' ?></p>
      <?php if (!empty($e['trace'])): ?><details style="margin-top:6px"><summary class="meta">Trace</summary><pre class="mono" style="white-space:pre-wrap;font-size:12px"><?= rrp_h((string) $e['trace']) ?></pre></details><?php endif; ?>
    </section>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="empty"><?= $tab === 'fixed' ? 'Nothing marked fixed yet.' : 'No open errors. 🎉 If something looks broken, press "Send test error" to check that capture works.' ?></p><?php endif; ?>

  <section class="dash-card" style="margin-top:14px">
    <h2>PHP log (last lines)</h2>
    <p class="meta"><?= $logPath ? 'Written to config/php-error.log, which the web cannot download.' : 'config/ is not writable, so PHP uses the host default log.' ?></p>
    <div class="mono" style="white-space:pre-wrap;word-break:break-word"><?= $log ? rrp_h(implode("\n", $log)) : '<span class="meta">Empty so far.</span>' ?></div>
  </section>

<script>
(function () {
  function copy(text, btn) {
    var done = function () { var t = btn.textContent; btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = t; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
    var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(ta);
  }
  document.querySelectorAll('.err-copy').forEach(function (b) { b.addEventListener('click', function () { copy(b.getAttribute('data-text'), b); }); });
  var all = document.getElementById('copyAll');
  if (all) all.addEventListener('click', function () {
    copy([].map.call(document.querySelectorAll('.err-copy'), function (x) { return x.getAttribute('data-text'); }).join('\n\n----------\n\n'), all);
  });
})();
</script>
<?php rrp_admin_shell_end(); ?>
