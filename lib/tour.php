<?php
declare(strict_types=1);

require_once __DIR__ . '/views/health_dashboard.php';

/**
 * The guided tour (tour.php) and the demos shown on the landing page.
 *
 * Every demo is a small animated page (tour-demo.php?d=<key>) that runs on SAMPLE data about Milo, a beagle. Nothing
 * here reads or writes the visitor's own pets, chats or memory: a tour can be started again at any time without
 * changing anything. The Health demos use the real Health dashboard code (lib/views/health_dashboard.php) fed with
 * sample data shaped exactly like rrp_vm_health(), so what people see is what the app shows.
 */

/** The demo scenes a page may ask for. */
const RRP_TOUR_DEMOS = ['health', 'vet', 'chat', 'routines', 'lost', 'money', 'walk', 'coins'];

/**
 * @return list<array{key:string, no:string, sym:string, name:string, title:string, lead:string, how:list<string>, demo:string, action?:string}>
 */
function rrp_tour_steps(?array $owner = null): array
{
    $steps = [
        ['key' => 'health', 'no' => '02', 'sym' => 'He', 'name' => 'Health', 'demo' => 'health', 'action' => 'labs',
            'title' => "See your pet's health in one place",
            'lead' => 'Routines, weight, vet treatments, food and lab results sit on one page. Only values outside the normal range blink, so you see what needs attention first.',
            'how' => ['Send a photo or a PDF of the lab results (blood, urine or stool) to SIPO, or use "Add lab results" on the Health page.',
                'SIPO reads the numbers, saves them and deletes the file.',
                'Tap a result to see its normal range and how it changed.']],
        ['key' => 'vet', 'no' => '03', 'sym' => 'Vt', 'name' => 'Vet link', 'demo' => 'vet',
            'title' => 'Share the dashboard with your vet',
            'lead' => 'Make a private, read-only link to the same dashboard. Your vet sees the numbers, the trends and the routines, with no account needed.',
            'how' => ['Open Health, find "Share with your vet" and tap Create vet link.',
                'Copy the link and send it by message or email. It works for ' . RRP_SHARE_DAYS . ' days.',
                'Turn it off at any time. You can see how many times it was opened.']],
        ['key' => 'chat', 'no' => '04', 'sym' => 'Si', 'name' => 'SIPO chat', 'demo' => 'chat',
            'title' => 'Tell SIPO, it looks after the rest',
            'lead' => 'Chat the way you talk. SIPO saves what you say, works out the next date and reminds you. Ask what is due and it lists how to care for your pet and your routines.',
            'how' => ['Say what happened: "Milo had his rabies shot today".',
                'Ask: "What do I need to do for Milo this week?"',
                'Tap the buttons SIPO offers instead of typing.']],
        ['key' => 'routines', 'no' => '05', 'sym' => 'Ro', 'name' => 'Routines', 'demo' => 'routines',
            'title' => 'Routines show what is due, late and on track',
            'lead' => 'Vaccines, parasite control, teeth, nails and check-ups, each with its next date. Each one is closed until you tap it, then it explains what it is and what to do.',
            'how' => ['Tap a routine to read what it is and what to do.',
                'Press Done, or tell SIPO "done", and the next date is set.',
                'Daily routines such as tooth brushing sit under Every day. Your own reminders ring at the time you chose.']],
        ['key' => 'lost', 'no' => '06', 'sym' => 'Lo', 'name' => 'Lost pet', 'demo' => 'lost',
            'title' => 'If your pet is ever lost, one tap starts the search',
            'lead' => 'SIPAW tells nearby owners, repeats the alert later and gives you a search plan. You stay in charge and end it with one tap when your pet is home.',
            'how' => ['Open Lost pet and tap "My pet is lost".',
                'Tell SIPO where your pet was last seen, so the alert starts there.',
                'Share the poster and watch for sightings. Tap Found when your pet is back.']],
        ['key' => 'money', 'no' => '07', 'sym' => 'Mo', 'name' => 'Money', 'demo' => 'money',
            'title' => 'Know what your pet costs',
            'lead' => 'Add costs by chatting or on the Expenses page. See the month, the categories and how it compares with a usual month.',
            'how' => ['Tell SIPO: "I spent 30 on food".',
                'Open Expenses to see each cost and the total for the month.',
                'Costs are kept per pet, so you always know which one they belong to.']],
        ['key' => 'walk', 'no' => '08', 'sym' => 'Wk', 'name' => 'Walks', 'demo' => 'walk',
            'title' => 'Walks with a map',
            'lead' => 'Start a walk and keep the screen on. SIPAW draws your path and counts the distance and the time. Save it, and your dog earns you paw coins.',
            'how' => ['Open Daily, then Start walk.',
                'Keep SIPAW open on the screen while you walk, so the phone keeps the GPS on.',
                'Finish and save. The route stays in your walk history.']],
        ['key' => 'coins', 'no' => '09', 'sym' => 'Co', 'name' => 'Coins', 'demo' => 'coins',
            'title' => 'Paw coins reward good care, friends earn you free months',
            'lead' => 'Vaccines, lab reports, walks and meals earn paw coins. A perfect week takes 5% off Plus, up to 20%, and coins can lower the price when you subscribe.',
            'how' => ['Look after your pet as usual. The coins arrive by themselves.',
                'Do every routine and check in on 4 days of the week to make it a perfect week.',
                'Invite friends: every 3 who subscribe give you one free month.']],
    ];
    // The walk step is for dogs: skip it when every pet the owner has is not a dog.
    if ($owner && function_exists('rrp_pets_by_owner')) {
        try {
            $pets = rrp_pets_by_owner((int) $owner['id']);
            if ($pets && !array_filter($pets, static fn($p) => ($p['type'] ?? '') === 'dog')) {
                $steps = array_values(array_filter($steps, static fn($s) => $s['key'] !== 'walk'));
            }
        } catch (Throwable $e) {
        }
    }
    return $steps;
}

/** Sample dashboard data for Milo, shaped like rrp_vm_health(). Dates are relative to today so it always looks current. */
function rrp_tour_sample_vm(): array
{
    $d = static fn(int $days): string => gmdate('Y-m-d', time() + $days * 86400);
    $pt = static fn(int $days, float $v, ?float $lo, ?float $hi): array => ['date' => $d($days), 'value' => $v, 'ref_low' => $lo, 'ref_high' => $hi,
        'flag' => ($hi !== null && $v > $hi) ? 'high' : (($lo !== null && $v < $lo) ? 'low' : 'normal')];
    $lab = static function (string $key, string $label, string $unit, array $pts, string $alert, bool $sharp = false): array {
        $latest = end($pts);
        $prev = $pts[count($pts) - 2]['value'] ?? null;
        $chg = $prev ? round(100 * ($latest['value'] - $prev) / abs($prev), 1) : null;
        $dir = $prev === null ? 'flat' : ($latest['value'] > $prev * 1.05 ? 'up' : ($latest['value'] < $prev * 0.95 ? 'down' : 'flat'));
        return ['key' => $key, 'label' => $label, 'unit' => $unit, 'points' => $pts, 'latest' => $latest, 'direction' => $dir, 'change_pct' => $chg,
            'sharp' => $sharp, 'watch' => false, 'alert' => $alert];
    };
    $labs = [
        $lab('eos_abs', 'Absolute eosinophils', '/µL', [$pt(-210, 780, 0, 1250), $pt(-100, 1040, 0, 1250), $pt(-9, 1360, 0, 1250)], 'red', true),
        $lab('alt', 'ALT', 'U/L', [$pt(-210, 48, 10, 100), $pt(-100, 52, 10, 100), $pt(-9, 55, 10, 100)], 'ok'),
        $lab('creat', 'Creatinine', 'mg/dL', [$pt(-210, 0.9, 0.5, 1.6), $pt(-100, 1.0, 0.5, 1.6), $pt(-9, 1.0, 0.5, 1.6)], 'ok'),
        $lab('glu', 'Glucose', 'mg/dL', [$pt(-210, 88, 70, 120), $pt(-100, 92, 70, 120), $pt(-9, 90, 70, 120)], 'ok'),
    ];
    $routine = static fn(string $key, string $title, string $state, ?string $last, ?string $next, string $when): array => ['key' => $key, 'title' => $title,
        'state' => $state, 'last_done' => $last, 'next_due' => $next, 'when' => $when, 'note' => '', 'sources' => []];
    $routines = [
        $routine('dog_flea_tick', 'Flea and tick prevention', 'overdue', $d(-41), $d(-11), 'Every month'),
        $routine('dog_worming', 'Worming (intestinal parasites)', 'due_soon', $d(-80), $d(6), 'Every 3 months'),
        $routine('dog_checkup', 'Vet health check', 'due_soon', $d(-350), $d(15), 'Once a year'),
        $routine('dog_rabies', 'Rabies vaccine', 'ok', $d(-120), $d(245), 'Every year'),
        $routine('dog_core_dhpp', 'Core vaccine (distemper, adenovirus, parvovirus)', 'ok', $d(-120), $d(245), 'Every year'),
        $routine('dog_teeth', 'Tooth brushing', 'ok', $d(-1), $d(0), 'Every day'),
        $routine('dog_nails', 'Nail trim', 'ok', $d(-12), $d(16), 'Every 4 weeks'),
    ];
    return [
        'pet' => ['id' => 0, 'name' => 'Milo', 'kind' => 'Dog', 'avatar' => rrp_asset('assets/img/pets/milo.svg'), 'initial' => 'M',
            'breed' => 'Beagle', 'sex' => 'male', 'age' => '4 years', 'weight_kg' => 11.8, 'conditions' => null],
        'status' => ['state' => 'attention', 'headline' => 'Milo needs a look', 'alerts' => ['Absolute eosinophils is high', 'Flea and tick prevention is overdue']],
        'routines' => $routines,
        'treatments' => [],
        'labs' => $labs,
        'weights' => [['date' => $d(-180), 'value' => 11.2], ['date' => $d(-140), 'value' => 11.3], ['date' => $d(-100), 'value' => 11.5],
            ['date' => $d(-60), 'value' => 11.4], ['date' => $d(-30), 'value' => 11.7], ['date' => $d(-4), 'value' => 11.8]],
        'food' => [
            'need' => ['kcal' => 1059, 'note' => 'An estimate for a neutered adult dog of this weight. Your vet knows best.'],
            'today' => ['percent' => 62, 'eaten_kcal' => 657, 'meals' => 2, 'unknown_meals' => 0],
            'foods' => [['name' => 'Salmon and rice kibble', 'food_kind' => 'dry', 'kcal_per_kg' => 3650, 'daily_grams' => 290, 'days_left' => 9, 'warnings' => []]],
            'preferences' => [],
        ],
        'region_label' => 'USA / Canada guidelines (AAHA, CAPC)',
    ];
}

/** The tabs and tiles on top of the sample Health page, shaped like rrp_vm_health_top(). */
function rrp_tour_sample_top(array $vm): array
{
    return [
        'sections' => ['sec-overview' => 'Overview', 'sec-profile' => 'Profile', 'sec-labs' => 'Labs', 'sec-food' => 'Food', 'sec-routines' => 'Routines'],
        'kpis' => [
            ['label' => 'Status', 'value' => 'Check', 'sub' => '2 alerts', 'state' => 'warn'],
            ['label' => 'Routines', 'value' => '71%', 'sub' => '1 overdue', 'state' => 'bad'],
            ['label' => 'Weight', 'value' => rrp_fmt_weight(11.8), 'sub' => '+0.1 ' . rrp_units()['weight'], 'state' => 'ok'],
            ['label' => 'Labs', 'value' => (string) count($vm['labs']), 'sub' => '1 out of range', 'state' => 'bad'],
            ['label' => 'Food today', 'value' => '657 kcal', 'sub' => '62% of 1059', 'state' => 'ok'],
            ['label' => 'Walks (7 days)', 'value' => '184 min', 'sub' => '5 day streak', 'state' => 'ok'],
        ],
    ];
}

/** Distance in the visitor's unit for the walk demo: [number, unit label, pace label]. */
function rrp_tour_walk_numbers(): array
{
    $u = rrp_units();
    $km = 2.4;
    $dist = rrp_distance_show($km);
    $minutes = 28 + 10 / 60;
    $pace = $minutes / $dist;
    return ['dist' => round($dist, 2), 'unit' => $u['distance'], 'pace_unit' => $u['pace'], 'pace' => sprintf('%d:%02d', (int) $pace, (int) round(($pace - (int) $pace) * 60)), 'seconds' => 1690];
}

/** The Si Blue paw coin used across the demos. */
function rrp_tour_coin(int $size = 22): string
{
    return function_exists('rrp_coin_svg') ? rrp_coin_svg($size) : '';
}

// ---------------------------------------------------------------------------
// The demo scenes (each echoes the inside of the <body> of tour-demo.php)
// ---------------------------------------------------------------------------

function rrp_tour_demo(string $key): void
{
    match ($key) {
        'health' => rrp_tour_demo_health(false),
        'vet' => rrp_tour_demo_health(true),
        'chat' => rrp_tour_demo_chat(),
        'routines' => rrp_tour_demo_routines(),
        'lost' => rrp_tour_demo_lost(),
        'money' => rrp_tour_demo_money(),
        'walk' => rrp_tour_demo_walk(),
        'coins' => rrp_tour_demo_coins(),
        default => null,
    };
}

/** "Health" and "Vet link": the real sample dashboard, plus a lab upload (health) or a share sheet (vet). */
function rrp_tour_demo_health(bool $vet): void
{
    $vm = rrp_tour_sample_vm();
    $top = rrp_tour_sample_top($vm);
    ?>
<div class="td-screen td-<?= $vet ? 'vet' : 'health' ?>">
  <div class="topbar care-top"><div class="center" style="text-align:left"><h2>Health · Milo</h2></div></div>
  <div class="td-viewport">
    <div class="td-scroll pad stack care-page">
      <?php rrp_view_health_top($top); ?>
      <?php rrp_view_health_dashboard($vm, true); ?>
    </div>
  </div>
<?php if (!$vet): ?>
  <div class="td-upload" aria-hidden="true">
    <div class="td-doc"><b>Blood test</b><small>lab-results.pdf</small><i></i><i></i><i></i></div>
    <p class="td-reading">SIPO is reading the results…</p>
    <p class="td-ok"><b>Saved 4 results</b><small>+15 paw coins · the file is deleted</small></p>
  </div>
<?php else: ?>
  <p class="td-vetbar" aria-hidden="true">Vet view · read only · no account needed</p>
  <div class="td-sheet" aria-hidden="true">
    <h3>Share with your vet</h3>
    <p class="sub">A private, read-only link to this page. Works for <?= RRP_SHARE_DAYS ?> days; turn it off anytime.</p>
    <span class="btn primary td-create">Create vet link</span>
    <div class="td-link"><span>sipaw.nakilab.com/vet.php?t=<b>••••••••••</b></span><i>Copy</i></div>
    <p class="meta td-views">Opened 0 times · only people with the link can see it</p>
  </div>
<?php endif; ?>
  <span class="td-finger" aria-hidden="true"></span>
</div>
<?php
}

function rrp_tour_demo_chat(): void
{
    ?>
<div class="td-screen td-chat">
  <header class="sipo-top td-chat-top">
    <div class="sipo-who"><img src="<?= rrp_h(rrp_asset('assets/mascot/v2/happy-s.webp')) ?>" width="44" height="44" alt=""><div><h1 class="sipo-title">SIPO</h1><p class="sipo-status">Looking after Milo</p></div></div>
    <span class="td-chip">Beagle · 4 years · 11.8 kg</span>
  </header>
  <div class="td-chatlog">
    <div class="sipo-msg from-me td-m" style="--d:.4s"><p>Milo had his rabies shot today 💉</p></div>
    <div class="sipo-typing sipo-msg from-sipo td-m td-typing" style="--d:1.4s"><b class="dots"><span></span><span></span><span></span></b></div>
    <div class="sipo-msg from-sipo td-m" style="--d:3s"><p>Saved. Rabies is done. The next one is due in a year, and I will remind you the week before.</p></div>
    <div class="sipo-msg from-me td-m" style="--d:5.2s"><p>What do I need to do for Milo this week?</p></div>
    <div class="sipo-msg from-sipo td-m td-list" style="--d:7s">
      <p>Here is Milo's week:</p>
      <ul><li><b>Flea and tick drops</b><small>overdue, do it today</small></li><li><b>Worming</b><small>due Saturday</small></li><li><b>Tooth brushing</b><small>every day</small></li><li><b>Vet check-up</b><small>book by next week</small></li></ul>
    </div>
    <div class="sipo-choices td-m" style="--d:9s"><span class="sipo-choice">Flea drops done</span><span class="sipo-choice">Remind me Friday</span></div>
  </div>
  <div class="sipo-composer td-composer"><span class="td-ph">Tell SIPO about Milo…</span><i class="sipo-send">➜</i></div>
  <span class="td-finger" aria-hidden="true"></span>
</div>
<?php
}

function rrp_tour_demo_routines(): void
{
    $vm = rrp_tour_sample_vm();
    $groups = ['overdue' => 'Overdue', 'due_soon' => 'Due soon', 'ok' => 'On track'];
    ?>
<div class="td-screen td-routines">
  <div class="topbar care-top"><div class="center" style="text-align:left"><h2>Routines · Milo</h2></div></div>
  <div class="td-viewport">
    <div class="td-scroll pad stack care-page">
      <?php foreach ($groups as $state => $label): $rows = array_filter($vm['routines'], static fn($r) => $r['state'] === $state); if (!$rows) continue; ?>
        <section class="care-card" data-group="<?= rrp_h($state) ?>">
          <h3 class="label"><?= rrp_h($label) ?></h3>
          <ul class="care-routines" role="list">
            <?php foreach ($rows as $r): $ex = rrp_care_explain((string) $r['key'], (string) $r['state']); ?>
              <li class="is-<?= rrp_h($r['state']) ?>"<?= $r['key'] === 'dog_flea_tick' ? ' id="tdFlea"' : '' ?>>
                <details class="care-rt"<?= $r['key'] === 'dog_flea_tick' ? ' id="tdFleaD"' : '' ?>>
                  <summary>
                    <span class="dot" aria-hidden="true"></span>
                    <span class="care-rt-main"><b><?= rrp_h($r['title']) ?></b><small><?= $r['last_done'] ? 'Last ' . rrp_h($r['last_done']) . ' · ' : '' ?>Next <?= rrp_h((string) $r['next_due']) ?></small></span>
                    <span class="care-rt-end"><span class="chip-s" data-chip><?= rrp_h(['ok' => 'On track', 'due_soon' => 'Due soon', 'overdue' => 'Overdue'][$r['state']]) ?></span><svg class="care-rt-chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></span>
                  </summary>
                  <p class="care-rt-line"><span>What it is</span><?= rrp_h($ex['what']) ?></p>
                  <p class="care-rt-line is-todo"><span>What to do</span><?= rrp_h($ex['todo']) ?></p>
                  <?php if ($r['key'] === 'dog_flea_tick'): ?><p class="td-donerow"><span class="btn primary td-done">Done</span></p><?php endif; ?>
                </details>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    </div>
  </div>
  <span class="td-finger" aria-hidden="true"></span>
</div>
<?php
}

/** A stylised neighbourhood map shared by the Lost and Walk demos. */
function rrp_tour_map_svg(string $extra = ''): string
{
    return '<svg class="td-map" viewBox="0 0 300 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true">'
        . '<rect width="300" height="300" class="m-land"/>'
        . '<rect x="26" y="150" width="120" height="96" rx="10" class="m-park"/><circle cx="62" cy="186" r="9" class="m-tree"/><circle cx="96" cy="206" r="11" class="m-tree"/><circle cx="118" cy="176" r="8" class="m-tree"/>'
        . '<path d="M0 74h300M0 150h300M0 238h300M64 0v300M170 0v300M246 0v300" class="m-road"/>'
        . '<path d="M0 112c60 8 90-30 150-12s90 6 150-10M118 0c10 60-8 100 18 160s-6 100 10 140" class="m-road m-thin"/>'
        . '<rect x="190" y="84" width="40" height="28" class="m-block"/><rect x="190" y="164" width="40" height="52" class="m-block"/><rect x="82" y="86" width="64" height="40" class="m-block"/><rect x="262" y="164" width="30" height="52" class="m-block"/>'
        . $extra . '</svg>';
}

function rrp_tour_demo_lost(): void
{
    $owners = [[204, 126], [236, 190], [150, 210], [100, 120], [214, 70], [270, 120], [128, 262], [60, 246], [190, 258], [262, 252]];
    ?>
<div class="td-screen td-lost">
  <div class="td-alert"><b>Milo is lost</b><small>Last seen near the park</small></div>
  <div class="td-mapwrap">
    <?= rrp_tour_map_svg(
        '<g class="td-rings"><circle cx="150" cy="150" r="20" class="ring r1"/><circle cx="150" cy="150" r="20" class="ring r2"/><circle cx="150" cy="150" r="20" class="ring r3"/></g>'
        . '<g class="td-owners">' . implode('', array_map(static fn($p, $i) => '<g class="own" style="--d:' . (1.6 + $i * 0.35) . 's"><circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="7"/><path d="M' . ($p[0] - 3) . ' ' . ($p[1] - 0.5) . 'l2.4 2.4 4.2-4.6" fill="none"/></g>', $owners, array_keys($owners))) . '</g>'
        . '<g class="td-pin"><path d="M150 168s-14-14-14-26a14 14 0 0 1 28 0c0 12-14 26-14 26z"/><circle cx="150" cy="142" r="5"/></g>'
    ) ?>
    <p class="td-count"><b data-count="12" data-delay="1.6">0</b> nearby owners told</p>
  </div>
  <ul class="td-todo">
    <li style="--d:3.6s"><i></i>Look around the last place you saw Milo</li>
    <li style="--d:4.4s"><i></i>Tell neighbours and share the poster</li>
    <li style="--d:5.2s"><i></i>Keep your phone on for sightings</li>
    <li style="--d:6s"><i></i>The alert repeats later, and a search plan appears</li>
  </ul>
  <p class="td-found" style="--d:7.4s"><span class="btn primary">Milo is found</span></p>
</div>
<?php
}

function rrp_tour_demo_money(): void
{
    $rows = [['Vet visit', 'Vet', 92.00, '92'], ['Salmon kibble', 'Food', 64.00, '64'], ['Flea drops', 'Medicine', 18.40, '18'], ['Grooming', 'Grooming', 12.00, '12']];
    ?>
<div class="td-screen td-money">
  <div class="topbar care-top"><div class="center" style="text-align:left"><h2>Expenses · Milo</h2></div></div>
  <div class="pad stack care-page">
    <section class="care-card td-total"><p class="label">This month</p><p class="td-big"><span>CAD $</span><b data-count="186.4" data-dec="2" data-delay=".5" data-id="tot">0.00</b></p><p class="meta">Usual month: CAD $170</p></section>
    <section class="care-card">
      <h3 class="label">By category</h3>
      <?php foreach ($rows as $i => [$name, $cat, $amt, $w]): ?>
        <div class="td-bar" style="--d:<?= 0.8 + $i * 0.25 ?>s"><span><?= rrp_h($cat) ?></span><i><u style="--w:<?= (int) round($amt / 92 * 100) ?>%"></u></i><b class="data">$<?= number_format($amt, 2) ?></b></div>
      <?php endforeach; ?>
    </section>
    <div class="sipo-msg from-me td-m td-spent" style="--d:3.6s"><p>I spent 30 on food</p></div>
    <section class="care-card td-newrow" style="--d:4.8s"><p><b>Food</b><small>just now</small></p><b class="data">+ $30.00</b></section>
  </div>
</div>
<?php
}

function rrp_tour_demo_walk(): void
{
    $w = rrp_tour_walk_numbers();
    $route = 'M64 238 C64 200 70 172 96 160 S150 168 170 150 S196 100 170 84 S120 90 112 118 S150 140 176 132 S236 150 246 190 S214 232 170 238';
    ?>
<div class="td-screen td-walk">
  <div class="topbar care-top"><div class="center" style="text-align:left"><h2>Walk · Milo</h2></div></div>
  <div class="td-mapwrap">
    <?= rrp_tour_map_svg(
        '<path d="' . $route . '" class="td-path-bg"/><path id="tdRoute" d="' . $route . '" class="td-path" pathLength="100"/>'
        . '<g class="td-dog"><circle r="11"/><image href="' . rrp_h(rrp_asset('assets/img/pets/milo.svg')) . '" x="-10" y="-10" width="20" height="20"/><animateMotion dur="9s" begin="indefinite" fill="freeze" rotate="0" keyPoints="0;1" keyTimes="0;1" calcMode="linear"><mpath href="#tdRoute"/></animateMotion></g>'
        . '<circle cx="64" cy="238" r="6" class="td-start"/>'
    ) ?>
  </div>
  <div class="walk-stats td-stats" aria-live="off">
    <div><b class="data" data-time="<?= (int) $w['seconds'] ?>" data-delay="1" data-dur="9">0:00</b><small>time</small></div>
    <div><b class="data" data-count="<?= $w['dist'] ?>" data-dec="2" data-delay="1" data-dur="9">0.00</b><small><?= rrp_h($w['unit']) ?></small></div>
    <div><b class="data td-pace"><?= rrp_h($w['pace']) ?></b><small><?= rrp_h($w['pace_unit']) ?></small></div>
  </div>
  <p class="td-walkbtn"><span class="btn primary td-start-btn">Start walk</span><span class="btn outline td-finish-btn">Finish &amp; save</span></p>
  <p class="td-saved"><b>Saved</b> · <?= rrp_h((string) $w['dist']) ?> <?= rrp_h($w['unit']) ?> · +3 paw coins</p>
  <span class="td-finger" aria-hidden="true"></span>
</div>
<?php
}

function rrp_tour_demo_coins(): void
{
    ?>
<div class="td-screen td-coins">
  <div class="topbar care-top"><div class="center" style="text-align:left"><h2>Rewards</h2></div></div>
  <div class="pad stack care-page">
    <section class="care-card td-wallet">
      <span class="td-bigcoin"><?= rrp_tour_coin(54) ?></span>
      <div><p class="td-big"><b data-count="255" data-delay=".5" data-dur="5">0</b></p><p class="meta">paw coins, earned by caring</p></div>
      <i class="td-plus p1" style="--d:1s">+10 vaccine</i><i class="td-plus p2" style="--d:2.2s">+15 lab report</i><i class="td-plus p3" style="--d:3.4s">+3 walk</i>
    </section>
    <section class="care-card">
      <h3 class="label">This week</h3>
      <div class="td-week"><?php foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $i => $dd): ?><span style="--d:<?= 1 + $i * 0.45 ?>s"><i></i><?= $dd ?></span><?php endforeach; ?></div>
      <p class="td-perfect" style="--d:4.4s"><b>Perfect week</b> · 5% off your next Plus month</p>
    </section>
    <section class="care-card td-ref">
      <h3 class="label">Invite friends</h3>
      <div class="td-friends">
        <span style="--d:5.2s"><img src="<?= rrp_h(rrp_asset('assets/img/pets/milo.svg')) ?>" alt=""></span>
        <span style="--d:6s"><img src="<?= rrp_h(rrp_asset('assets/img/pets/luna.svg')) ?>" alt=""></span>
        <span style="--d:6.8s"><img src="<?= rrp_h(rrp_asset('assets/mascot/v2/happy-s.webp')) ?>" alt=""></span>
        <b class="td-gift" style="--d:7.6s">1 free month</b>
      </div>
      <p class="meta">Every 3 friends who subscribe give you one free month.</p>
    </section>
  </div>
</div>
<?php
}
