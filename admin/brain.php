<?php
declare(strict_types=1);
/**
 * Admin SI Brain (§G) — KPIs, Red Team, redacted transcripts.
 * Decrypt only when openai.si_transcript.master_key is set server-side.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();
$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'redteam') {
            $result = rrp_si_brain_run_redteam((int) $admin['id']);
            if (!empty($result['ok'])) {
                $flash = 'Red Team run #' . (int) $result['run_id'] . ' finished.';
            } else {
                $error = $result['error'] ?? 'Red Team failed.';
            }
        } elseif ($action === 'decrypt') {
            $mid = (int) ($_POST['message_id'] ?? 0);
            $dec = rrp_si_brain_decrypt_message($mid);
            if (!empty($dec['ok'])) {
                $flash = 'Decrypt OK for #' . $mid . ': ' . mb_substr((string) $dec['plaintext'], 0, 280);
                rrp_admin_log((int) $admin['id'], 'si_brain_decrypt', 'chat_messages', $mid, 'Decrypt preview');
            } else {
                $error = $dec['error'] ?? 'Decrypt failed.';
            }
        } elseif ($action === 'elevate') {
            $el = rrp_elevate_super_owners();
            $flash = 'Supers: owners_flagged=' . $el['owners_flagged'] . ', admins_ensured=' . $el['admins_ensured'];
        }
    }
}

$snap = rrp_si_brain_kpi_snapshot();
$redacted = rrp_si_brain_redacted_recent(30);
$runs = [];
try {
    $runs = rrp_db()->query('SELECT * FROM si_brain_runs ORDER BY id DESC LIMIT 8')->fetchAll();
} catch (Throwable $e) {
}

rrp_admin_shell_start('SI Brain', 'brain');
?>
<div class="pad stack brain-wrap" style="gap:16px">
  <header class="brain-head">
    <h1>SI Brain</h1>
    <p class="sub">NAKILAB Red Team + KPIs. Raw chats stay hashed unless Navid unlocks <code>si_transcript.master_key</code>.</p>
  </header>

  <?php if ($flash): ?><div class="flash flash-ok" role="status"><?= rrp_h($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <div class="brain-kpis">
    <div class="box">
      <p class="label">SI messages today</p>
      <p class="big data"><?= rrp_h((string) $snap['messages_today']) ?></p>
      <p class="sub"><?= rrp_h($snap['usage_day']) ?> · America/Toronto</p>
    </div>
    <div class="box">
      <p class="label">Owners with usage</p>
      <p class="big data"><?= rrp_h((string) $snap['owners_today']) ?></p>
      <p class="sub">Tokens: <?= rrp_h((string) $snap['tokens_today']) ?></p>
    </div>
    <div class="box">
      <p class="label">Keys</p>
      <p class="sub" style="margin-top:8px">Brain key: <?= $snap['brain_key_configured'] ? 'ready' : 'empty (graceful)' ?></p>
      <p class="sub">Master decrypt: <?= $snap['master_key_configured'] ? 'set' : 'locked' ?></p>
      <p class="sub">Live prompt: <?= rrp_h((string) ($snap['live_prompt']['version_label'] ?? '—')) ?></p>
    </div>
  </div>

  <?php if ($snap['note']): ?><p class="sub"><?= rrp_h($snap['note']) ?></p><?php endif; ?>

  <section class="box stack" style="gap:12px">
    <h2 class="label" style="font-size:14px">Red Team</h2>
    <p class="sub">Runs eval cases on <code>gpt-6-astra</code> via <code>brain_api_key</code>. Empty keys → no 500.</p>
    <form method="post" class="row" style="gap:8px;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
      <input type="hidden" name="action" value="redteam" />
      <button class="btn primary" type="submit">Run Red Team now</button>
    </form>
    <form method="post" style="margin-top:4px">
      <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
      <input type="hidden" name="action" value="elevate" />
      <button class="btn ghost" type="submit">Re-elevate Navid + Mehrdad supers</button>
    </form>
  </section>

  <section class="box">
    <h2 class="label" style="font-size:14px">Recent runs</h2>
    <?php if ($runs === []): ?>
      <p class="sub" style="margin-top:8px">No runs yet. Import SQL 008, then run Red Team.</p>
    <?php else: ?>
      <ul class="brain-runs">
        <?php foreach ($runs as $run): ?>
          <li>
            <strong>#<?= (int) $run['id'] ?></strong>
            <?= rrp_h((string) $run['status']) ?> ·
            <?= rrp_h((string) $run['run_week']) ?> ·
            <code><?= rrp_h((string) $run['model']) ?></code>
            <?php if (!empty($run['summary_md'])): ?>
              <pre class="brain-summary"><?= rrp_h(mb_substr((string) $run['summary_md'], 0, 1200)) ?></pre>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="box">
    <h2 class="label" style="font-size:14px">Transcripts (redacted)</h2>
    <p class="sub">SHA-256 previews only. Decrypt requires master key on server.</p>
    <?php if ($redacted === []): ?>
      <p class="sub" style="margin-top:8px">No SI messages yet.</p>
    <?php else: ?>
      <table class="brain-table">
        <thead><tr><th>ID</th><th>Hash</th><th>When</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($redacted as $row): ?>
          <tr>
            <td class="data"><?= (int) $row['id'] ?></td>
            <td><code><?= rrp_h($row['preview']) ?></code></td>
            <td class="sub"><?= rrp_h($row['created_at']) ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                <input type="hidden" name="action" value="decrypt" />
                <input type="hidden" name="message_id" value="<?= (int) $row['id'] ?>" />
                <button class="btn ghost" type="submit" <?= $snap['master_key_configured'] ? '' : 'disabled title="master_key empty"' ?>>Decrypt</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
<style>
.brain-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.brain-runs{list-style:none;padding:0;margin:12px 0 0;display:flex;flex-direction:column;gap:12px}
.brain-summary{white-space:pre-wrap;font-size:12px;background:#0a0a0b;border:1px solid #222;padding:10px;margin:8px 0 0;max-height:180px;overflow:auto}
.brain-table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
.brain-table th,.brain-table td{border-bottom:1px solid #222;padding:8px 6px;text-align:left;vertical-align:middle}
@media (max-width:768px){.brain-kpis{grid-template-columns:1fr}}
</style>
<?php rrp_admin_shell_end(); ?>
