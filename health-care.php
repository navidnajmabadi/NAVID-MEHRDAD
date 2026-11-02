<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$owner = rrp_require_owner();
$pets = rrp_pets_by_owner((int) $owner['id']);
$nav = rrp_owner_nav_context((int) $owner['id']);

$petId = rrp_page_pet_id($owner, $pets);
$pet = null;
foreach ($pets as $p) {
    if ($petId === (int) $p['id'] || (!$petId && !$pet)) {
        $pet = $p;
        $petId = (int) $p['id'];
    }
}

$tab = (string) ($_GET['tab'] ?? 'vax');
if (!in_array($tab, ['vax', 'labs', 'meds', 'feed', 'vet'], true)) {
    $tab = 'vax';
}

$dbReady = true;
$vaxList = $medList = $feedList = $vetList = $labExams = [];
$fedToday = [];

if ($pet && $_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $redirect = 'health-care.php?pet=' . $petId . '&tab=' . rawurlencode($tab);
    try {
        if ($action === 'vax_add') {
            $r = rrp_vax_add($petId, $_POST);
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Vaccine saved.' : ($r['error'] ?? 'Could not save.'));
            $tab = 'vax';
        } elseif ($action === 'lab_add') {
            $rows = [];
            foreach ((array) ($_POST['lab_label'] ?? []) as $i => $label) {
                $rows[] = ['label' => $label, 'value' => $_POST['lab_value'][$i] ?? '', 'range' => $_POST['lab_range'][$i] ?? '', 'unit' => $_POST['lab_unit'][$i] ?? ''];
            }
            $r = rrp_labs_manual_add($owner, $pet, (string) ($_POST['lab_kind'] ?? ''), (string) ($_POST['lab_date'] ?? ''), $rows);
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Saved ' . (int) $r['saved'] . ' indicator' . ($r['saved'] === 1 ? '' : 's') . '. See them on the Health page.' : ($r['error'] ?? 'Could not save.'));
            $tab = 'labs';
        } elseif ($action === 'lab_del') {
            rrp_labs_exam_delete($petId, (int) ($_POST['id'] ?? 0));
            rrp_flash_set('ok', 'Test removed.');
            $tab = 'labs';
        } elseif ($action === 'vax_del') {
            rrp_vax_delete($petId, (int) ($_POST['id'] ?? 0));
            rrp_flash_set('ok', 'Vaccine removed.');
            $tab = 'vax';
        } elseif ($action === 'med_add') {
            $r = rrp_med_add($petId, $_POST);
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Medication saved.' : ($r['error'] ?? 'Could not save.'));
            $tab = 'meds';
        } elseif ($action === 'med_del') {
            rrp_med_delete($petId, (int) ($_POST['id'] ?? 0));
            rrp_flash_set('ok', 'Medication removed.');
            $tab = 'meds';
        } elseif ($action === 'feed_add') {
            $r = rrp_feed_add($petId, $_POST);
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Meal saved.' : ($r['error'] ?? 'Could not save.'));
            $tab = 'feed';
        } elseif ($action === 'feed_del') {
            rrp_feed_delete($petId, (int) ($_POST['id'] ?? 0));
            rrp_flash_set('ok', 'Meal removed.');
            $tab = 'feed';
        } elseif ($action === 'feed_mark') {
            $sid = (int) ($_POST['id'] ?? 0);
            if (!empty($_POST['fed'])) {
                rrp_feed_mark($petId, $sid);
            } else {
                rrp_feed_unmark($petId, $sid);
            }
            $tab = 'feed';
        } elseif ($action === 'vet_add') {
            $r = rrp_vet_add($petId, $_POST);
            rrp_flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'Appointment saved.' : ($r['error'] ?? 'Could not save.'));
            $tab = 'vet';
        } elseif ($action === 'vet_del') {
            rrp_vet_delete($petId, (int) ($_POST['id'] ?? 0));
            rrp_flash_set('ok', 'Appointment removed.');
            $tab = 'vet';
        }
    } catch (Throwable $e) {
        error_log('health-care POST: ' . $e->getMessage());
        rrp_flash_set('error', 'Health tables may be missing — run sql/005_health.sql in phpMyAdmin.');
    }
    rrp_redirect('health-care.php?pet=' . $petId . '&tab=' . rawurlencode($tab));
}

if ($pet) {
    try {
        $vaxList = rrp_vax_list($petId);
        $labExams = rrp_labs_exam_list($petId);
        $medList = rrp_meds_list($petId);
        $feedList = rrp_feed_list($petId);
        $fedToday = rrp_feed_today_map($petId);
        $vetList = rrp_vet_list($petId);
    } catch (Throwable $e) {
        error_log('health-care load: ' . $e->getMessage());
        $dbReady = false;
    }
}

rrp_layout_head('Health — SIPAW');
rrp_app_start(['nav' => 'health']);
?>
<div class="topbar">
  <a class="iconbtn" href="dashboard.php" aria-label="Back"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Health</h2><?php if ($pet): ?><span class="label"><?= rrp_h($pet['name']) ?></span><?php endif; ?></div>
  <a class="iconbtn" href="<?= $pet ? 'pet-form.php?id=' . (int) $pet['id'] : 'pet-form.php' ?>" aria-label="Edit"><svg aria-hidden="true"><use href="#i-edit"/></svg></a>
</div>

<?php rrp_render_flash(); ?>

<?php if (!$pets): ?>
  <div class="pad"><div class="box"><p class="sub" style="margin:0">Add a pet to track vaccines, meds, and food.</p><a class="btn primary" style="margin-top:12px" href="si-chat.php?new=1">Add a pet with SIPO</a></div></div>
<?php else: ?>
  <div class="pets" role="group" aria-label="Pets" style="padding-top:4px;padding-bottom:12px">
    <?php foreach ($pets as $p): ?>
      <a class="petchip" href="health-care.php?pet=<?= (int) $p['id'] ?>&tab=<?= rrp_h($tab) ?>" aria-pressed="<?= (int) $p['id'] === $petId ? 'true' : 'false' ?>">
        <span class="ph">
          <?php if (!empty($p['image_path'])): ?><img src="<?= rrp_h($p['image_path']) ?>" alt="" /><?php else: ?>
          <svg viewBox="0 0 200 200"><use href="#<?= ($p['type'] ?? '') === 'cat' ? 'pet-cat' : 'pet-dog' ?>"/></svg><?php endif; ?>
        </span>
        <?= rrp_h($p['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="tabs" role="tablist">
    <?php foreach (['vax' => 'Vaccines', 'labs' => 'Labs', 'meds' => 'Meds', 'feed' => 'Food', 'vet' => 'Vet'] as $k => $label): ?>
      <a href="health-care.php?pet=<?= (int) $petId ?>&tab=<?= $k ?>" role="tab" aria-pressed="<?= $tab === $k ? 'true' : 'false' ?>" style="display:flex;align-items:center;justify-content:center;height:42px;font-family:var(--f-display);font-weight:500;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:<?= $tab === $k ? 'var(--text)' : 'var(--sub)' ?>;text-decoration:none;position:relative">
        <?= rrp_h($label) ?>
        <?php if ($tab === $k): ?><span style="position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--blue)"></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="pad" style="display:flex;gap:12px;flex-wrap:wrap;padding-top:12px">
    <a class="link" href="wellness.php?pet=<?= (int) $petId ?>">Wellness</a>
    <a class="link" href="reminders.php?pet=<?= (int) $petId ?>">Reminders</a>
    <a class="link" href="si-chat.php?pet=<?= (int) $petId ?>">Ask SIPO</a>
  </div>
  <div class="pad stack" style="margin-top:16px;gap:14px">
    <?php if (!$dbReady): ?>
      <div class="box">
        <p style="margin:0 0 6px;font-weight:500">Health tables not installed</p>
        <p class="sub" style="margin:0;font-size:13px">Run <span class="data">sql/005_health.sql</span> in Hostinger phpMyAdmin.</p>
      </div>
    <?php elseif ($tab === 'vax'): ?>
      <?php if (!empty($pet['medical_vaccines']) || !empty($pet['medical_next_due'])): ?>
        <div class="box">
          <div class="row between"><span class="label">Profile notes</span><svg class="ico" aria-hidden="true"><use href="#i-syringe"/></svg></div>
          <?php if (!empty($pet['medical_vaccines'])): ?>
            <p style="margin:12px 0 0;white-space:pre-wrap;font-size:14px"><?= rrp_h($pet['medical_vaccines']) ?></p>
          <?php endif; ?>
          <?php if (!empty($pet['medical_next_due'])): ?>
            <dl class="kv" style="margin-top:12px"><dt>Next due (profile)</dt><dd class="data"><?= rrp_h($pet['medical_next_due']) ?></dd></dl>
          <?php endif; ?>
          <a class="link" style="display:inline-block;margin-top:10px;font-size:13px" href="pet-form.php?id=<?= (int) $petId ?>">Edit profile notes</a>
        </div>
      <?php endif; ?>

      <div class="box">
        <span class="label">Vaccination records</span>
        <?php if (!$vaxList): ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No structured vaccine records yet.</p>
        <?php else: ?>
          <div class="list" style="margin-top:10px">
            <?php foreach ($vaxList as $v): ?>
              <div class="li">
                <div style="flex:1">
                  <b><?= rrp_h((string) $v['name']) ?></b>
                  <small>
                    <?php if (!empty($v['given_on'])): ?>Given <?= rrp_h((string) $v['given_on']) ?><?php endif; ?>
                    <?php if (!empty($v['next_due'])): ?><?= !empty($v['given_on']) ? ' · ' : '' ?>Due <?= rrp_h((string) $v['next_due']) ?><?php endif; ?>
                    <?php if (!empty($v['notes'])): ?><br><?= rrp_h((string) $v['notes']) ?><?php endif; ?>
                  </small>
                </div>
                <form method="post" onsubmit="return confirm('Remove this vaccine record?');">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="vax_del" />
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>" />
                  <button type="submit" class="iconbtn" aria-label="Remove" style="width:36px;height:36px">×</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" class="box stack" style="gap:10px">
        <span class="label">Add vaccine</span>
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="vax_add" />
        <?php
        $vaxSpecies = in_array($pet['type'] ?? '', ['dog', 'cat'], true) ? (string) $pet['type'] : 'dog';
        $vaxOpts = [];
        foreach (rrp_care_catalog($vaxSpecies, rrp_care_region($owner, $pet)) as $row) {
            if (($row['group'] ?? '') === 'vaccine') {
                $vaxOpts[] = ['title' => (string) $row['title'], 'days' => (int) ($row['interval_days'] ?? 365)];
            }
        }
        ?>
        <div class="field"><label for="vax_pick">Vaccine</label><div class="in"><select id="vax_pick" name="name_pick" required>
          <option value="">Choose a vaccine</option>
          <?php foreach ($vaxOpts as $o): ?><option value="<?= rrp_h($o['title']) ?>" data-days="<?= (int) $o['days'] ?>"><?= rrp_h($o['title']) ?></option><?php endforeach; ?>
          <option value="__other">Other (type it)</option>
        </select></div></div>
        <div class="field" id="vaxOtherWrap" hidden><label for="vax_name">Vaccine name</label><div class="in"><input id="vax_name" name="name" maxlength="120" placeholder="Name of the vaccine" /></div></div>
        <div class="field"><label for="vax_given">Date given</label><div class="in"><input id="vax_given" name="given_on" type="date" required /></div></div>
        <div class="field"><label for="vax_due">Next due <small>(filled in for you, change it if your vet said otherwise)</small></label><div class="in"><input id="vax_due" name="next_due" type="date" /></div></div>
        <div class="field"><label for="vax_notes">Notes</label><div class="in"><input id="vax_notes" name="notes" maxlength="500" /></div></div>
        <button type="submit" class="btn primary">Save vaccine</button>
      </form>

    <?php elseif ($tab === 'labs'): ?>
      <form method="post" class="box stack" style="gap:10px" id="labForm">
        <span class="label">Add test results</span>
        <p class="sub" style="margin:0;font-size:13px">Type what the report says. Or send a photo or PDF to SIPO on the Health page and it fills this in for you.</p>
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="lab_add" />
        <div class="field"><label for="lab_kind">Kind of test</label><div class="in"><select id="lab_kind" name="lab_kind">
          <?php foreach (RRP_LAB_KINDS as $k => $kd): ?><option value="<?= rrp_h($k) ?>"><?= rrp_h($kd[0]) ?></option><?php endforeach; ?>
        </select></div></div>
        <div class="field"><label for="lab_date">Date of the test</label><div class="in"><input id="lab_date" name="lab_date" type="date" required max="<?= rrp_h(rrp_sipo_local_now($owner)->format('Y-m-d')) ?>" /></div></div>
        <div id="labRows" class="lab-rows"></div>
        <button type="button" class="btn outline" id="labMore">+ Add another indicator</button>
        <button type="submit" class="btn primary">Save results</button>
      </form>
      <?php foreach (RRP_LAB_KINDS as $k => $kd): ?>
        <datalist id="dl_<?= rrp_h($k) ?>"><?php foreach ($kd[1] as $name): ?><option value="<?= rrp_h($name) ?>"></option><?php endforeach; ?></datalist>
      <?php endforeach; ?>

      <div class="box">
        <span class="label">Saved tests</span>
        <?php if (!$labExams): ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No tests yet.</p>
        <?php else: ?>
          <div class="list" style="margin-top:10px">
            <?php foreach ($labExams as $e): ?>
              <div class="li">
                <div style="flex:1">
                  <b><?= rrp_h((string) $e['title']) ?> · <?= rrp_h((string) $e['examined_on']) ?></b>
                  <small><?php foreach ($e['results'] as $i => $res): $val = $res['value_num'] !== null ? rtrim(rtrim(number_format((float) $res['value_num'], 2, '.', ''), '0'), '.') : (string) $res['value_text']; ?><?= $i ? ' · ' : '' ?><?= rrp_h((string) $res['analyte_label']) ?> <?= rrp_h($val) ?><?= $res['unit'] ? ' ' . rrp_h((string) $res['unit']) : '' ?><?= in_array($res['flag'], ['low', 'high'], true) ? ' (' . rrp_h($res['flag']) . ')' : '' ?><?php endforeach; ?></small>
                </div>
                <form method="post" onsubmit="return confirm('Remove this test and its numbers?');">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="lab_del" />
                  <input type="hidden" name="id" value="<?= (int) $e['id'] ?>" />
                  <button type="submit" class="iconbtn" aria-label="Remove" style="width:36px;height:36px">×</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'meds'): ?>
      <?php if (!empty($pet['medical_pills']) || !empty($pet['medical_conditions'])): ?>
        <div class="box">
          <span class="label">Profile notes</span>
          <?php if (!empty($pet['medical_pills'])): ?>
            <p style="margin:12px 0 0;font-size:14px"><?= rrp_h($pet['medical_pills']) ?></p>
          <?php endif; ?>
          <?php if (!empty($pet['medical_conditions'])): ?>
            <p style="margin:12px 0 0;font-size:14px"><span class="label">Conditions</span><br><?= rrp_h($pet['medical_conditions']) ?></p>
          <?php endif; ?>
          <a class="link" style="display:inline-block;margin-top:10px;font-size:13px" href="pet-form.php?id=<?= (int) $petId ?>">Edit profile notes</a>
        </div>
      <?php endif; ?>

      <div class="box">
        <span class="label">Medication reminders</span>
        <?php if (!$medList): ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No scheduled meds yet. Add a time to get reminders.</p>
        <?php else: ?>
          <div class="list" style="margin-top:10px">
            <?php foreach ($medList as $m): ?>
              <div class="li">
                <div style="flex:1">
                  <b><?= rrp_h((string) $m['name']) ?></b>
                  <small class="data"><?= rrp_h(substr((string) $m['schedule_time'], 0, 5)) ?> UTC<?= !empty($m['dose_note']) ? ' · ' . rrp_h((string) $m['dose_note']) : '' ?><?= empty($m['active']) ? ' · paused' : '' ?></small>
                </div>
                <form method="post" onsubmit="return confirm('Remove this medication?');">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="med_del" />
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>" />
                  <button type="submit" class="iconbtn" aria-label="Remove" style="width:36px;height:36px">×</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" class="box stack" style="gap:10px">
        <span class="label">Add medication</span>
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="med_add" />
        <div class="field"><label for="med_name">Name</label><div class="in"><input id="med_name" name="name" required maxlength="120" placeholder="Heartworm" /></div></div>
        <div class="field"><label for="med_dose">Dose note</label><div class="in"><input id="med_dose" name="dose_note" maxlength="255" placeholder="1 tablet" /></div></div>
        <div class="field"><label for="med_time">Time (UTC)</label><div class="in"><input id="med_time" name="schedule_time" type="time" required /></div></div>
        <button type="submit" class="btn primary">Save medication</button>
      </form>

    <?php elseif ($tab === 'feed'): ?>
      <div class="box">
        <span class="label">Today’s meals</span>
        <?php if (!$feedList): ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No feeding schedule yet.</p>
        <?php else: ?>
          <div class="list" style="margin-top:10px">
            <?php
            $done = 0;
            foreach ($feedList as $f):
              $isFed = !empty($fedToday[(int) $f['id']]);
              if ($isFed) {
                  $done++;
              }
            ?>
              <div class="li">
                <form method="post" style="display:contents">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="feed_mark" />
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>" />
                  <input type="hidden" name="fed" value="<?= $isFed ? '0' : '1' ?>" />
                  <button type="submit" class="check" aria-checked="<?= $isFed ? 'true' : 'false' ?>" aria-label="Mark fed">
                    <svg><use href="#i-check"/></svg>
                  </button>
                </form>
                <div style="flex:1">
                  <b><?= rrp_h((string) $f['label']) ?></b>
                  <small class="data"><?= rrp_h(substr((string) $f['feed_time'], 0, 5)) ?> UTC<?= !empty($f['portion_note']) ? ' · ' . rrp_h((string) $f['portion_note']) : '' ?></small>
                </div>
                <form method="post" onsubmit="return confirm('Remove this meal?');">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="feed_del" />
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>" />
                  <button type="submit" class="iconbtn" aria-label="Remove" style="width:36px;height:36px">×</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
          <?php
            $pct = count($feedList) ? (int) round(100 * $done / count($feedList)) : 0;
          ?>
          <div class="meter" style="margin-top:16px" aria-label="<?= $pct ?>% fed today"><i style="width:<?= $pct ?>%"></i></div>
        <?php endif; ?>
      </div>

      <form method="post" class="box stack" style="gap:10px">
        <span class="label">Add meal</span>
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="feed_add" />
        <div class="field"><label for="feed_label">Meal</label><div class="in"><input id="feed_label" name="label" required maxlength="80" placeholder="Breakfast" /></div></div>
        <div class="field"><label for="feed_time">Time (UTC)</label><div class="in"><input id="feed_time" name="feed_time" type="time" required /></div></div>
        <div class="field"><label for="feed_portion">Portion</label><div class="in"><input id="feed_portion" name="portion_note" maxlength="255" placeholder="80 g dry" /></div></div>
        <button type="submit" class="btn primary">Save meal</button>
      </form>

    <?php else: ?>
      <div class="box">
        <span class="label">Vet appointments</span>
        <?php if (!$vetList): ?>
          <p class="sub" style="margin:12px 0 0;font-size:13px">No appointments on file.</p>
        <?php else: ?>
          <div class="list" style="margin-top:10px">
            <?php foreach ($vetList as $a):
              $past = strtotime((string) $a['appointment_at'] . ' UTC') < time();
            ?>
              <div class="li">
                <div style="flex:1">
                  <b><?= rrp_h((string) $a['title']) ?></b>
                  <small class="data">
                    <?= rrp_h((string) $a['appointment_at']) ?> UTC
                    <?= !empty($a['clinic_name']) ? ' · ' . rrp_h((string) $a['clinic_name']) : '' ?>
                    <?= $past ? ' · past' : '' ?>
                    <?php if (!empty($a['notes'])): ?><br><?= rrp_h((string) $a['notes']) ?><?php endif; ?>
                  </small>
                </div>
                <form method="post" onsubmit="return confirm('Remove this appointment?');">
                  <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="vet_del" />
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>" />
                  <button type="submit" class="iconbtn" aria-label="Remove" style="width:36px;height:36px">×</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" class="box stack" style="gap:10px">
        <span class="label">Add appointment</span>
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" />
        <input type="hidden" name="action" value="vet_add" />
        <div class="field"><label for="vet_title">Title</label><div class="in"><input id="vet_title" name="title" required maxlength="160" placeholder="Annual checkup" /></div></div>
        <div class="field"><label for="vet_clinic">Clinic</label><div class="in"><input id="vet_clinic" name="clinic_name" maxlength="160" /></div></div>
        <div class="field"><label for="vet_when">When (UTC)</label><div class="in"><input id="vet_when" name="appointment_at" type="datetime-local" required /></div></div>
        <div class="field"><label for="vet_notes">Notes</label><div class="in"><input id="vet_notes" name="notes" maxlength="500" /></div></div>
        <button type="submit" class="btn primary">Save appointment</button>
      </form>
    <?php endif; ?>
    <div class="gap"></div>
  </div>
<?php endif; ?>
<?php
?>
<script>
(function () {
  var pick = document.getElementById('vax_pick');
  if (pick) {
    var other = document.getElementById('vaxOtherWrap'), name = document.getElementById('vax_name');
    var given = document.getElementById('vax_given'), due = document.getElementById('vax_due'), touched = false;
    due.addEventListener('input', function () { touched = true; });
    function fill() {
      var days = parseInt(pick.selectedOptions[0] && pick.selectedOptions[0].getAttribute('data-days'), 10);
      if (touched || !days || !given.value) return;
      var d = new Date(given.value + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + days);
      due.value = d.toISOString().slice(0, 10);
    }
    pick.addEventListener('change', function () {
      var isOther = pick.value === '__other';
      other.hidden = !isOther; name.required = isOther;
      if (!isOther) name.value = pick.value;
      fill();
    });
    given.addEventListener('change', fill);
  }
  var rows = document.getElementById('labRows');
  if (rows) {
    var kind = document.getElementById('lab_kind');
    function add() {
      var d = document.createElement('div'); d.className = 'lab-row';
      d.innerHTML = '<div class="field"><label>Indicator</label><div class="in"><input name="lab_label[]" list="dl_' + kind.value + '" maxlength="120" placeholder="e.g. ALT" /></div></div>' +
        '<div class="field"><label>Current value</label><div class="in"><input name="lab_value[]" inputmode="decimal" maxlength="40" placeholder="e.g. 85" /></div></div>' +
        '<div class="field"><label>Normal range</label><div class="in"><input name="lab_range[]" maxlength="40" placeholder="e.g. 10-100" /></div></div>' +
        '<div class="field"><label>Unit <small>(optional)</small></label><div class="in"><input name="lab_unit[]" maxlength="40" placeholder="e.g. U/L" /></div></div>';
      rows.appendChild(d);
    }
    add();
    document.getElementById('labMore').addEventListener('click', add);
    kind.addEventListener('change', function () {
      [].forEach.call(rows.querySelectorAll('input[name="lab_label[]"]'), function (i) { i.setAttribute('list', 'dl_' + kind.value); });
    });
  }
})();
</script>
<?php
rrp_app_end(['nav' => 'health', 'unread' => $nav['unread'], 'lost_active' => $nav['lost_active']]);
