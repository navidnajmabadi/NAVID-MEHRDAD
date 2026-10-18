<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$name = trim((string) ($_GET['pet'] ?? 'your pet'));

rrp_layout_head('Yoho rescued — Ring Ring Paw', ['body_class' => 'page-rescued']);
?>
<div class="shell rescued-shell">
  <div class="app-top">
    <a class="app-brand" href="dashboard.php">
      <img src="assets/logo.jpg" alt="" width="40" height="40" />
      <span>Ring Ring Paw</span>
    </a>
  </div>

  <div class="rescued-card" role="status">
    <img class="rescued-logo" src="assets/logo.jpg" alt="" width="96" height="96" />
    <p class="rescued-eyebrow">Good news</p>
    <h1>Yoho rescued!</h1>
    <p class="lede"><?= rrp_h($name) ?> is marked Found. Nearby helpers were notified — the search is over.</p>
    <a class="btn btn-primary btn-block" href="dashboard.php">Back to dashboard</a>
  </div>
</div>
<?php rrp_layout_foot(); ?>
