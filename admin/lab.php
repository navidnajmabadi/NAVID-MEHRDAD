<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// Test lab: write cases by hand, let the brain model write many at once, run them through SIPO's real
// instructions, see the score per topic. Failing cases show where SIPO is wrong; NAKI learns from them.
$admin = rrp_require_admin();
$flash = null;
$flashBad = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    @set_time_limit(280);
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'add') {
        $id = rrp_lab_add((string) ($_POST['domain'] ?? 'general'), (string) ($_POST['ask'] ?? ''), (string) ($_POST['must_any'] ?? ''), (string) ($_POST['must_not'] ?? ''), 'manual');
        $flash = $id ? 'Test added.' : 'Not added: write what the owner says, and it must be new.';
        $flashBad = !$id;
    } elseif ($act === 'seed') {
        $n = rrp_lab_seed_from_issues();
        $flash = $n ? "Added {$n} tests from bugs already found." : 'The known-bug tests are already there.';
    } elseif ($act === 'bulk') {
        $r = rrp_lab_generate_bulk((int) ($_POST['per'] ?? 6), ($_POST['only'] ?? '') !== '' ? (string) $_POST['only'] : null);
        $flash = !empty($r['ok']) ? "The brain model wrote {$r['added']} new tests." : ($r['error'] ?? 'Failed');
        $flashBad = empty($r['ok']);
    } elseif ($act === 'run' || $act === 'run_failing') {
        $r = rrp_lab_run_all($act === 'run_failing');
        $flash = isset($r['error']) ? 'Stopped: ' . $r['error'] . " (ran {$r['ran']})" : "Ran {$r['ran']} tests: {$r['passed']} passed, " . ($r['ran'] - $r['passed']) . ' failed, ' . number_format($r['tokens']) . ' tokens used.';
        $flashBad = isset($r['error']);
    } elseif ($act === 'run_one') {
        $st = rrp_db()->prepare('SELECT * FROM si_tests WHERE id = ?');
        $st->execute([(int) ($_POST['id'] ?? 0)]);
        $c = $st->fetch();
        $r = $c ? rrp_lab_run_one($c) : ['ok' => false, 'error' => 'Test not found'];
        $flash = !empty($r['ok']) ? ($r['pass'] ? 'Passed.' : 'Failed: ' . implode('; ', $r['why'])) : ($r['error'] ?? 'Failed');
        $flashBad = empty($r['ok']) || !$r['pass'];
    } elseif ($act === 'off') {
        rrp_db()->prepare('UPDATE si_tests SET active = 0 WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        $flash = 'Test removed from the lab.';
    } elseif ($act === 'improve') {
        $r = rrp_naki_propose('admin');
        $flash = !empty($r['ok']) ? 'NAKI proposal: ' . $r['status'] . ($r['notes'] ? ' · ' . mb_substr((string) $r['notes'], 0, 220) : '') : ($r['error'] ?? 'Failed');
        $flashBad = empty($r['ok']);
    }
}
$sum = rrp_lab_summary();
$dom = (string) ($_GET['d'] ?? '');
$only = $dom !== '' && isset(RRP_DOMAINS[$dom]) ? 'AND domain = ' . rrp_db()->quote($dom) : '';
$tests = rrp_db()->query("SELECT * FROM si_tests WHERE active = 1 {$only} ORDER BY (last_pass IS NULL) DESC, last_pass ASC, id DESC LIMIT 200")->fetchAll();
$rate = $sum['ran'] ? (int) round(100 * $sum['pass'] / $sum['ran']) : null;
$product = RrpOpenAiClient::product()->configured();
$brain = RrpOpenAiClient::brain()->configured();
$csrf = rrp_csrf_token();
$post = static fn(string $act, array $hid = [], string $label = '', string $cls = 'ad-btn') => '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . rrp_h($csrf) . '"><input type="hidden" name="act" value="' . rrp_h($act) . '">'
    . implode('', array_map(static fn($k, $v) => '<input type="hidden" name="' . rrp_h($k) . '" value="' . rrp_h((string) $v) . '">', array_keys($hid), $hid)) . '<button class="' . $cls . '" type="submit">' . rrp_h($label) . '</button></form>';

rrp_admin_shell_start('Test lab', 'lab');
?>
  <h1>Test lab</h1>
  <p class="lede">A test is something an owner might say plus what a good reply must (and must not) do. Each test runs through SIPO's real instructions and gets a score. Let the brain model write many tests at once, run them, and fix what fails.</p>
  <?php if ($flash): ?><div class="flash <?= $flashBad ? 'flash-error' : 'flash-ok' ?>" role="status"><?= rrp_h($flash) ?></div><?php endif; ?>
  <?php if (!$product): ?><div class="flash flash-error">Running tests needs <code>openai.api_key</code>. You can still write and seed tests.</div><?php endif; ?>
  <?php if (!$brain): ?><div class="flash flash-error">Bulk generation needs <code>openai.brain_api_key</code>.</div><?php endif; ?>

  <div class="ad-kpis">
    <div class="ad-kpi"><b><?= $sum['total'] ?></b><span>Tests</span></div>
    <div class="ad-kpi <?= $rate === null ? '' : ($rate >= 90 ? 'is-good' : ($rate >= 70 ? 'is-warn' : 'is-bad')) ?>"><b><?= $rate === null ? '–' : $rate . '%' ?></b><span>Passing (<?= $sum['pass'] ?> of <?= $sum['ran'] ?> run)</span></div>
    <div class="ad-kpi"><b><?= $sum['tokens'] ? number_format((int) $sum['tokens']) : '–' ?></b><span>Tokens per test (lower is cheaper)</span></div>
    <div class="ad-kpi"><b><?= $sum['total'] - $sum['ran'] ?></b><span>Not run yet</span></div>
  </div>

  <div class="ad-card">
    <h2>Create and run</h2>
    <div class="ad-toolbar" style="margin-bottom:0">
      <?= $post('seed', [], 'Add tests from known bugs') ?>
      <form method="post" class="ad-toolbar" style="margin:0">
        <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="bulk" />
        <label class="ad-field">Topic<select name="only"><option value="">All topics</option><?php foreach (RRP_DOMAINS as $k => $l): if ($k === 'general') continue; ?><option value="<?= $k ?>"><?= rrp_h($l) ?></option><?php endforeach; ?></select></label>
        <label class="ad-field">Per topic<select name="per"><?php foreach ([4, 6, 8, 12] as $n): ?><option value="<?= $n ?>" <?= $n === 6 ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
        <button class="ad-btn is-primary" type="submit" <?= $brain ? '' : 'disabled' ?>>Generate tests with AI</button>
      </form>
      <?= $post('run', [], 'Run up to ' . RRP_LAB_MAX_RUN . ' tests') ?>
      <?= $post('run_failing', [], 'Re-run failing and new') ?>
      <?= $post('improve', [], 'Ask NAKI to improve SIPO from failures', 'ad-btn') ?>
    </div>
    <p class="ad-note">A run uses roughly 7,000 tokens per test, so <?= RRP_LAB_MAX_RUN ?> tests is about <?= number_format(RRP_LAB_MAX_RUN * 7000) ?> tokens. NAKI only applies a change to SIPO's instructions if the exam passes at least as well as before, and rolls it back if live quality drops.</p>
  </div>

  <div class="ad-card">
    <h2>Write one test</h2>
    <form method="post" class="ad-toolbar" style="margin:0">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="add" />
      <label class="ad-field">Topic<select name="domain"><?php foreach (RRP_DOMAINS as $k => $l): ?><option value="<?= $k ?>"><?= rrp_h($l) ?></option><?php endforeach; ?></select></label>
      <label class="ad-field" style="flex:2 1 280px">The owner says<input name="ask" required maxlength="500" placeholder="Remind me tomorrow to give her the pills" /></label>
      <label class="ad-field" style="flex:1 1 200px">Reply must contain one of<input name="must_any" maxlength="500" placeholder="what time | when | tool:set_reminder" /></label>
      <label class="ad-field" style="flex:1 1 200px">Reply must not contain<input name="must_not" maxlength="500" placeholder="tool:set_reminder | \d+ ?mg" /></label>
      <button class="ad-btn" type="submit">Add test</button>
    </form>
    <p class="ad-note">Separate words with a vertical bar. <code>tool:set_reminder</code> means that tool must (or must not) be called. A word can be a pattern such as <code>\d+ ?mg</code>.</p>
  </div>

  <div class="ad-card">
    <h2>Score by topic</h2>
    <?php if (!$sum['by_domain']): ?><p class="ad-empty">No tests yet. Start with "Add tests from known bugs".</p><?php else: ?>
    <div class="dbars">
      <?php foreach ($sum['by_domain'] as $k => $b): $p = $b['ran'] ? (int) round(100 * $b['pass'] / $b['ran']) : 0; ?>
        <a class="dbar" style="text-decoration:none;color:inherit" href="lab.php?d=<?= rrp_h($k) ?>"><span><?= rrp_h(RRP_DOMAINS[$k] ?? $k) ?></span><i style="width:<?= max(2, $p) ?>%;background:<?= $b['ran'] ? ($p >= 90 ? 'var(--ad-good)' : ($p >= 70 ? 'var(--ad-warn)' : 'var(--ad-bad)')) : 'var(--line)' ?>"></i><b class="data"><?= $b['ran'] ? $p . '%' : '–' ?> <small>(<?= $b['n'] ?>)</small></b></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($tests): ?>
  <div class="ad-tablewrap">
    <table class="ad-table">
      <thead><tr><th>Topic</th><th>The owner says</th><th>Rules</th><th>Result</th><th class="num">Tokens</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($tests as $t): ?>
        <tr>
          <td data-label="Topic"><?= rrp_h(RRP_DOMAINS[$t['domain']] ?? $t['domain']) ?><br><small class="meta"><?= rrp_h($t['source']) ?></small></td>
          <td data-label="Says"><?= rrp_h($t['ask']) ?></td>
          <td data-label="Rules"><small class="meta"><?= $t['must_any'] !== '' ? 'must: ' . rrp_h($t['must_any']) : '' ?><?= ($t['must_any'] !== '' && $t['must_not'] !== '') ? '<br>' : '' ?><?= $t['must_not'] !== '' ? 'not: ' . rrp_h($t['must_not']) : '' ?></small></td>
          <td data-label="Result">
            <?php if ($t['last_pass'] === null): ?><span class="ad-chip">not run</span>
            <?php else: ?><span class="ad-chip <?= (int) $t['last_pass'] ? 'is-good' : 'is-bad' ?>"><?= (int) $t['last_pass'] ? 'pass' : 'fail' ?> · <?= rrp_h((string) $t['last_score']) ?></span>
              <details><summary class="meta" style="cursor:pointer">SIPO replied</summary><p class="ad-note" style="white-space:pre-wrap"><?= rrp_h((string) $t['last_reply']) ?></p></details><?php endif; ?>
          </td>
          <td class="num" data-label="Tokens"><?= $t['last_tokens'] !== null ? number_format((int) $t['last_tokens']) : '–' ?></td>
          <td style="white-space:nowrap">
            <?= $post('run_one', ['id' => (int) $t['id']], 'Run', 'ad-btn is-sm') ?>
            <?= $post('off', ['id' => (int) $t['id']], 'Remove', 'ad-btn is-sm') ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
<?php rrp_admin_shell_end(); ?>
