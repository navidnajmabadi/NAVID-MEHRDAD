<?php
declare(strict_types=1);
/**
 * Admin SI Brain (T06c) — KPI shell. Raw decrypt requires si_transcript.master_key.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

rrp_require_admin();

$stats = [
    'si_messages_today' => null,
    'usage_owners' => null,
    'note' => 'Import 006/007. Decrypt UI stays locked without openai.si_transcript.master_key.',
];
try {
    $day = rrp_si_toronto_day();
    $q = rrp_db()->prepare('SELECT COUNT(*) FROM si_usage_daily WHERE usage_day = ?');
    $q->execute([$day]);
    $stats['usage_owners'] = (int) $q->fetchColumn();
    $q2 = rrp_db()->prepare('SELECT COALESCE(SUM(message_count),0) FROM si_usage_daily WHERE usage_day = ?');
    $q2->execute([$day]);
    $stats['si_messages_today'] = (int) $q2->fetchColumn();
} catch (Throwable $e) {
    $stats['note'] = 'si_usage_daily missing — import sql/006_v1_product.sql';
}

rrp_admin_shell_start('SI Brain', 'brain');
?>
<div class="pad stack" style="gap:16px">
  <h1>SI Brain</h1>
  <p class="sub">KPIs only for ops. Raw transcripts require Navid master key (never in git).</p>
  <div class="box">
    <p class="label">Today (America/Toronto)</p>
    <p class="big data" style="margin-top:8px"><?= rrp_h((string) ($stats['si_messages_today'] ?? '—')) ?></p>
    <p class="sub">SI messages counted</p>
    <p class="sub" style="margin-top:8px">Owners with usage: <?= rrp_h((string) ($stats['usage_owners'] ?? '—')) ?></p>
  </div>
  <div class="box">
    <p class="label">Red-team / eval</p>
    <p class="sub" style="margin-top:8px">Weekly red-team on Brain model ships after adapter is live. <?= rrp_h($stats['note']) ?></p>
  </div>
</div>
<?php rrp_admin_shell_end(); ?>
