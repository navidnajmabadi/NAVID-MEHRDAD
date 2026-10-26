<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$ownerId = (int) $owner['id'];
$limit = rrp_owner_pet_limit($owner);
$count = rrp_pet_count($ownerId, true);
$atCap = $count >= $limit;

$session = rrp_pet_register_get_active($ownerId);
$messages = $session ? rrp_pet_register_messages((int) $session['id']) : [];
$csrf = rrp_csrf_token();

rrp_layout_head('Register a pet with SIPO — SIPAW', [
    'description' => 'SIPO asks the questions — conversational pet registration.',
    'body_class' => 'page-pet-register',
]);
rrp_app_start(['nav' => false, 'class' => 'app-pet-register']);
?>
<div class="scr sipo-reg" style="display:flex;flex-direction:column;min-height:100%">
  <div class="topbar">
    <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
    <div class="center"><span class="label">SIPO · Register pet</span></div>
    <span style="width:40px"></span>
  </div>

  <div class="sipo-reg-hero">
    <div class="sipo-reg-mascot" aria-hidden="true">
      <?php if (is_readable(__DIR__ . '/assets/mascot/sipo-idle.svg')): ?>
        <img src="<?= rrp_h(rrp_asset('assets/mascot/sipo-idle.svg')) ?>" alt="" width="72" height="72" />
      <?php else: ?>
        <span class="sipo-reg-dot"></span>
      <?php endif; ?>
    </div>
    <div>
      <h1 class="title" style="font-size:1.35rem;margin:0">Let’s meet your pet</h1>
      <p class="sub" style="margin:6px 0 0">I’ll ask a few things — no government form.<?= rrp_is_super_owner($owner) ? ' Super access: unlimited pets.' : ' Limit: ' . (int) $count . '/' . (int) $limit . '.' ?></p>
    </div>
  </div>

  <?php if ($atCap): ?>
    <div class="flash flash-error" role="alert">You’re at your active pet limit.<?= rrp_is_super_owner($owner) ? '' : ' Archive a pet or upgrade to Plus.' ?></div>
    <?php if (!rrp_is_super_owner($owner)): ?>
      <p style="padding:0 16px"><a class="btn primary" href="plus.php">See Plus</a></p>
    <?php endif; ?>
  <?php else: ?>
  <div id="sipoRegLog" class="sipo-reg-log" aria-live="polite">
    <?php if ($messages === []): ?>
      <div class="sipo-reg-bubble sipo">Hi — I’m SIPO. Tap start and I’ll walk you through registering your pet.</div>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <div class="sipo-reg-bubble <?= ($m['sender_role'] ?? '') === 'sipo' ? 'sipo' : 'owner' ?>"><?= rrp_h((string) $m['body']) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form id="sipoRegForm" class="sipo-reg-composer" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" />
    <input type="hidden" id="sipoSessionId" value="<?= $session ? (int) $session['id'] : 0 ?>" />
    <label class="sr-only" for="sipoRegInput">Message SIPO</label>
    <input id="sipoRegInput" type="text" maxlength="1900" placeholder="Type your answer…" <?= $session ? '' : 'disabled' ?> />
    <button type="button" id="sipoRegStart" class="btn <?= $session ? 'ghost' : 'primary' ?>" <?= $session ? 'hidden' : '' ?>>Start</button>
    <button type="submit" id="sipoRegSend" class="btn primary" <?= $session ? '' : 'hidden' ?>>Send</button>
  </form>
  <p class="sub sipo-reg-alt"><a class="link" href="pet-form.php?classic=1">Prefer the classic form?</a></p>
  <?php endif; ?>
</div>

<script>
(function(){
  var log = document.getElementById('sipoRegLog');
  var form = document.getElementById('sipoRegForm');
  if (!form || !log) return;
  var input = document.getElementById('sipoRegInput');
  var sessionEl = document.getElementById('sipoSessionId');
  var startBtn = document.getElementById('sipoRegStart');
  var sendBtn = document.getElementById('sipoRegSend');
  var csrf = form.querySelector('[name=csrf]').value;
  var apiStart = <?= json_encode(rrp_asset('api/pets/register-start.php')) ?>;
  var apiSend = <?= json_encode(rrp_asset('api/pets/register-send.php')) ?>;

  function bubble(role, text){
    var d = document.createElement('div');
    d.className = 'sipo-reg-bubble ' + (role === 'sipo' ? 'sipo' : 'owner');
    d.textContent = text;
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
  }

  function renderMessages(msgs){
    log.innerHTML = '';
    (msgs || []).forEach(function(m){
      bubble(m.sender_role === 'sipo' ? 'sipo' : 'owner', m.body || '');
    });
  }

  function activateComposer(sessionId){
    sessionEl.value = String(sessionId || 0);
    input.disabled = false;
    startBtn.hidden = true;
    sendBtn.hidden = false;
    input.focus();
  }

  if (startBtn){
    startBtn.addEventListener('click', function(){
      startBtn.disabled = true;
      fetch(apiStart, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify({csrf: csrf})
      }).then(function(r){ return r.json(); }).then(function(data){
        startBtn.disabled = false;
        if (!data || !data.ok){
          bubble('sipo', (data && data.error) || 'Could not start.');
          return;
        }
        renderMessages(data.messages || []);
        activateComposer(data.session && data.session.id);
      }).catch(function(){
        startBtn.disabled = false;
        bubble('sipo', 'Network snag — try again.');
      });
    });
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var text = (input.value || '').trim();
    var sid = parseInt(sessionEl.value, 10) || 0;
    if (!text || !sid) return;
    sendBtn.disabled = true;
    bubble('owner', text);
    input.value = '';
    fetch(apiSend, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: JSON.stringify({csrf: csrf, session_id: sid, message: text})
    }).then(function(r){ return r.json(); }).then(function(data){
      sendBtn.disabled = false;
      if (!data || !data.ok){
        bubble('sipo', (data && data.error) || 'Could not send.');
        if (data && data.messages) renderMessages(data.messages);
        return;
      }
      renderMessages(data.messages || []);
      if (data.done && data.pet_id){
        setTimeout(function(){ location.href = 'tag.php?id=' + data.pet_id; }, 900);
      }
    }).catch(function(){
      sendBtn.disabled = false;
      bubble('sipo', 'Network snag — try again.');
    });
  });
})();
</script>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
