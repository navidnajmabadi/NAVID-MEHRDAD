<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// Settings hub (left tab): profile, pets, home area, Lost mode, plan, help.
$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);
$plan = rrp_owner_plan($owner);
$canAdd = rrp_vm_pet_switcher($owner)['can_add'];
$initial = strtoupper(substr((string) ($owner['name'] ?? 'U'), 0, 1));
$pwError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'password') {
        $r = rrp_owner_change_password((int) $owner['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''), (string) ($_POST['new2'] ?? ''));
        if (!empty($r['ok'])) {
            rrp_flash_set('ok', 'Password changed 🔒 Other devices were logged out.');
            rrp_redirect('settings.php');
        }
        $pwError = $r['error'] ?? 'Could not change it.';
    } elseif ($act === 'timezone') {
        $tz = rrp_auth_clean_timezone((string) ($_POST['tz'] ?? ''));
        $auto = !empty($_POST['auto']) ? 1 : 0;
        if ($tz !== null) {
            rrp_db()->prepare('UPDATE owners SET timezone = ?, timezone_auto = ? WHERE id = ?')->execute([$tz, $auto, (int) $owner['id']]);
            rrp_flash_set('ok', 'Time zone saved: ' . $tz . ($auto ? ' (follows your phone)' : ' (fixed)'));
        }
        rrp_redirect('settings.php#timezone');
    } elseif ($act === 'skin') {
        rrp_ui_set_skin((string) ($_POST['skin'] ?? RRP_UI_DEFAULT_SKIN), (int) $owner['id']);
        rrp_flash_set('ok', 'Look changed ✨');
        rrp_redirect('settings.php#appearance');
    } elseif ($act === 'profile') {
        $r = rrp_onb_save_profile($owner, $_POST, false);
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Profile saved 💙' : ($r['error'] ?? 'Could not save.'));
        rrp_redirect('settings.php#profile');
    } elseif ($act === 'photo') {
        try {
            if (!empty($_POST['remove'])) {
                $path = rrp_save_pet_image(null, null, 'owners');
                rrp_db()->prepare('UPDATE owners SET avatar_path = NULL WHERE id = ?')->execute([(int) $owner['id']]);
                rrp_flash_set('ok', 'Photo removed.');
            } else {
                $path = rrp_save_pet_image($_FILES['photo'] ?? null, $owner['avatar_path'] ?? null, 'owners');
                if ($path) {
                    rrp_db()->prepare('UPDATE owners SET avatar_path = ? WHERE id = ?')->execute([$path, (int) $owner['id']]);
                    rrp_flash_set('ok', 'Photo saved 💙');
                } else {
                    rrp_flash_set('error', 'Choose a photo first.');
                }
            }
        } catch (Throwable $e) {
            rrp_flash_set('error', $e->getMessage());
        }
        rrp_redirect('settings.php#profile');
    } elseif ($act === 'report_pref') {
        $on = !empty($_POST['report_weekly']) ? 1 : 0;
        rrp_db()->prepare('UPDATE owners SET report_weekly = ? WHERE id = ?')->execute([$on, (int) $owner['id']]);
        rrp_flash_set('ok', $on ? 'Weekly health report is on 📬 It arrives on Sundays.' : 'Weekly health report is off.');
        rrp_redirect('settings.php#reports');
    } elseif ($act === 'report_test') {
        $r = rrp_report_send($owner);
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok'])
            ? 'Sent! Check your inbox (and spam) for your health report.'
            : 'We could not send the email right now. Please try again in a little while.');
        if (empty($r['ok'])) {
            error_log('settings report_test: ' . ($r['error'] ?? 'failed'));
        }
        rrp_redirect('settings.php#reports');
    } elseif ($act === 'delete_request') {
        $r = rrp_account_delete_request($owner);
        rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok'])
            ? 'Check your email: we sent a link to confirm deleting the account. It works for 30 minutes.'
            : ($r['error'] ?? 'Could not send the email.'));
        rrp_redirect('settings.php#danger');
    }
}
$csrf = rrp_csrf_token();

rrp_layout_head('Settings — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />',
    'body_class' => 'page-care',
]);
rrp_app_start(['nav' => 'settings']);
?>
<div class="topbar">
  <span></span>
  <div class="center"><h2>Settings</h2></div>
  <?php rrp_coin_badge($owner); ?>
</div>
<div class="pad stack care-page">
  <?php rrp_render_flash(); ?>

  <a class="care-status set-me" href="account.php">
    <?php if (!empty($owner['avatar_path'])): ?><img class="avatar" src="<?= rrp_h($owner['avatar_path']) ?>" alt="" width="40" height="40" /><?php else: ?><span class="avatar"><?= rrp_h($initial) ?></span><?php endif; ?>
    <div>
      <h2><?= rrp_h((string) ($owner['name'] ?? 'Your profile')) ?></h2>
      <p class="sub"><?= rrp_h((string) ($owner['email'] ?? '')) ?> · Edit profile, phone and address</p>
    </div>
  </a>

  <?php if ($nav['lost_active']): ?>
    <a class="btn set-lost" href="lost.php">🚨 Lost mode is on. Open it</a>
  <?php elseif ($pets): ?>
    <a class="btn set-lost" href="lost.php">🚨 My pet is lost</a>
  <?php endif; ?>

  <section class="care-card">
    <h3 class="label">My pets</h3>
    <ul class="set-list">
      <?php foreach ($pets as $p): ?>
        <li><a href="pet-profile.php?id=<?= (int) $p['id'] ?>"><?php rrp_pet_avatar(rrp_sipo_pet_brief($p)); ?><span><b><?= rrp_h($p['name']) ?></b><small class="meta"><?= rrp_h(ucfirst((string) ($p['type'] ?? ''))) ?><?= !empty($p['breed']) ? ' · ' . rrp_h($p['breed']) : '' ?></small></span><i aria-hidden="true">›</i></a></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($canAdd): ?>
      <a class="btn primary" href="si-chat.php?new=1" style="margin-top:10px">➕ Add a new pet</a>
      <p class="meta">SIPO sets up the new profile with you 🐾</p>
    <?php else: ?>
      <p class="sub">Free includes 1 pet for now. <a class="link" href="plus.php">Plus adds up to 3</a></p>
    <?php endif; ?>
  </section>

  <section class="care-card">
    <h3 class="label">App</h3>
    <ul class="set-list">
      <li><a href="alerts.php"><span>🔔</span><span><b>Notifications</b><small class="meta"><?= $nav['unread'] ? (int) $nav['unread'] . ' unread' : 'All caught up' ?></small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="area.php"><span>📍</span><span><b>Home area</b><small class="meta">Used for nearby Lost mode alerts</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="plus.php"><span>⭐</span><span><b><?= $plan === 'plus' ? 'Plus' : 'Free plan' ?></b><small class="meta"><?= $plan === 'plus' ? 'Plan details' : 'See Plus' ?></small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="rewards.php"><span>🐾</span><span><b>Rewards</b><small class="meta">Paw coins and invite friends</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="dashboard.php"><span>🏠</span><span><b>Home overview</b><small class="meta">All pets on one screen</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="welcome.php?again=1"><span>👋</span><span><b>Start the welcome again</b><small class="meta">Meet SIPO again, then add or finish a pet profile step by step</small></span><i aria-hidden="true">›</i></a></li>
      <li><a href="support.php"><span>💬</span><span><b>Help and support</b><small class="meta">Your tickets and replies · we reply within 7 days</small></span><i aria-hidden="true">›</i></a></li>
      <?php if (rrp_is_super_owner($owner)): ?><li><a href="<?= rrp_h(rrp_asset('admin/')) ?>"><span>🛠️</span><span><b>Admin</b></span><i aria-hidden="true">›</i></a></li><?php endif; ?>
    </ul>
  </section>

  <?php $tzNow = (string) ($owner['timezone'] ?? '') ?: rrp_sipo_owner_tz($owner)->getName(); $tzAuto = (int) ($owner['timezone_auto'] ?? 1) === 1; ?>
  <section class="care-card" id="timezone">
    <h3 class="label">Time zone</h3>
    <p class="sub">Reminders, chat times and "good morning" use <b><?= rrp_h($tzNow) ?></b> · <?= rrp_h(rrp_sipo_local_now($owner)->format('H:i')) ?> now<?= $tzAuto ? ' (follows your phone)' : ' (fixed)' ?>.</p>
    <form method="post" class="fin-grid" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="timezone" />
      <label style="grid-column:1/-1">Zone<select name="tz">
        <?php foreach (DateTimeZone::listIdentifiers() as $z): ?><option value="<?= rrp_h($z) ?>"<?= $z === $tzNow ? ' selected' : '' ?>><?= rrp_h(str_replace('_', ' ', $z)) ?></option><?php endforeach; ?>
      </select></label>
      <label style="grid-column:1/-1;flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="auto" value="1"<?= $tzAuto ? ' checked' : '' ?> style="min-height:0" /> Follow my phone's time zone automatically</label>
      <button class="btn primary" type="submit" style="grid-column:1/-1">Save time zone</button>
    </form>
  </section>

  <section class="care-card" id="profile">
    <h3 class="label">Your profile</h3>
    <form method="post" enctype="multipart/form-data" class="set-photo" id="photoForm">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="photo" />
      <?php if (!empty($owner['avatar_path'])): ?><img class="avatar" src="<?= rrp_h($owner['avatar_path']) ?>" alt="Your photo" width="64" height="64" /><?php else: ?><span class="avatar" aria-hidden="true"><?= rrp_h($initial) ?></span><?php endif; ?>
      <div>
        <label class="btn outline" for="pfPhoto">Change photo</label>
        <input id="pfPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-crop hidden />
        <?php if (!empty($owner['avatar_path'])): ?><button class="btn outline" type="button" data-crop-adjust="pfPhoto" data-src="<?= rrp_h($owner['avatar_path']) ?>">Adjust</button><button class="btn outline" type="submit" name="remove" value="1">Remove</button><?php endif; ?>
      </div>
    </form>
    <form method="post" class="auth-form" style="padding:0">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="profile" />
      <div class="field"><label for="pfName">Name</label><div class="in"><input id="pfName" name="name" required maxlength="80" value="<?= rrp_h(trim((string) ($owner['nickname'] ?? '')) ?: (string) ($owner['name'] ?? '')) ?>" /></div></div>
      <?php $pfCode = rrp_onb_country_code((string) ($owner['country'] ?? '')); ?>
      <div class="field"><label for="pfCountry">Country</label><div class="in"><select id="pfCountry" name="country">
        <option value="">Not set</option>
        <?php foreach (rrp_onb_countries() as $code => $cname): ?><option value="<?= rrp_h($code) ?>" <?= $pfCode === $code ? 'selected' : '' ?>><?= rrp_h($cname) ?></option><?php endforeach; ?>
      </select></div></div>
      <div class="field"><label for="pfCity">City</label><div class="in"><input id="pfCity" name="city" maxlength="80" value="<?= rrp_h((string) ($owner['city'] ?? '')) ?>" /></div></div>
      <button class="btn secondary" type="submit">Save profile</button>
    </form>
  </section>

  <section class="care-card" id="appearance">
    <h3 class="label">Appearance</h3>
    <form method="post" class="skin-pick">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="skin" />
      <?php foreach (['friendly' => 'Warm, soft curves, made for dog people', 'clean' => 'Light and calm, like top health apps'] as $k => $why): ?>
        <button type="submit" name="skin" value="<?= rrp_h($k) ?>" class="skin-opt" aria-pressed="<?= rrp_ui_skin() === $k ? 'true' : 'false' ?>">
          <span class="skin-swatch skin-<?= rrp_h($k) ?>" aria-hidden="true"><i></i><i></i><i></i></span>
          <b><?= rrp_h(RRP_UI_SKINS[$k]) ?></b><small><?= rrp_h($why) ?></small>
        </button>
      <?php endforeach; ?>
    </form>
  </section>

  <section class="care-card" id="notifications">
    <h3 class="label">Phone notifications</h3>
    <p class="sub" id="pnHint">Checking this device…</p>
    <div class="set-photo" style="margin:10px 0 0">
      <div>
        <button type="button" class="btn primary" id="pnEnable" hidden>Turn on</button>
        <button type="button" class="btn outline" id="pnTest">Send me a test</button>
      </div>
    </div>
    <p class="meta" id="pnResult" aria-live="polite"></p>
  </section>

  <section class="care-card" id="reports">
    <h3 class="label">Health report by email</h3>
    <p class="meta" style="margin:0 0 10px">Every Sunday SIPO emails you how each pet did: walks, potty, weight trend, vaccines and lab flags, plus a caring note.</p>
    <form method="post" class="row" style="gap:10px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="report_pref" />
      <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="report_weekly" value="1" <?= !empty($owner['report_weekly']) ? 'checked' : '' ?> onchange="this.form.submit()" /> Send me the weekly report</label>
    </form>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="report_test" />
      <button type="submit" class="btn btn-secondary">Send me one now</button>
    </form>
  </section>

  <details class="care-card set-sec" <?= $pwError ? 'open' : '' ?>>
    <summary class="label">🔒 Change password</summary>
    <?php if ($pwError): ?><div class="flash flash-error" role="alert"><?= rrp_h($pwError) ?></div><?php endif; ?>
    <form method="post" class="fin-grid set-pw">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="password" />
      <label>Current password<input type="password" name="current" required autocomplete="current-password" /></label>
      <label>New password<input type="password" name="new" required minlength="8" autocomplete="new-password" /></label>
      <label>Repeat new password<input type="password" name="new2" required minlength="8" autocomplete="new-password" /></label>
      <button class="btn primary" type="submit">Save password</button>
    </form>
    <p class="meta">Forgot it? <a class="link" href="forgot-password.php">Get a reset link by email</a></p>
  </details>

  <a class="btn outline" href="logout.php">Log out</a>

  <section class="care-card set-danger" id="danger">
    <h3 class="label">Danger zone</h3>
    <p class="sub"><b>Delete my account.</b> This removes your profile, every pet, chats with SIPO, health records, routines, posts and paw coins for good. It cannot be undone.</p>
    <ol class="meta set-steps"><li>Tap the button. We email <?= rrp_h((string) ($owner['email'] ?? 'you')) ?> a link.</li><li>Open the link within 30 minutes.</li><li>Type DELETE to confirm.</li></ol>
    <form method="post" data-confirm-delete>
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" /><input type="hidden" name="act" value="delete_request" />
      <button class="btn set-delete" type="submit">Delete my account…</button>
    </form>
  </section>
  <p class="meta" style="text-align:center"><a class="link" href="legal.php">Terms and privacy</a></p>
</div>
<?php
?>
<script>
(function () {
  var hint = document.getElementById('pnHint'), en = document.getElementById('pnEnable'), test = document.getElementById('pnTest'), out = document.getElementById('pnResult');
  function refresh() {
    if (!window.RRPPush) { setTimeout(refresh, 300); return; }
    window.RRPPush.status().then(function (s) {
      hint.textContent = s.subscribed ? 'On for this device. Reminders and SIPO reach you even when the app is closed.' : (s.hint || 'Off on this device.');
      en.hidden = !(s.supported && s.configured && !s.subscribed && s.permission !== 'denied' && !(s.ios && !s.standalone));
    });
  }
  refresh();
  en.addEventListener('click', function () {
    window.RRPPush.enable().then(function (r) { out.textContent = r && r.ok ? 'Turned on 💙' : 'Could not turn it on (' + ((r && r.error) || 'unknown') + ').'; refresh(); });
  });
  test.addEventListener('click', function () {
    out.textContent = 'Sending…';
    var fd = new FormData(); fd.append('csrf', <?= json_encode($csrf) ?>);
    fetch('api/push-test.php', { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.devices) { out.textContent = j.error || 'Could not send.'; return; }
      out.textContent = (j.ok ? 'Sent. It should pop up now. ' : 'It did not go through. ') +
        j.devices.map(function (d) { return d.service + ': ' + (d.ok ? 'delivered to the push service (' + d.status + ')' : 'failed (' + (d.error || d.status) + ')'); }).join(' · ');
    }).catch(function () { out.textContent = 'Could not reach the server.'; });
  });
})();
</script>
<?php
rrp_app_end(['nav' => 'settings', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
