<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/tour.php';

/**
 * Guided tour: what SIPAW can do, one part at a time, with animated demos on sample data.
 * Read-only by design. It never creates, changes or deletes anything of the owner's (pets, chats, memory, reminders).
 * Opened after the welcome on first sign-in (?first=1) and any time from Menu → Take the tour.
 */
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$first = isset($_GET['first']);
$skipTo = $first && !$pets ? 'si-chat.php?new=1' : 'dashboard.php';
$endTo = $pets ? 'dashboard.php' : 'si-chat.php?new=1';
$steps = rrp_tour_steps($owner);
$total = count($steps) + 2;   // intro + steps + finish
$csrf = rrp_csrf_token();
$thread = null;
if ($pets) {
    try {
        // Look only: rrp_sipo_thread_for_pet() would create a chat, and the tour must not write anything.
        $st = rrp_db()->prepare("SELECT id FROM chat_threads WHERE owner_id = ? AND pet_id = ? AND thread_kind = 'si' ORDER BY id ASC LIMIT 1");
        $st->execute([(int) $owner['id'], (int) $pets[0]['id']]);
        $thread = $st->fetch() ?: null;
    } catch (Throwable $e) {
        $thread = null;
    }
}
$links = [
    'health' => ['care.php', 'Open my Health page'],
    'vet' => ['care.php#share', 'Make a vet link'],
    'chat' => ['si-chat.php', 'Open SIPO'],
    'routines' => ['routines.php', 'Open Routines'],
    'lost' => ['lost.php', 'See how Lost pet works'],
    'money' => ['finance.php', 'Open Expenses'],
    'walk' => ['daily.php', 'Open Daily'],
    'coins' => ['rewards.php', 'Open Rewards'],
];

rrp_layout_head('Take the tour — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />'
        . '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/tour.css', true)) . '" />',
    'body_class' => 'page-care page-tour',
]);
rrp_app_start(['nav' => false]);
?>
<header class="tour-top">
  <span class="brand"><?php rrp_logo_wordmark(); ?></span>
  <a class="tour-skip" href="<?= rrp_h($skipTo) ?>" id="tourSkip">Skip</a>
</header>
<div class="tour-progress" id="tourProgress" aria-hidden="true"><?php for ($i = 0; $i < $total; $i++): ?><i></i><?php endfor; ?></div>
<p class="tour-count" id="tourCount" aria-live="polite">Step 1 of <?= (int) $total ?></p>

<main id="tour" data-total="<?= (int) $total ?>">
  <section class="tour-step on" data-step="0" data-key="intro" aria-labelledby="t-intro">
    <div class="tour-head">
      <div class="tour-tile" aria-hidden="true"><em>01</em><strong>Si<sup>°</sup></strong><small>SIPAW</small></div>
      <h1 id="t-intro">A 2 minute tour of SIPAW</h1>
      <p class="lead">Eight short demos, each on a sample dog called Milo. They show what SIPAW does for your pet and how to use it.</p>
    </div>
    <div class="tour-demo"><img src="<?= rrp_h(rrp_asset('assets/mascot/v2/fullbody.webp')) ?>" alt="SIPO waving" width="285" height="380" style="width:70%;height:auto" /></div>
    <div class="tour-body">
      <ul class="tour-how">
        <li>Nothing here changes your pets, chats, reminders or SIPO's memory.</li>
        <li>Skip any time. You can come back from the Menu: <b>Take the tour</b>.</li>
        <li>Every demo repeats by itself, so you can read it at your pace.</li>
      </ul>
    </div>
  </section>

<?php foreach ($steps as $i => $s): $n = $i + 1; ?>
  <section class="tour-step" data-step="<?= $n ?>" data-key="<?= rrp_h($s['key']) ?>" data-demo="<?= rrp_h($s['demo']) ?>" aria-labelledby="t-<?= rrp_h($s['key']) ?>">
    <div class="tour-head">
      <div class="tour-tile" aria-hidden="true"><em><?= rrp_h($s['no']) ?></em><strong><?= rrp_h($s['sym']) ?></strong><small><?= rrp_h($s['name']) ?></small></div>
      <h1 id="t-<?= rrp_h($s['key']) ?>"><?= rrp_h($s['title']) ?></h1>
      <p class="lead"><?= rrp_h($s['lead']) ?></p>
    </div>
    <div class="tour-demo">
      <div class="tour-phone"><div class="tour-cover">Loading demo…</div><iframe title="<?= rrp_h($s['name']) ?> demo" data-src="<?= rrp_h(rrp_asset('tour-demo.php?d=' . $s['demo'])) ?>" loading="lazy" tabindex="-1"></iframe></div>
      <p class="tour-demo-note">Sample data about Milo. Not your pet.</p>
    </div>
    <div class="tour-body">
      <ol class="tour-how"><?php foreach ($s['how'] as $h): ?><li><?= rrp_h($h) ?></li><?php endforeach; ?></ol>
<?php if (($s['action'] ?? '') === 'labs'): ?>
      <div class="tour-try">
        <?php if ($pets && $thread): ?>
          <p><b>Try it with a real result.</b> Pick a photo or a PDF of your pet's lab results. SIPO reads it and saves the numbers. This is the one place in the tour that adds something, and only because you choose a file.</p>
          <label class="btn primary"><input type="file" id="tourFile" accept="image/jpeg,image/png,image/webp,application/pdf" multiple hidden /><span>Choose lab results</span></label>
          <p class="tour-status" id="tourStatus" aria-live="polite"></p>
        <?php elseif ($pets): ?>
          <p><b>Try it on the Health page.</b> Open Health and use "Add lab reports or a label" to send a photo or PDF.</p>
        <?php else: ?>
          <p><b>Add your pet first.</b> After the tour SIPO helps you add your pet. Then you can send lab results from the Health page or in the chat.</p>
        <?php endif; ?>
      </div>
<?php elseif ($pets && isset($links[$s['key']])): [$href, $label] = $links[$s['key']]; ?>
      <p><a class="btn outline" href="<?= rrp_h($href) ?>"><?= rrp_h($label) ?></a></p>
<?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>

  <section class="tour-step tour-done" data-step="<?= $total - 1 ?>" data-key="finish" aria-labelledby="t-done">
    <div class="tour-head">
      <div class="tour-tile" aria-hidden="true"><em>10</em><strong>Go</strong><small>Start</small></div>
      <h1 id="t-done"><?= $pets ? 'That is SIPAW. Your pet is waiting.' : 'Now let us meet your pet' ?></h1>
      <p class="lead"><?= $pets ? 'Everything you saw is ready on your account. Ask SIPO anything, or open the dashboard.' : 'SIPO will ask a few questions in a chat: the name, the kind of pet and the birthday. It takes about a minute, and you can fill in the rest later.' ?></p>
    </div>
    <div class="tour-demo"><img src="<?= rrp_h(rrp_asset('assets/mascot/v2/happy-l.webp')) ?>" alt="" width="300" height="300" style="width:70%;height:auto" /></div>
    <div class="tour-body">
      <p><a class="btn primary" href="<?= rrp_h($endTo) ?>"><?= $pets ? 'Go to my dashboard' : 'Add my pet' ?></a></p>
      <p><a class="btn outline" href="#" id="tourAgain">See the tour again</a></p>
    </div>
  </section>
</main>

<div class="tour-bar" id="tourBar">
  <button type="button" class="btn outline back" id="tourBack" hidden>Back</button>
  <button type="button" class="btn primary" id="tourNext">Start the tour</button>
</div>
<script>window.TOUR={csrf:<?= json_encode($csrf) ?>,threadId:<?= (int) ($thread['id'] ?? 0) ?>,upload:<?= json_encode(rrp_asset('api/si/upload.php')) ?>,endTo:<?= json_encode($endTo) ?>};</script>
<?php
rrp_app_end(['nav' => false, 'scripts' => ['assets/js/upload-many.js', 'assets/js/tour.js'], 'push' => false]);
