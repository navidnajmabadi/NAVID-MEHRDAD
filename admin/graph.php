<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// NAKI brain graph: SIPO at the core; topics, request groups (KNN), issues, rules and tests around it. Click a node for detail.
$admin = rrp_require_admin();
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null) && ($_POST['act'] ?? '') === 'rebuild') {
    $r = rrp_graph_rebuild();
    $flash = $r['requests'] ? "Grouped {$r['requests']} requests into {$r['clusters']} groups." : 'No requests logged yet. Talk to SIPO, or run the Test lab, then rebuild.';
}
$total = (int) rrp_db()->query("SELECT COUNT(*) FROM si_request_log WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetchColumn();
$csrf = rrp_csrf_token();
rrp_admin_shell_start('Brain graph', 'graph');
?>
  <h1>Brain graph</h1>
  <p class="lede">SIPO is the core. Around it: topics, groups of similar requests (grouped by nearest neighbours), the issues seen in them, SIPO's rules, and the tests. Colour is the score, shape is the kind of node. Click or press Enter on a node.</p>
  <?php if ($flash): ?><div class="flash flash-ok"><?= rrp_h($flash) ?></div><?php endif; ?>

  <form method="post" class="ad-toolbar">
    <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="rebuild" />
    <button class="ad-btn is-primary" type="submit">Regroup requests</button>
    <button class="ad-btn" type="button" id="grList" aria-pressed="false">Show as list</button>
    <span class="ad-note" style="margin:0"><?= number_format($total) ?> requests in the last 30 days.</span>
  </form>

  <div class="gr-wrap">
    <div>
      <div class="gr-stage">
        <svg id="grSvg" role="group" aria-label="Brain graph"><title>Brain graph</title></svg>
        <div class="gr-legend" aria-label="Legend">
          <span><svg viewBox="-10 -10 20 20"><path d="M0 -9 L8 -4.5 L8 4.5 L0 9 L-8 4.5 L-8 -4.5Z" fill="currentColor"/></svg>SIPO core</span>
          <span><svg viewBox="-10 -10 20 20"><circle r="8" fill="currentColor"/></svg>Topic</span>
          <span><svg viewBox="-10 -10 20 20"><path d="M0 -9 L9 0 L0 9 L-9 0Z" fill="currentColor"/></svg>Request group</span>
          <span><svg viewBox="-10 -10 20 20"><path d="M0 -9 L9 7 L-9 7Z" fill="currentColor"/></svg>Issue</span>
          <span><svg viewBox="-10 -10 20 20"><rect x="-7" y="-7" width="14" height="14" fill="currentColor"/></svg>Rule</span>
          <span><svg viewBox="-10 -10 20 20"><path d="M0 -9 L2.6 -3 L9 -2.5 L4 1.6 L5.6 8 L0 4.6 L-5.6 8 L-4 1.6 L-9 -2.5 L-2.6 -3Z" fill="currentColor"/></svg>Tests</span>
          <span style="color:#d6453d">● score under 3</span><span style="color:#d98e04">● 3 to 4</span><span style="color:#2da44e">● 4 or more</span><span>● grey: no score</span>
        </div>
      </div>
      <div class="gr-list ad-tablewrap" id="grListBox" style="margin-top:12px"></div>
    </div>
    <aside class="gr-panel" id="grPanel" aria-live="polite">
      <h2>Pick a node</h2>
      <p class="ad-note">The panel shows the score, the requests behind it, what is failing and how to raise the score.</p>
    </aside>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js"></script>
  <script>window.GRAPH = { data: "graph-data.php", act: "graph-act.php", csrf: <?= json_encode($csrf) ?> };</script>
  <script src="<?= rrp_h(rrp_asset('assets/js/admin-graph.js', true)) ?>"></script>
<?php rrp_admin_shell_end(); ?>
