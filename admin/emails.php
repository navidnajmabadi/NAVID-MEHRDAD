<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

rrp_require_admin();

/** Sample data only: this page never reads or sends real user data. */
$d = static fn(int $n): string => gmdate('Y-m-d', time() + 86400 * $n);
$sample = [
    'owner_name' => 'Alex',
    'from' => $d(-6),
    'to' => $d(0),
    'has_activity' => true,
    'spend' => ['CAD' => 125.5],
    'pets' => [[
        'id' => 1, 'name' => 'Milo', 'type' => 'dog', 'walks' => 4, 'walk_minutes' => 120, 'bathroom' => 9, 'checkins' => 5,
        'weight' => [['date' => $d(-40), 'kg' => 11.2], ['date' => $d(-32), 'kg' => 11.5], ['date' => $d(-24), 'kg' => 11.8], ['date' => $d(-16), 'kg' => 12.0], ['date' => $d(-8), 'kg' => 12.3], ['date' => $d(-1), 'kg' => 12.4]],
        'weight_trend' => 'up',
        'due' => [['label' => 'Rabies', 'date' => $d(-3), 'days' => -3, 'status' => 'overdue'], ['label' => 'Bordetella', 'date' => $d(12), 'days' => 12, 'status' => 'soon']],
        'meds' => [['name' => 'Ear drops', 'kind' => 'medicine', 'ends_on' => $d(2), 'days_left' => 2]],
        'labs' => [['label' => 'ALT', 'value' => '142', 'unit' => 'U/L', 'range' => '10 to 100', 'flag' => 'high', 'date' => $d(-10)]],
        'spend' => ['CAD' => 45.5],
        'note' => "Milo's Rabies is overdue. A call to the vet this week would put my mind at ease.",
    ]],
];
$mails = [
    'Password reset' => rrp_email_password_reset('Alex', rrp_app_url('reset-password.php?token=SAMPLE'), 2),
    'Weekly health report' => rrp_email_weekly_report($sample, rrp_app_url('settings')),
];

rrp_admin_shell_start('Email designs', 'emails');
?>
  <h1>Email designs</h1>
  <p class="lede">What users receive, with sample data. Nothing here is sent.</p>
  <?php foreach ($mails as $title => $m): ?>
    <div class="panel" style="margin-bottom:16px">
      <strong><?= rrp_h($title) ?></strong> <span class="meta">Subject: <?= rrp_h($m['subject']) ?></span>
      <iframe title="<?= rrp_h($title) ?>" sandbox="" srcdoc="<?= rrp_h($m['html']) ?>" style="width:100%;height:720px;border:1px solid var(--line,#ddd);border-radius:12px;margin-top:10px;background:#fff"></iframe>
    </div>
  <?php endforeach; ?>
<?php rrp_admin_shell_end(); ?>
