<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// One real call to OpenAI with the live config, so a broken key/model shows its exact error.
$admin = rrp_require_admin();
$cfg = rrp_config_ready() ? (rrp_load_config()['openai'] ?? []) : [];
$client = RrpOpenAiClient::product();
$model = $client->modelFor('si_chat');
$result = null;
$withTools = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $msgs = [['role' => 'system', 'content' => 'Reply with the single word: ready'], ['role' => 'user', 'content' => 'ping']];
    $result = $client->complete($msgs, [], $model, 0.6);
    $withTools = $client->complete($msgs, rrp_sipo_tool_defs(true), $model, 0.6);
}
$last = RrpOpenAiClient::lastError();
$key = trim((string) ($cfg['api_key'] ?? ''));

rrp_admin_shell_start('SIPO check', 'sipo');
?>
  <h1>SIPO check</h1>
  <p class="lede">Tests the live OpenAI setup SIPO uses. Nothing is shown to owners.</p>

  <div class="panel panel-soft">
    <div class="meta">API key: <b><?= $key === '' ? 'missing' : 'set (…' . rrp_h(substr($key, -4)) . ')' ?></b></div>
    <div class="meta">Base URL: <b><?= rrp_h((string) ($cfg['base_url'] ?? 'https://api.openai.com/v1')) ?></b></div>
    <div class="meta">Chat model (openai.models.si_chat): <b><?= rrp_h($model) ?></b></div>
    <div class="meta">Fallback (openai.fallback_model): <b><?= rrp_h((string) ($cfg['fallback_model'] ?? '') ?: 'not set') ?></b></div>
  </div>

  <form method="post" style="margin:16px 0">
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <button class="btn primary" type="submit" style="width:auto">Run test call</button>
  </form>

  <?php foreach (['Plain chat' => $result, 'Chat with SIPO tools' => $withTools] as $label => $r): if ($r === null) continue; ?>
    <div class="panel panel-soft" style="margin-bottom:10px;border-color:<?= !empty($r['ok']) ? 'var(--blue)' : 'var(--red)' ?>">
      <strong><?= rrp_h($label) ?>: <?= !empty($r['ok']) ? 'OK' : 'FAILED' ?></strong>
      <div class="meta">Model used: <?= rrp_h((string) ($r['model'] ?? '')) ?> · HTTP <?= (int) ($r['status'] ?? 0) ?> · <?= (int) ($r['latency_ms'] ?? 0) ?> ms</div>
      <div class="meta"><?= !empty($r['ok']) ? 'Reply: ' . rrp_h(mb_substr((string) ($r['content'] ?? '[tool call]'), 0, 200)) : 'Error: ' . rrp_h((string) ($r['error'] ?? '')) ?></div>
    </div>
  <?php endforeach; ?>

  <?php $ex = json_decode((string) @file_get_contents(sys_get_temp_dir() . '/sipaw-last-extract.json'), true); ?>
  <?php if (is_array($ex)): ?>
    <div class="panel panel-soft" style="margin-bottom:10px">
      <strong>Last profile reading (new-pet setup)</strong>
      <div class="meta"><?= rrp_h((string) $ex['at']) ?> · chat #<?= (int) $ex['thread_id'] ?> · <?= $ex['onboarding'] ? 'pet not created yet' : 'pet exists' ?> · <?= $ex['ok'] ? 'OK' : 'FAILED' ?></div>
      <div class="meta"><?= $ex['ok'] ? 'Read: ' . rrp_h(json_encode($ex['data'], JSON_UNESCAPED_UNICODE)) : 'Error: ' . rrp_h((string) $ex['error']) ?></div>
    </div>
  <?php endif; ?>
  <?php if ($last): ?>
    <div class="panel panel-soft">
      <strong>Last error seen by SIPO</strong>
      <div class="meta"><?= rrp_h($last['at']) ?> · model <?= rrp_h($last['model']) ?> · HTTP <?= (int) $last['status'] ?></div>
      <div class="meta"><?= rrp_h($last['error']) ?></div>
    </div>
  <?php endif; ?>
<?php
rrp_admin_shell_end();
