<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

// First sign-in only: SIPO says hello, explains SIPAW, asks the owner's name and country, then
// hands over to the SIPO chat where the pet is added.
$owner = rrp_require_owner();
if (!empty($owner['onboarded_at']) && !isset($_GET['again'])) {
    rrp_redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rrp_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Try again.';
    } else {
        $r = rrp_onb_save_profile($owner, $_POST, true);
        if (!empty($r['ok'])) {
            rrp_redirect('si-chat.php?new=1');
        }
        $error = $r['error'] ?? 'Could not save that.';
    }
}

$countries = rrp_onb_countries();
$saved = rrp_onb_country_code((string) ($owner['country'] ?? ''));
$guess = $saved !== '' ? '' : rrp_onb_guess_country($owner);
$selected = (string) ($_POST['country'] ?? ($saved !== '' ? $saved : $guess));
$nameValue = (string) ($_POST['name'] ?? (trim((string) ($owner['nickname'] ?? '')) ?: trim((string) ($owner['name'] ?? ''))));

rrp_layout_head('Welcome to SIPAW', [
    'description' => 'Meet SIPO, your pet’s health companion.',
    'body_class' => 'page-welcome',
]);
rrp_app_start(['nav' => false]);
?>
<main class="wel" id="wel">
  <div class="wel-dots" aria-hidden="true"><i class="on"></i><i></i><i></i></div>

  <form method="post" class="wel-form" id="welForm" novalidate>
    <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

    <section class="wel-step on" data-step="1">
      <img class="wel-sipo" src="<?= rrp_h(rrp_asset('assets/mascot/v2/fullbody.webp')) ?>" alt="SIPO waving" width="285" height="380" />
      <div class="wel-bubble">
        <h1>Hi, I’m SIPO 👋</h1>
        <p>I’m a soft-hearted helper whose whole job is keeping your pet healthy and happy.</p>
        <ul>
          <li><b>I remember everything</b>: vaccines, meals, walks, vet notes.</li>
          <li><b>I remind you</b> before something is due, so nothing slips.</li>
          <li><b>I read lab reports</b> and tell you what looks different.</li>
          <li><b>I’m always one tap away</b>, if you ever worry.</li>
        </ul>
      </div>
      <button class="btn primary" type="button" data-next>Nice to meet you</button>
    </section>

    <section class="wel-step" data-step="2">
      <img class="wel-sipo sm" src="<?= rrp_h(rrp_asset('assets/mascot/v2/fullbody-s.webp')) ?>" alt="" width="150" height="200" />
      <div class="wel-bubble">
        <h1>What should I call you?</h1>
        <p>A first name or a nickname is perfect.</p>
      </div>
      <div class="field">
        <label for="welName">Your name</label>
        <div class="in"><input id="welName" name="name" required maxlength="80" autocomplete="given-name" value="<?= rrp_h($nameValue) ?>" /></div>
      </div>
      <button class="btn primary" type="button" data-next>Continue</button>
    </section>

    <section class="wel-step" data-step="3">
      <img class="wel-sipo sm" src="<?= rrp_h(rrp_asset('assets/mascot/v2/fullbody-s.webp')) ?>" alt="" width="150" height="200" />
      <div class="wel-bubble">
        <h1>Where are you?</h1>
        <p><?= $guess !== '' ? 'I guessed ' . rrp_h($countries[$guess] ?? '') . ' from your connection. Change it if I got it wrong.' : 'It helps me find vets and check the weather where you live.' ?></p>
      </div>
      <div class="field">
        <label for="welCountry">Country</label>
        <div class="in"><select id="welCountry" name="country" autocomplete="country">
          <option value="">Choose your country</option>
          <?php foreach ($countries as $code => $name): ?>
            <option value="<?= rrp_h($code) ?>" <?= $selected === $code ? 'selected' : '' ?>><?= rrp_h($name) ?></option>
          <?php endforeach; ?>
        </select></div>
      </div>
      <div class="field">
        <label for="welCity">City <small>(optional, you can add it later in Profile)</small></label>
        <div class="in"><input id="welCity" name="city" maxlength="80" autocomplete="address-level2" value="<?= rrp_h((string) ($_POST['city'] ?? ($owner['city'] ?? ''))) ?>" /></div>
      </div>
      <button class="btn primary" type="submit">Let’s meet your pet 🐾</button>
    </section>
  </form>
</main>
<script>
(function () {
  var steps = [].slice.call(document.querySelectorAll('.wel-step')), dots = [].slice.call(document.querySelectorAll('.wel-dots i')), cur = 0;
  var hasError = <?= $error ? 'true' : 'false' ?>;
  function show(n) {
    cur = n;
    steps.forEach(function (s, i) { s.classList.toggle('on', i === n); });
    dots.forEach(function (d, i) { d.classList.toggle('on', i <= n); });
    var f = steps[n].querySelector('input,select'); if (f && n > 0) f.focus({ preventScroll: true });
  }
  document.querySelectorAll('[data-next]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (cur === 1 && !document.getElementById('welName').value.trim()) { document.getElementById('welName').focus(); return; }
      show(Math.min(cur + 1, steps.length - 1));
    });
  });
  document.getElementById('welName').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.querySelector('[data-step="2"] [data-next]').click(); } });
  if (hasError) show(1);
  document.documentElement.classList.add('wel-js');
})();
</script>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
