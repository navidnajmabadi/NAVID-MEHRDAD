<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Routines (right tab): everything scheduled for the current pet, grouped by when.
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$petId = rrp_page_pet_id($owner, $pets);
$pet = null;
foreach ($pets as $p) {
    if ((int) $p['id'] === $petId) {
        $pet = $p;
    }
}
$nav = rrp_owner_nav_context((int) $owner['id']);

if ($pet && $_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $id = (int) ($_POST['reminder_id'] ?? 0);
    try {
        if (($_POST['act'] ?? '') === 'done') {
            $r = rrp_care_reminder_outcome($owner, $pet, $id, 'done');
            $msg = 'Done ✅ Nice work!' . (!empty($r['next_due']) ? ' Next one ' . $r['next_due'] . '.' : '');
        } else {
            $tomorrow = rrp_sipo_local_now($owner)->modify('+1 day')->format('Y-m-d') . ' 09:00';
            $r = rrp_care_reminder_outcome($owner, $pet, $id, 'rescheduled', $tomorrow);
            $msg = 'Moved to tomorrow 🗓️';
        }
    } catch (Throwable $e) {
        // Never a crash page for a tap: report it, keep the owner on Routines.
        error_log('routines action failed: reminder=' . $id . ' ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $r = ['ok' => false, 'error' => 'Could not update this one. Please try again.'
            . (rrp_is_super_owner($owner) ? ' [Admin] ' . mb_substr($e->getMessage(), 0, 200) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : '')];
    }
    rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? $msg : ($r['error'] ?? 'Could not update.'));
    rrp_redirect('routines.php?pet=' . $petId);
}

$groups = ['overdue' => [], 'today' => [], 'week' => [], 'later' => []];
$meds = [];
$meals = [];
$walk = null;
$unscheduled = [];
if ($pet) {
    $tz = rrp_sipo_owner_tz($owner);
    $now = rrp_sipo_local_now($owner);
    $today = $now->format('Y-m-d');
    $weekEnd = $now->modify('+7 days')->format('Y-m-d');
    foreach (rrp_care_open_reminders($petId, 50) as $r) {
        $local = (new DateTimeImmutable((string) $r['due_at'], new DateTimeZone('UTC')))->setTimezone($tz);
        $day = $local->format('Y-m-d');
        $r['when'] = $day === $today ? $local->format('H:i') : $local->format('D j M');
        $key = $day < $today ? 'overdue' : ($day === $today ? 'today' : ($day <= $weekEnd ? 'week' : 'later'));
        $groups[$key][] = $r;
    }
    foreach (rrp_care_status($owner, $pet) as $s) {
        if ($s['state'] === 'unknown') {
            $unscheduled[] = $s;
        }
    }
    foreach (rrp_treatments_for_pet($petId, true) as $t) {
        foreach ($t['meds'] as $m) {
            if ((int) $m['active']) {
                $meds[] = $m;
            }
        }
    }
    try {
        $meals = array_values(array_filter(rrp_feed_list($petId), static fn($f) => (int) ($f['active'] ?? 1) === 1));
    } catch (Throwable $e) {
    }
    try {
        $walk = rrp_walk_summary($owner, $pet);
    } catch (Throwable $e) {
    }
}
// Medicines and meals in one list, by time of day.
$daily = [];
foreach ($meds as $m) {
    $daily[] = ['time' => substr((string) $m['schedule_time'], 0, 5), 'title' => '💊 ' . $m['name'], 'note' => (string) $m['dose_note']];
}
foreach ($meals as $f) {
    $daily[] = ['time' => substr((string) $f['feed_time'], 0, 5), 'title' => '🍽️ ' . ($f['label'] ?: 'Meal'), 'note' => (string) ($f['portion_note'] ?? '')];
}
usort($daily, static fn($a, $b) => strcmp($a['time'], $b['time']));
$labels = ['overdue' => '⏰ Overdue', 'today' => '☀️ Today', 'week' => '📅 This week', 'later' => '🗓️ Later'];
$total = array_sum(array_map('count', $groups));
$csrf = rrp_csrf_token();

rrp_layout_head('Routines — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'routines']);
?>
<div class="topbar">
  <span></span>
  <div class="center"><h2>Routines</h2></div>
  <?php rrp_coin_badge($owner); ?>
</div>
<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>
  <?php if (!$pet): ?>
    <section class="care-card"><p class="sub">Add a pet and SIPO will plan the routines 🐾</p><a class="btn primary" href="si-chat.php?new=1" style="margin-top:10px">Add a pet</a></section>
  <?php else: ?>
    <?php rrp_pet_switcher(rrp_vm_pet_switcher($owner)); ?>

    <section class="care-status <?= $groups['overdue'] ? '' : 'is-good' ?>">
      <?php rrp_pet_avatar(rrp_sipo_pet_brief($pet)); ?>
      <div>
        <h2><?= $groups['overdue'] ? count($groups['overdue']) . ' overdue' : ($groups['today'] ? count($groups['today']) . ' for today' : 'All on track 🎉') ?></h2>
        <p class="sub"><?= $total ?> scheduled for <?= rrp_h($pet['name']) ?>. Ask SIPO to add or move one.</p>
      </div>
    </section>

    <?php foreach ($groups as $g => $rows): if (!$rows) continue; ?>
      <section class="care-card rt-group rt-<?= $g ?>">
        <h3 class="label"><?= $labels[$g] ?></h3>
        <ul class="rt-list">
          <?php foreach ($rows as $r): ?>
            <li>
              <span class="rt-when data"><?= rrp_h($r['when']) ?></span>
              <b class="rt-title"><?= rrp_h((string) $r['title']) ?></b>
              <?php if ($g === 'overdue' || $g === 'today'): ?>
                <form method="post" class="rt-acts">
                  <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>" />
                  <button class="btn primary" name="act" value="done" type="submit">Done</button>
                  <button class="btn outline" name="act" value="tomorrow" type="submit">Tomorrow</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>

    <?php if ($daily || ($walk && $walk['goal'])): ?>
      <section class="care-card">
        <h3 class="label">Every day</h3>
        <ul class="rt-list">
          <?php foreach ($daily as $d): ?><li><span class="rt-when data"><?= rrp_h($d['time']) ?></span><b class="rt-title"><?= rrp_h($d['title']) ?></b><small class="meta"><?= rrp_h($d['note']) ?></small></li><?php endforeach; ?>
          <?php if ($walk && $walk['goal']): ?><li><span class="rt-when data"><?= (int) $walk['today_minutes'] ?>/<?= (int) $walk['goal'] ?>m</span><b class="rt-title">🦮 Walk goal</b><a class="link" href="walk.php">Start a walk</a></li><?php endif; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($unscheduled): ?>
      <section class="care-card">
        <h3 class="label">Not set yet</h3>
        <p class="sub"><?= rrp_h(implode(' · ', array_slice(array_column($unscheduled, 'title'), 0, 6))) ?></p>
        <a class="btn outline" href="si-chat.php" style="margin-top:10px">Tell SIPO when they were last done</a>
      </section>
    <?php endif; ?>

    <a class="link" href="care.php">See the full care plan</a>
  <?php endif; ?>
</div>
<?php
rrp_app_end(['nav' => 'routines', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
