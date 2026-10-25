<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$doc = (string) ($_GET['doc'] ?? 'terms');
$titles = [
    'terms' => 'Terms of Service',
    'privacy' => 'Privacy Policy',
];
if (!isset($titles[$doc])) {
    $doc = 'terms';
}

rrp_layout_head($titles[$doc] . ' — SIPAW');
rrp_app_start(['nav' => false]);
?>
<div class="pad stack" style="gap:16px;max-width:640px;margin:0 auto;padding-top:32px">
  <?php rrp_logo_endorsed(); ?>
  <h1 class="title" style="font-size:28px"><?= rrp_h($titles[$doc]) ?></h1>
  <p class="sub">Stub page until T27. SIPAW by Nakilab — English only.</p>
  <?php if ($doc === 'privacy'): ?>
    <div class="box stack" style="gap:10px">
      <p class="sub" style="font-size:14px">We store pet profiles, health notes, approximate location area, chat, and forum posts. OpenAI may process SI messages when configured. You can request export/delete later (T26).</p>
    </div>
  <?php else: ?>
    <div class="box stack" style="gap:10px">
      <p class="sub" style="font-size:14px">SIPAW is not a veterinary service. SIPO is Super Intelligence for education and organization — never a substitute for your vet. No invented drug doses.</p>
    </div>
  <?php endif; ?>
  <p><a class="link" href="signup.php">Back to register</a> · <a class="link" href="index.php">Home</a></p>
  <div class="gap"></div>
</div>
<?php rrp_app_end(['nav' => false, 'push' => false]); ?>
