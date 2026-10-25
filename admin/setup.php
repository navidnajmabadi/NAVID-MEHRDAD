<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';

/**
 * One-time bootstrap for the first two admin accounts (Navid + Mehrdad).
 * Requires admin_setup_secret from config. Disabled once 2+ admins exist.
 */

$cfg = rrp_load_config();
$secret = (string) ($cfg['admin_setup_secret'] ?? '');
$secretMissing = ($secret === '' || $secret === 'CHANGE_ME_ADMIN_SETUP_SECRET');
$count = rrp_admin_count();
$error = null;
$okMsg = null;

if ($count >= 2) {
    rrp_layout_head('Admin setup — SIPAW', ['body_class' => 'admin-body']);
    ?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="<?= rrp_h(rrp_asset('index.php')) ?>">
      <img src="<?= rrp_h(rrp_asset('assets/logo.jpg')) ?>" alt="" width="40" height="40" />
      <span>siPaw</span>
    </a>
    <a href="login.php">Admin login</a>
  </div>
  <h1>Setup closed</h1>
  <p class="lede">Two admin accounts already exist. Use <a href="login.php">admin login</a>.</p>
</div>
    <?php
    rrp_layout_foot();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSecret = (string) ($_POST['setup_secret'] ?? '');
    if ($secretMissing) {
        $error = 'First open config/sipaw.php (or legacy ringringpaw.php) and set admin_setup_secret to any long string you choose.';
    } elseif ($postedSecret === '' || !hash_equals($secret, $postedSecret)) {
        $error = 'That setup secret does not match admin_setup_secret in config. Copy the same value into the field below — we never show the secret on this page.';
    } elseif (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $result = rrp_admin_create(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if (!empty($result['ok'])) {
            rrp_admin_log(null, 'admin_setup_create', 'admin_users', (int) $result['admin_id'], 'Created via setup.php');
            $count = rrp_admin_count();
            if ($count >= 2) {
                rrp_flash_set('ok', 'Second admin created. Setup is now closed. Log in below.');
                rrp_redirect('login.php');
            }
            $okMsg = 'Admin created (' . $count . '/2). Create the second account (Navid or Mehrdad).';
            $_POST = [];
        } else {
            $error = $result['error'] ?? 'Could not create admin.';
        }
    }
}

rrp_layout_head('Create admin — SIPAW', ['body_class' => 'admin-body']);
?>
<div class="shell">
  <div class="app-top">
    <a class="app-brand" href="<?= rrp_h(rrp_asset('index.php')) ?>">
      <img src="<?= rrp_h(rrp_asset('assets/logo.jpg')) ?>" alt="" width="40" height="40" />
      <span>siPaw</span>
    </a>
    <a href="login.php">Admin login</a>
  </div>

  <h1>Create admin (<?= (int) $count ?>/2)</h1>
  <p class="lede">Create Navid and Mehrdad as separate admin users (one form each). This page closes after two admins exist.</p>

  <section class="panel panel-soft" aria-labelledby="secretHelpHeading">
    <h2 id="secretHelpHeading">What is the setup secret?</h2>
    <ol class="setup-steps">
      <li>Invent any long secret string yourself (like a password). Example shape: <code>your-secret-from-config</code> — pick your own.</li>
      <li>Open <code>config/sipaw.php</code> (or legacy <code>ringringpaw.php</code>) on the server (File Manager or SSH).</li>
      <li>Set <code>admin_setup_secret</code> to that string and save the file.</li>
      <li>Type the <strong>same</strong> value in the “Setup secret” field below.</li>
    </ol>
    <p class="field-hint" style="margin:10px 0 0">This page never shows your real secret. Keep it only in the config file.</p>
  </section>

  <?php if ($secretMissing): ?>
    <div class="flash flash-error" role="alert">
      First open <code>config/sipaw.php</code> (or legacy <code>ringringpaw.php</code>) and set <code>admin_setup_secret</code> to any long string you choose.
      Then come back here and enter that same string in the field below.
    </div>
  <?php endif; ?>

  <?php if ($okMsg): ?><div class="flash flash-ok" role="status"><?= rrp_h($okMsg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <form method="post" class="panel" novalidate<?= $secretMissing ? ' aria-disabled="true"' : '' ?>>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />

    <label for="setup_secret">Setup secret (must match admin_setup_secret in config)</label>
    <input
      id="setup_secret"
      name="setup_secret"
      type="password"
      required
      autocomplete="off"
      placeholder="your-secret-from-config"
      <?= $secretMissing ? 'disabled' : '' ?>
    />
    <p class="field-hint">Paste the value you put in <code>admin_setup_secret</code> — not your admin login password.</p>

    <label for="name">Admin name</label>
    <input id="name" name="name" required value="<?= rrp_h($_POST['name'] ?? '') ?>" placeholder="Navid or Mehrdad" <?= $secretMissing ? 'disabled' : '' ?> />

    <label for="email">Admin email</label>
    <input id="email" name="email" type="email" required value="<?= rrp_h($_POST['email'] ?? '') ?>" <?= $secretMissing ? 'disabled' : '' ?> />

    <label for="password">Admin password</label>
    <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" <?= $secretMissing ? 'disabled' : '' ?> />
    <p class="field-hint">At least 10 characters. This is the password for /admin/login.php.</p>

    <div class="stack-actions">
      <button type="submit" class="btn btn-primary btn-block" <?= $secretMissing ? 'disabled' : '' ?>>Create admin</button>
    </div>
  </form>
</div>
<?php rrp_layout_foot(); ?>
