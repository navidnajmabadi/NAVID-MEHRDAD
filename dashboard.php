<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (rrp_verify_csrf($_POST['csrf'] ?? null)) {
        if ($action === 'lost_trigger') {
            $result = rrp_trigger_lost((int) $owner['id'], (int) ($_POST['pet_id'] ?? 0));
            if (!empty($result['ok'])) {
                $n = (int) ($result['recipients'] ?? 0);
                rrp_flash_set(
                    'ok',
                    $n > 0
                        ? "Lost mode sent to {$n} nearby owner" . ($n === 1 ? '' : 's') . '.'
                        : 'Lost mode started. No owners nearby yet — alerts will still go out on schedule.'
                );
            } else {
                rrp_flash_set('error', $result['error'] ?? 'Could not start alert.');
            }
            rrp_redirect('lost.php');
        }
        if ($action === 'lost_found') {
            $result = rrp_mark_lost_found((int) $owner['id'], (int) ($_POST['alert_id'] ?? 0));
            if (!empty($result['ok'])) {
                $alert = rrp_lost_by_id((int) ($_POST['alert_id'] ?? 0));
                $petName = $alert['pet_name'] ?? 'your pet';
                rrp_redirect('rescued.php?pet=' . rawurlencode($petName));
            }
            rrp_flash_set('error', $result['error'] ?? 'Could not mark found.');
            rrp_redirect('dashboard.php');
        }
    }
}

$pets = rrp_pets_by_owner((int) $owner['id']);
$notes = rrp_notifications_for_owner((int) $owner['id'], 4);
$nearbyLost = rrp_lost_inbox_for_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

// Home: current pet first, today's care at a glance, one tap to Health, Routines or SIPO.
$activeId = $pets ? rrp_page_pet_id($owner, $pets) : 0;
$active = null;
foreach ($pets as $p) {
    if ((int) $p['id'] === $activeId) {
        $active = $p;
    }
}
if (!$active && $pets) {
    $active = $pets[0];
    $activeId = (int) $active['id'];
}
usort($pets, static fn($a, $b) => ((int) $b['id'] === $activeId) <=> ((int) $a['id'] === $activeId));

$hour = (int) rrp_sipo_local_now($owner)->format('G');
$greet = $hour < 5 ? 'Good night' : ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'));
$name = rrp_sipo_owner_nickname($owner);

$status = null;
$due = [];
$walk = null;
$food = null;
$lostOpen = null;
if ($active) {
    try {
        $status = rrp_vm_health($owner, $active)['status'];
    } catch (Throwable $e) {
    }
    $now = rrp_sipo_local_now($owner)->format('Y-m-d');
    foreach (rrp_care_open_reminders($activeId, 20) as $r) {
        $local = (new DateTimeImmutable((string) $r['due_at'], new DateTimeZone('UTC')))->setTimezone(rrp_sipo_owner_tz($owner));
        if ($local->format('Y-m-d') <= $now) {
            $due[] = ['title' => $r['title'], 'when' => $local->format('Y-m-d') < $now ? 'Overdue' : $local->format('H:i'), 'late' => $local->format('Y-m-d') < $now];
        }
    }
    try {
        $walk = rrp_walk_summary($owner, $active);
        $food = rrp_food_today($owner, $active);
    } catch (Throwable $e) {
    }
    $lostOpen = rrp_open_lost_for_pet($activeId);
}
$age = $active ? rrp_vm_age_label($active) : null;

rrp_layout_head('Home — SIPAW');
rrp_app_start(['nav' => 'settings']);
?>
<div class="topbar home2-top">
  <a href="dashboard.php" aria-label="SIPAW home" class="home2-logo"><?php rrp_logo_wordmark(); ?></a>
  <?php rrp_coin_badge($owner); ?>
</div>

<div class="pad stack home2">
  <?php rrp_render_flash(); ?>
  <div class="home2-hello">
    <h1><?= rrp_h($greet) ?>, <?= rrp_h($name) ?></h1>
    <p class="sub"><?= $active ? 'Here is ' . rrp_h($active['name']) . '’s day. Ask SIPO anything.' : 'Let’s meet your pet. SIPO sets everything up with you.' ?></p>
  </div>

  <?php if ($nearbyLost): ?>
    <a class="home2-lost" href="lost-alert.php?id=<?= (int) $nearbyLost[0]['id'] ?>">
      <b>🚨 Lost nearby: <?= rrp_h($nearbyLost[0]['pet_name']) ?></b><small>Keep an eye out on your walks. Tap for the photo.</small>
    </a>
  <?php endif; ?>

  <?php if (!$pets): ?>
    <section class="care-card home2-empty">
      <h2>Add your first pet 🐾</h2>
      <p class="sub">Tell SIPO your pet’s name and kind. Health, routines and reminders fill in as you chat.</p>
      <a class="btn primary" href="si-chat.php?new=1">Add my pet with SIPO</a>
    </section>
  <?php else: ?>
    <section class="care-card home2-hero">
      <div class="home2-photo">
        <?php if (!empty($active['image_path'])): ?>
          <img src="<?= rrp_h($active['image_path']) ?>" alt="<?= rrp_h($active['name']) ?>" />
        <?php else: ?>
          <svg viewBox="0 0 200 200" aria-hidden="true"><use href="#<?= ($active['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg>
        <?php endif; ?>
      </div>
      <div class="home2-info">
        <h2><?= rrp_h($active['name']) ?></h2>
        <p class="sub"><?= rrp_h(rrp_pet_type_label($active)) ?><?= $active['breed'] ? ' · ' . rrp_h($active['breed']) : '' ?><?= $age ? ' · ' . rrp_h($age) : '' ?></p>
        <?php if ($lostOpen): ?>
          <a class="home2-status is-lost" href="lost.php">Lost mode is on</a>
        <?php elseif ($status): ?>
          <a class="home2-status is-<?= rrp_h($status['state']) ?>" href="care.php"><?= rrp_h($status['headline']) ?></a>
        <?php endif; ?>
      </div>
      <div class="home2-actions">
        <a class="btn primary" href="care.php">Health</a>
        <a class="btn outline" href="routines.php">Routines</a>
        <a class="btn outline" href="pet-profile.php?id=<?= (int) $activeId ?>">Profile</a>
      </div>
    </section>

    <section class="care-card">
      <h3 class="label">Today</h3>
      <ul class="rt-list">
        <?php foreach (array_slice($due, 0, 4) as $d): ?>
          <li><span class="rt-when data<?= $d['late'] ? ' is-late' : '' ?>"><?= rrp_h($d['when']) ?></span><b class="rt-title"><?= rrp_h((string) $d['title']) ?></b></li>
        <?php endforeach; ?>
        <?php if (!$due): ?><li><span class="rt-when data">✓</span><b class="rt-title">Nothing due today</b><a class="link" href="routines.php">See all</a></li><?php endif; ?>
        <?php if ($walk): ?>
          <li><span class="rt-when data"><?= (int) $walk['today_minutes'] ?><?= $walk['goal'] ? '/' . (int) $walk['goal'] : '' ?> min</span><b class="rt-title">🦮 Walks</b><a class="link" href="walk.php">Start a walk</a></li>
        <?php endif; ?>
        <?php if ($food): ?>
          <li><span class="rt-when data"><?= (int) $food['eaten_kcal'] ?> kcal</span><b class="rt-title">🍽️ Food<?= $food['percent'] !== null ? ' · ' . (int) $food['percent'] . '% of the day' : '' ?></b><a class="link" href="care.php#sec-food">Details</a></li>
        <?php endif; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($notes): ?>
    <section class="care-card">
      <h3 class="label">Latest</h3>
      <ul class="rt-list">
        <?php foreach ($notes as $n): if (($n['type'] ?? '') === 'scan') continue; ?>
          <li><span class="rt-when data"><?= rrp_h(substr((string) $n['created_at'], 5, 5)) ?></span><b class="rt-title"><?= rrp_h((string) $n['title']) ?></b></li>
        <?php endforeach; ?>
      </ul>
      <a class="link" href="alerts.php">All notifications</a>
    </section>
  <?php endif; ?>
</div>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
