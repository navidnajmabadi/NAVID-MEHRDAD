<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';
require_once dirname(__DIR__) . '/lib/views/health_dashboard.php';

// NAKI brain: quality of SIPO's real replies, brain growth, prompt versions (auto-apply + rollback), exam.
$admin = rrp_require_admin();
$flash = null;
$exam = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    @set_time_limit(180);
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'score') {
        $r = rrp_naki_score_recent(30);
        $flash = "Scored {$r['scored']} replies" . ($r['avg'] !== null ? ", average {$r['avg']}/5" : '') . '.';
    } elseif ($act === 'propose') {
        $r = rrp_naki_propose('admin');
        $flash = !empty($r['ok']) ? 'Proposal ' . $r['status'] . ($r['notes'] ? ': ' . mb_substr((string) $r['notes'], 0, 200) : '') : ($r['error'] ?? 'Failed');
    } elseif ($act === 'exam') {
        $exam = rrp_naki_exam();
        $flash = !empty($exam['ok']) ? 'Exam score ' . $exam['score'] . '%' : ($exam['error'] ?? 'Exam failed');
    } elseif ($act === 'rollback') {
        $v = (int) ($_POST['version_id'] ?? 0);
        $r = $v > 0 ? rrp_sipo_rollback_core_prompt($v) : ['ok' => (bool) rrp_db()->exec("UPDATE si_prompt_versions SET is_live = 0 WHERE layer = 'core' AND is_live = 1") || true];
        rrp_admin_log((int) $admin['id'], 'prompt_rollback', 'si_prompt_versions', $v, null);
        $flash = !empty($r['ok']) ? ($v ? "Version #{$v} is live again." : 'Back to the built-in default prompt.') : ($r['error'] ?? 'Failed');
    } elseif ($act === 'prune') {
        $flash = 'Memory cleanup removed ' . rrp_naki_prune_memory() . ' duplicate or empty facts.';
    }
}
$live3 = rrp_naki_live_stats(3);
$live30 = rrp_naki_live_stats(30);
$growth = rrp_naki_growth(12);
$versions = rrp_ins_q("SELECT id, version_label, is_live, created_by, notes, created_at, replaced_at, CHAR_LENGTH(system_prompt) AS chars FROM si_prompt_versions WHERE layer = 'core' ORDER BY id DESC LIMIT 12");
$proposals = rrp_ins_q('SELECT * FROM naki_proposals ORDER BY id DESC LIMIT 10');
$worst = rrp_ins_q("SELECT s.quality_score, s.issues_json, s.notes, s.created_at, m.body FROM si_brain_scores s JOIN chat_messages m ON m.id = s.message_id WHERE s.source = 'live' ORDER BY s.created_at DESC, s.quality_score ASC LIMIT 12");
$brainReady = RrpOpenAiClient::brain()->configured();
$csrf = rrp_csrf_token();
$repeat = $live30['n'] ? round(100 * (($live30['issues']['repeat_question'] ?? 0)) / $live30['n'], 1) : null;

rrp_admin_shell_start('NAKI brain', 'brain');
?>
  <h1>NAKI · SI Brain</h1>
  <p class="lede">NAKI reads SIPO's real replies (personal details removed), scores them, and every <?= RRP_NAKI_EVOLVE_DAYS ?> days proposes a small prompt fix. A fix goes live only if it passes the exam; after <?= RRP_NAKI_EVOLVE_DAYS ?> days it is kept, or rolled back automatically if quality fell.</p>
  <?php if ($flash): ?><div class="flash flash-ok"><?= rrp_h($flash) ?></div><?php endif; ?>
  <?php if (!$brainReady): ?><div class="flash flash-error">Add <code>openai.brain_api_key</code> for NAKI's judgements. Signals-only scoring still works.</div><?php endif; ?>

  <div class="kpis">
    <div class="kpi"><b><?= $live3['avg'] ?? '–' ?></b><span>Quality (3 days, /5)</span></div>
    <div class="kpi"><b><?= $live30['avg'] ?? '–' ?></b><span>Quality (30 days)</span></div>
    <div class="kpi"><b><?= (int) $live30['n'] ?></b><span>Replies scored</span></div>
    <div class="kpi"><b><?= (int) $live30['thanks'] ?></b><span>Owner thanked</span></div>
    <div class="kpi <?= $live30['frustration'] ? 'is-bad' : '' ?>"><b><?= (int) $live30['frustration'] ?></b><span>Owner frustrated</span></div>
    <div class="kpi"><b><?= $repeat !== null ? $repeat . '%' : '–' ?></b><span>Owner had to repeat</span></div>
  </div>

  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" />
    <button class="btn outline" name="act" value="score" style="width:auto">Score recent replies</button>
    <button class="btn outline" name="act" value="exam" style="width:auto">Run exam</button>
    <button class="btn primary" name="act" value="propose" style="width:auto">Propose + test a fix now</button>
    <button class="btn outline" name="act" value="prune" style="width:auto">Clean memory</button>
  </form>

  <div class="dash-grid">
    <section class="dash-card">
      <h2>Brain growing</h2>
      <?php $qa = array_map(static fn($g) => ['date' => $g['week'], 'value' => (float) $g['qa']], $growth); ?>
      <p class="meta">Answers given (total)</p><?php rrp_view_line_chart($qa, null, null, 'answers'); ?>
      <?php $facts = array_map(static fn($g) => ['date' => $g['week'], 'value' => (float) $g['facts']], $growth); ?>
      <p class="meta">Facts learned about pets (total)</p><?php rrp_view_line_chart($facts, null, null, 'facts'); ?>
      <?php $qual = array_values(array_filter(array_map(static fn($g) => $g['quality'] !== null ? ['date' => $g['week'], 'value' => $g['quality']] : null, $growth))); ?>
      <?php if (count($qual) >= 2): ?><p class="meta">Quality per week</p><?php rrp_view_line_chart($qual, 4.0, 5.0, '/5'); ?><?php endif; ?>
      <p class="meta">Prompt versions: <?= (int) end($growth)['versions'] ?></p>
    </section>

    <section class="dash-card">
      <h2>Most common issues (30 days)</h2>
      <?php $mx = max(1, max($live30['issues'] ?: [1])); ?>
      <div class="dbars"><?php foreach ($live30['issues'] as $k => $n): ?>
        <div class="dbar"><span><?= rrp_h(str_replace('_', ' ', $k)) ?></span><i style="width:<?= round(100 * $n / $mx) ?>%"></i><b class="data"><?= (int) $n ?></b></div>
      <?php endforeach; ?><?php if (!$live30['issues']): ?><p class="meta">No issues found yet 🎉</p><?php endif; ?></div>
    </section>
  </div>

  <?php if ($exam && !empty($exam['results'])): ?>
    <section class="dash-card" style="margin-top:14px">
      <h2>Exam · <?= rrp_h((string) $exam['score']) ?>%</h2>
      <table class="dash-table"><tr><th>Case</th><th>Result</th><th>SIPO said</th></tr>
        <?php foreach ($exam['results'] as $r): ?><tr><td><?= rrp_h($r['key']) ?></td><td class="<?= $r['pass'] ? 'pill-ok' : 'pill-bad' ?>"><?= $r['pass'] ? 'pass' : 'FAIL' ?></td>
          <td><?= rrp_h($r['reply']) ?><?= $r['tools'] ? ' <span class="meta">[tools: ' . rrp_h(implode(', ', $r['tools'])) . ']</span>' : '' ?></td></tr><?php endforeach; ?>
      </table>
    </section>
  <?php endif; ?>

  <section class="dash-card" style="margin-top:14px">
    <h2>Prompt versions</h2>
    <table class="dash-table"><tr><th>#</th><th>Label</th><th>By</th><th>Created</th><th>Notes</th><th></th></tr>
      <tr><td>–</td><td>Built-in default</td><td>code</td><td></td><td><?= rrp_naki_core_version_id() ? '' : '<b class="pill-ok">LIVE</b>' ?></td>
        <td><?php if (rrp_naki_core_version_id()): ?><form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="rollback" /><input type="hidden" name="version_id" value="0" /><button class="link" type="submit">Use</button></form><?php endif; ?></td></tr>
      <?php foreach ($versions as $v): ?>
        <tr><td><?= (int) $v['id'] ?></td><td><?= rrp_h($v['version_label']) ?> <?= (int) $v['is_live'] ? '<b class="pill-ok">LIVE</b>' : '' ?></td><td><?= rrp_h($v['created_by']) ?></td><td><?= rrp_h(substr((string) $v['created_at'], 0, 16)) ?></td>
          <td class="mono"><?= rrp_h((string) $v['notes']) ?></td>
          <td><?php if (!(int) $v['is_live']): ?><form method="post"><input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="rollback" /><input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>" /><button class="link" type="submit">Make live</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>

  <section class="dash-card" style="margin-top:14px">
    <h2>NAKI proposals</h2>
    <table class="dash-table"><tr><th>#</th><th>Status</th><th>Exam before → after</th><th>Live before → after</th><th>Changes</th></tr>
      <?php foreach ($proposals as $p): ?><tr><td><?= (int) $p['id'] ?></td><td><?= rrp_h($p['status']) ?> <span class="meta"><?= rrp_h($p['trigger_source']) ?></span></td>
        <td><?= rrp_h((string) ($p['exam_before'] ?? '–')) ?> → <?= rrp_h((string) ($p['exam_after'] ?? '–')) ?></td>
        <td><?= rrp_h((string) ($p['live_before'] ?? '–')) ?> → <?= rrp_h((string) ($p['live_after'] ?? '–')) ?></td>
        <td class="mono"><?= rrp_h(mb_substr((string) $p['change_notes'], 0, 400)) ?></td></tr><?php endforeach; ?>
      <?php if (!$proposals): ?><tr><td colspan="5" class="meta">No proposals yet. NAKI proposes every <?= RRP_NAKI_EVOLVE_DAYS ?> days, or press the button above.</td></tr><?php endif; ?>
    </table>
  </section>

  <section class="dash-card" style="margin-top:14px">
    <h2>Recent scored replies</h2>
    <table class="dash-table"><tr><th>Score</th><th>SIPO said (redacted)</th><th>Issues</th><th>NAKI note</th></tr>
      <?php foreach ($worst as $w): ?><tr><td class="data <?= $w['quality_score'] < 3 ? 'pill-bad' : '' ?>"><?= rrp_h((string) $w['quality_score']) ?></td>
        <td><?= rrp_h(rrp_naki_redact(mb_substr((string) $w['body'], 0, 220))) ?></td><td class="meta"><?= rrp_h(implode(', ', (array) json_decode((string) $w['issues_json'], true))) ?></td><td class="meta"><?= rrp_h((string) $w['notes']) ?></td></tr><?php endforeach; ?>
    </table>
  </section>
<?php
rrp_admin_shell_end();
