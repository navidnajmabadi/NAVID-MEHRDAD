<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$name = trim((string) ($_GET['pet'] ?? 'your pet'));

rrp_layout_head('Home again — siPaw', ['body_class' => 'page-rescued']);
rrp_app_start(['nav' => false]);
?>
<div class="pad" style="padding-top:48px">
  <div class="rescued-card" role="status">
    <?php rrp_logo_wordmark(); ?>
    <p class="rescued-eyebrow" style="margin-top:20px">Good news</p>
    <h1 class="title" style="font-size:32px;margin:8px 0">Home again</h1>
    <p class="sub" style="margin:0 0 24px;font-size:15px"><?= rrp_h($name) ?> is marked Found. Nearby helpers were notified — the search is over.</p>
    <a class="btn primary" href="dashboard.php">Back to home</a>
  </div>
</div>
<?php
rrp_app_end(['nav' => false]);
