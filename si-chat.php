<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$petId = isset($_GET['pet']) ? (int) $_GET['pet'] : (int) ($pets[0]['id'] ?? 0);
$pet = $petId ? rrp_pet_by_id($petId, (int) $owner['id']) : null;
$nav = rrp_owner_nav_context((int) $owner['id']);
$gate = rrp_si_gate($owner);
$csrf = rrp_csrf_token();

rrp_layout_head('SIPO — SIPAW');
rrp_app_start(['nav' => 'chat']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>SIPO</h2></div>
  <a class="iconbtn" href="plus.php" aria-label="Plan"><span class="data"><?= rrp_h(strtoupper(rrp_owner_plan($owner))) ?></span></a>
</div>

<div class="pad stack" style="gap:12px;flex:1;min-height:0;display:flex;flex-direction:column">
  <?php if (!$pets): ?>
    <p class="sub">Add a pet first, then chat with SIPO.</p>
    <a class="btn primary" href="pet-form.php">Add pet</a>
  <?php else: ?>
    <form method="get" class="row between" style="gap:8px;align-items:center">
      <label class="label" for="pet">Pet</label>
      <select id="pet" name="pet" onchange="this.form.submit()" style="flex:1;min-height:44px;background:var(--panel);color:var(--text);border:1px solid var(--line);padding:0 12px">
        <?php foreach ($pets as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $petId ? 'selected' : '' ?>><?= rrp_h($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if (empty($gate['ok'])): ?>
      <div class="box blue">
        <p class="label"><?= ($gate['gate'] ?? '') === 'rate_limit' ? 'Daily limit' : 'Plus' ?></p>
        <p class="sub" style="margin-top:8px;font-size:14px"><?= rrp_h($gate['error'] ?? 'Upgrade required') ?></p>
        <?php if (($gate['gate'] ?? '') === 'plus_required'): ?>
          <a class="btn primary" href="plus.php" style="margin-top:12px">See Plus</a>
          <a class="link" href="forum.php" style="margin-top:12px;display:inline-block">Stay on Forum</a>
        <?php endif; ?>
      </div>
    <?php elseif (!empty($gate['teaser'])): ?>
      <p class="sub" style="font-size:13px">Free teaser: a few SIPO messages. Plus unlocks full SI.</p>
    <?php endif; ?>

    <div id="log" class="stack" style="flex:1;overflow:auto;gap:10px;padding-bottom:12px" aria-live="polite"></div>

    <form id="send" class="stack" style="gap:8px;flex:none">
      <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" />
      <input type="hidden" name="pet_id" value="<?= (int) $petId ?>" />
      <div class="field"><div class="in area"><textarea id="body" name="body" rows="2" maxlength="1900" placeholder="Ask SIPO about <?= rrp_h($pet['name'] ?? 'your pet') ?>…" <?= empty($gate['ok']) ? 'disabled' : '' ?>></textarea></div></div>
      <button class="btn primary" type="submit" <?= empty($gate['ok']) ? 'disabled' : '' ?>>Send</button>
    </form>
  <?php endif; ?>
</div>
<script>
(function(){
  var petId = <?= (int) $petId ?>;
  var csrf = <?= json_encode($csrf) ?>;
  var log = document.getElementById('log');
  var form = document.getElementById('send');
  if(!log || !petId) return;

  function bubble(role, text){
    var d = document.createElement('div');
    d.className = 'box';
    d.style.alignSelf = role === 'assistant' || role === 'owner' && String(text).indexOf('[SIPO]')===0 ? 'flex-start' : (role==='assistant'?'flex-start':'flex-end');
    if(role==='user' || role==='owner'){ d.style.borderColor = 'var(--blue)'; }
    d.innerHTML = '<span class="label">'+(role==='assistant'||String(text).indexOf('[SIPO]')===0?'SIPO':'You')+'</span><p class="sub" style="margin-top:8px;font-size:14px;white-space:pre-wrap"></p>';
    d.querySelector('p').textContent = String(text).replace(/^\[SIPO\]\s*/, '');
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
  }

  fetch(<?= json_encode(rrp_asset('api/si/messages.php')) ?>+'?pet_id='+petId, {credentials:'same-origin'})
    .then(function(r){return r.json()}).then(function(j){
      if(!j.ok) return;
      (j.messages||[]).forEach(function(m){ bubble(m.sender_role, m.body); });
      if(!(j.messages||[]).length){
        bubble('assistant', 'Hi — I am SIPO 💙 Ask me anything about care, reminders, or habits.');
      }
    });

  if(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var body = document.getElementById('body');
      var text = (body.value||'').trim();
      if(!text) return;
      body.value = '';
      bubble('user', text);
      fetch(<?= json_encode(rrp_asset('api/si/send.php')) ?>, {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({csrf:csrf, pet_id:petId, body:text})
      }).then(function(r){return r.json()}).then(function(j){
        if(j.assistant && j.assistant.body) bubble('assistant', j.assistant.body);
        else if(!j.ok) bubble('assistant', j.error || 'Could not send.');
        if(j.gate==='plus_required') location.href='plus.php';
      }).catch(function(){ bubble('assistant', 'Network error.'); });
    });
  }
})();
</script>
<?php
rrp_app_end(['nav' => 'chat', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
