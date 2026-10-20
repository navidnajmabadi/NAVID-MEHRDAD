<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$nav = rrp_owner_nav_context((int) $owner['id']);
$pets = rrp_pets_by_owner((int) $owner['id']);
$petName = $pets ? (string) $pets[0]['name'] : 'your pet';

rrp_layout_head('Chat — siPaw');
rrp_app_start(['nav' => 'chat']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Finder chat</h2></div>
  <span style="width:40px"></span>
</div>

<div class="chathead">
  <span class="av">F</span>
  <div>
    <b>Finder</b>
    <small>About <?= rrp_h($petName) ?> · <span class="soon">Coming soon</span></small>
  </div>
  <span class="status idle data">Offline</span>
</div>

<p class="privacy">
  <svg aria-hidden="true"><use href="#i-shield"/></svg>
  Phone numbers stay private. Location is shared only if you agree.
</p>

<div class="msgs">
  <div class="msg them">
    Hi — I think I found <?= rrp_h($petName) ?> near the park.
    <time>Demo</time>
  </div>
  <div class="msg me">
    Thank you. Please keep them calm. I’m on my way.
    <time>Demo</time>
  </div>
  <div class="msg them loc">
    <div class="mini"><svg viewBox="0 0 400 230"><use href="#map"/></svg></div>
    <p>Shared location once</p>
    <time>Demo</time>
  </div>
</div>

<div class="pad" style="padding-top:0">
  <div class="box">
    <p style="margin:0 0 6px;font-weight:500">Chat is almost ready</p>
    <p class="sub" style="margin:0;font-size:13px">
      This shell matches the siPaw prototype. Live finder ↔ owner messaging will wire here without changing the look.
      For now, finders reach you via the public scan page call button and shared location.
    </p>
  </div>
</div>

<div class="quick" aria-hidden="true">
  <button type="button" disabled>On my way</button>
  <button type="button" disabled>Keep them safe</button>
  <button type="button" disabled>What’s the magic word?</button>
</div>

<form class="composer" onsubmit="return false;" aria-label="Message composer">
  <input type="text" placeholder="Message (coming soon)" disabled />
  <button type="button" class="send" disabled aria-label="Send"><svg aria-hidden="true"><use href="#i-send"/></svg></button>
</form>
<?php
rrp_app_end(['nav' => 'chat', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
