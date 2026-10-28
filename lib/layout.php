<?php
declare(strict_types=1);

const RRP_UI_SKINS = ['studio' => 'Studio', 'friendly' => 'Friendly', 'clean' => 'Clean'];

/** Appearance chosen in Settings (per device, cookie). studio = the original dark look. */
function rrp_ui_skin(): string
{
    $s = (string) ($_COOKIE['sipaw_skin'] ?? 'studio');
    return isset(RRP_UI_SKINS[$s]) ? $s : 'studio';
}

function rrp_ui_set_skin(string $skin): void
{
    if (!isset(RRP_UI_SKINS[$skin])) {
        return;
    }
    setcookie('sipaw_skin', $skin, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax']);
    $_COOKIE['sipaw_skin'] = $skin;
}

function rrp_layout_head(string $title, array $opts = []): void
{
    $desc = $opts['description'] ?? 'siPaw by NAKILAB — AI pet care companion and community for pet owners.';
    $extra = $opts['extra_head'] ?? '';
    $canonical = $opts['canonical'] ?? (rrp_config_ready() ? rrp_app_url() : 'https://sipaw.nakilab.com/');
    $bodyClass = trim((string) ($opts['body_class'] ?? ''));
    if (!headers_sent()) {
        header('X-SIPAW-Page: ' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))); // lets menu.js verify clean URLs
    }
    $skin = rrp_ui_skin();
    $theme = $opts['theme'] ?? ($skin === 'studio' ? 'dark' : 'light');
    $skinFonts = ['friendly' => 'Nunito:wght@400;600;700;800', 'clean' => 'Figtree:wght@400;500;600;700'][$skin] ?? null;
    ?>
<!doctype html>
<html lang="en" data-theme="<?= rrp_h((string) $theme) ?>"<?= $skin !== 'studio' ? ' data-skin="' . rrp_h($skin) . '"' : '' ?>>
<head>
<meta charset="utf-8" />
<style>html{background:<?= ['friendly' => '#FFF8EF', 'clean' => '#F4F6FA'][$skin] ?? '#000000' ?>;color-scheme:<?= $skin === 'studio' ? 'dark' : 'light' ?>}@view-transition{navigation:auto}</style>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="description" content="<?= rrp_h($desc) ?>" />
<meta name="theme-color" content="<?= ['friendly' => '#FFF8EF', 'clean' => '#F4F6FA'][$skin] ?? '#000000' ?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="SIPAW" />
<link rel="canonical" href="<?= rrp_h($canonical) ?>" />
<link rel="manifest" href="<?= rrp_h(rrp_asset('manifest.webmanifest')) ?>" />
<link rel="icon" type="image/png" href="<?= rrp_h(rrp_asset('assets/icons/icon-192.png')) ?>" />
<link rel="apple-touch-icon" href="<?= rrp_h(rrp_asset('assets/icons/apple-touch-icon.png')) ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<?php if ($skin === 'studio'): ?><link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" media="print" onload="this.media='all'" /><?php endif; ?>
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/sipaw.css', true)) ?>" />
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/sipo-app.css', true)) ?>" />
<?php if ($skinFonts): ?><link href="https://fonts.googleapis.com/css2?family=<?= rrp_h($skinFonts) ?>&display=swap" rel="stylesheet" media="print" onload="this.media='all'" /><?php endif; ?>
<script type="speculationrules">{"prefetch":[{"where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":["/logout.php*","/dev-tools.php*","/account-delete.php*","/api/*","/cron/*","/admin/*"]}}]},"eagerness":"moderate"}]}</script>
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/skins.css', true)) ?>" />
<meta name="rrp-sw" content="<?= rrp_h(rrp_asset('sw.js')) ?>" />
<meta name="rrp-push-vapid" content="<?= rrp_h(rrp_asset('api/push-vapid.php')) ?>" />
<meta name="rrp-push-subscribe" content="<?= rrp_h(rrp_asset('api/push-subscribe.php')) ?>" />
<title><?= rrp_h($title) ?></title>
<meta property="og:title" content="<?= rrp_h($title) ?>" />
<meta property="og:site_name" content="SIPAW" />
<?= $extra ?>
</head>
<body class="<?= rrp_h($bodyClass) ?>">
<?php
    rrp_svg_sprite();
}

/**
 * Inline SVG icon + pet sprite from the exact siPaw prototype.
 */
function rrp_svg_sprite(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $path = dirname(__DIR__) . '/assets/img/sipaw-sprite.svg';
    if (!is_readable($path)) {
        return;
    }
    $svg = file_get_contents($path);
    if ($svg === false) {
        return;
    }
    // Keep hidden; symbols referenced via <use href="#…">
    echo $svg . "\n";
}

/**
 * @param array{nav?:string|false,unread?:int,lost_active?:bool,class?:string} $opts
 */
function rrp_app_start(array $opts = []): void
{
    $nav = $opts['nav'] ?? false;
    $class = trim('app ' . (string) ($opts['class'] ?? ''));
    $mainClass = 'app-main' . ($nav ? ' has-tab' : '');
    echo '<div class="' . rrp_h($class) . '">' . "\n";
    echo '<div class="' . rrp_h($mainClass) . '">' . "\n";
}

/**
 * @param array{nav?:string|false,unread?:int,lost_active?:bool,scripts?:array,push?:bool} $opts
 */
function rrp_app_end(array $opts = []): void
{
    echo "</div>\n"; // .app-main
    $nav = $opts['nav'] ?? false;
    if ($nav) {
        rrp_sipo_fab((string) $nav);
        rrp_side_menu((string) $nav, (int) ($opts['unread'] ?? 0), !empty($opts['lost_active']));
        rrp_install_sheet();
    }
    echo "</div>\n"; // .app
    rrp_layout_foot($opts);
}

/** Add to Home Screen ask (assets/js/install.js decides when/if it shows). */
function rrp_install_sheet(): void
{
    ?>
<div class="a2hs" id="a2hs" role="dialog" aria-labelledby="a2hsText" hidden>
  <img src="<?= rrp_h(rrp_asset('assets/icons/icon-192.png')) ?>" alt="" width="44" height="44" />
  <div>
    <p id="a2hsText">Add SIPAW to your Home Screen so SIPO can reach you.</p>
    <p class="a2hs-steps" hidden>Tap <svg class="a2hs-share" viewBox="0 0 24 24" aria-label="Share"><path d="M12 3v12M7 8l5-5 5 5M5 12v8h14v-8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg> Share, then <b>Add to Home Screen</b>.</p>
    <div class="a2hs-actions">
      <button type="button" class="btn primary" data-a2hs-add>Add</button>
      <button type="button" class="btn outline" data-a2hs-later>Not now</button>
    </div>
  </div>
</div>
<?php
}

/** Floating SIPO button on every app page except the chat itself: one tap to ask SIPO. */
/** Page → what "Ask SIPO" should explain (si-chat.php?ask=…). */
const RRP_SIPO_ASK_TOPICS = [
    'home' => 'Give me a quick summary of today for my pet: anything due, anything to watch.',
    'health' => 'Explain my pet’s Health page: the status, anything out of range, and what I should do next.',
    'daily' => 'Explain my pet’s Daily page: bathroom pattern, walks and anything unusual.',
    'routines' => 'Walk me through my pet’s routines: what is overdue or due soon, and what to do first.',
    'finance' => 'Explain my pet spending: this month vs usual, the biggest costs, and ways to plan.',
    'lost' => 'Explain how Lost mode works and what I should do right now.',
    'social' => 'What can I do on the Pack board, and what would be a nice post for my pet?',
];

function rrp_sipo_ask_topic_for_page(): ?string
{
    $map = ['dashboard.php' => 'home', 'care.php' => 'health', 'daily.php' => 'daily', 'routines.php' => 'routines',
        'finance.php' => 'finance', 'lost.php' => 'lost', 'forum.php' => 'social'];
    return $map[basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))] ?? null;
}

function rrp_sipo_fab(string $active): void
{
    if ($active === 'chat') {
        return;
    }
    $topic = rrp_sipo_ask_topic_for_page();
    $href = 'si-chat.php' . ($topic ? '?ask=' . $topic : '');
    $mascot = rrp_asset('assets/mascot/public/sipo-idle-ice-blue.webp');
    echo '<a class="sipo-fab" href="' . rrp_h($href) . '" aria-label="Ask SIPO about this page"><img src="' . rrp_h($mascot) . '" alt="" width="40" height="40" /><span>Ask SIPO</span></a>' . "\n";
}

/**
 * Left hamburger menu: SIPO first, then every area of the app; admin tools for super owners.
 */
function rrp_side_menu(string $active, int $unread, bool $lostActive): void
{
    $owner = rrp_current_owner();
    $isSuper = $owner && rrp_is_super_owner($owner);
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $items = [
        ['si-chat.php', 'SIPO', 'Ask or tell SIPO anything', null],
        ['care.php', 'Health', 'Dashboard, charts, labs, food', 'i-heart'],
        ['routines.php', 'Routines', 'What is scheduled and due', 'i-routine'],
        ['forum.php', 'Pack', 'Posts from pets near and far', 'i-social'],
        ['lost.php', $lostActive ? 'Lost mode is on' : 'Lost pet', $lostActive ? 'Open the search' : 'How it works, one tap to start', 'i-lost'],
        ['finance.php', 'Finance', 'What you spend on your pets', 'i-doc'],
        ['settings.php', 'Settings', 'Profile, password, account', 'i-gear'],
    ];
    $mascot = rrp_asset('assets/mascot/public/sipo-idle-ice-blue.webp');
    ?>
<button type="button" class="menu-btn" id="menuBtn" aria-label="Menu" aria-controls="sideMenu" aria-expanded="false">
  <span></span><span></span><span></span><?php if ($unread > 0): ?><i class="menu-dot" aria-hidden="true"></i><?php endif; ?>
</button>
<div class="side-backdrop" id="sideBackdrop" hidden></div>
<nav class="side-menu" id="sideMenu" aria-label="Menu" data-csrf="<?= rrp_h(rrp_csrf_token()) ?>" hidden>
  <div class="side-head"><?php rrp_logo_endorsed(); ?><button type="button" class="iconbtn" data-menu-close aria-label="Close menu">×</button></div>
  <ul role="list">
    <?php foreach ($items as [$href, $label, $sub, $icon]): $cur = $page === $href; ?>
      <li><a href="<?= rrp_h($href) ?>" class="<?= $href === 'si-chat.php' ? 'is-sipo ' : '' ?><?= $href === 'lost.php' ? 'is-lost ' : '' ?>"<?= $cur ? ' aria-current="page"' : '' ?>>
        <?php if ($icon === null): ?><img src="<?= rrp_h($mascot) ?>" alt="" width="36" height="36" /><?php else: ?><svg aria-hidden="true"><use href="#<?= rrp_h($icon) ?>"/></svg><?php endif; ?>
        <span><b><?= rrp_h($label) ?></b><small><?= rrp_h($sub) ?></small></span>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($owner): $sw = rrp_vm_pet_switcher($owner); ?>
    <div class="side-pets" id="sidePets">
      <p class="label">My pets · tap to switch</p>
      <?php $curId = (int) ($sw['current']['id'] ?? 0);
      usort($sw['pets'], static fn($a, $b) => ((int) $b['id'] === $curId) <=> ((int) $a['id'] === $curId)); // showing now on top
      foreach ($sw['pets'] as $p): $isCur = $curId === (int) $p['id']; ?>
        <button type="button" class="side-pet" data-menu-pet="<?= (int) $p['id'] ?>"<?= $isCur ? ' aria-current="true"' : '' ?>>
          <?php rrp_pet_avatar($p); ?><span><b><?= rrp_h($p['name']) ?></b><small><?= $isCur ? 'Showing now' : rrp_h($p['kind']) ?></small></span>
          <?php if ($isCur): ?><svg class="side-pet-check" aria-hidden="true"><use href="#i-check"/></svg><?php endif; ?>
        </button>
      <?php endforeach; ?>
      <?php if ($sw['can_add']): ?>
        <a class="side-pet is-add" href="si-chat.php?new=1"><span class="pet-avatar is-add" aria-hidden="true">+</span><span><b>Add a pet</b><small>SIPO sets it up with you</small></span></a>
      <?php elseif (!$sw['pets']): ?>
        <a class="side-pet is-add" href="si-chat.php?new=1"><span class="pet-avatar is-add" aria-hidden="true">+</span><span><b>Add your pet</b></span></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($isSuper): ?>
    <div class="side-admin">
      <p class="label">Admin</p>
      <a href="<?= rrp_h(rrp_asset('admin/')) ?>">Admin panel</a>
      <form method="post" action="dev-tools.php" data-confirm="Add sample pets, routines, labs, walks, spending and posts to YOUR account?">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="act" value="sample" />
        <button type="submit">🧪 Fill sample data</button></form>
      <form method="post" action="dev-tools.php" data-confirm="Clear SIPO memory and start as a new user? Your pets, chats and data on this account are removed.">
        <input type="hidden" name="csrf" value="<?= rrp_h(rrp_csrf_token()) ?>" /><input type="hidden" name="act" value="reset" />
        <button type="submit">🧹 Clear SIPO memory (start as new user)</button></form>
    </div>
  <?php endif; ?>
</nav>
<?php
}

/**
 * Pages no longer show a pet picker: the pet is chosen once, in the side menu under
 * Settings (My pets), and every page follows it. Kept as a no-op so old callers stay valid.
 */
function rrp_pet_switcher(array $switcher, ?array $showing = null): void
{
}

function rrp_pet_avatar(?array $pet): void
{
    if ($pet && !empty($pet['avatar'])) {
        echo '<img class="pet-avatar" src="' . rrp_h($pet['avatar']) . '" alt="" width="36" height="36" loading="lazy" />';
        return;
    }
    $initial = $pet['initial'] ?? '+';
    echo '<span class="pet-avatar" aria-hidden="true">' . rrp_h($initial) . '</span>';
}

function rrp_logo_endorsed(string $class = ''): void
{
    $cls = trim('logo-endorsed ' . $class);
    $dark = rrp_asset('assets/img/logos/sipaw-wordmark-endorsed-on-black.svg', true);
    $light = rrp_asset('assets/img/logos/sipaw-wordmark-endorsed-transparent-black.svg', true);
    echo '<img class="' . rrp_h($cls . ' on-dark') . '" src="' . rrp_h($dark) . '" alt="SI^PAW by NAKILAB" width="210" height="154" />';
    echo '<img class="' . rrp_h($cls . ' on-light') . '" src="' . rrp_h($light) . '" alt="SI^PAW by NAKILAB" width="210" height="154" />';
}

function rrp_logo_wordmark(string $class = ''): void
{
    $cls = trim('logo-word wordmark ' . $class);
    $dark = rrp_asset('assets/img/logos/sipaw-wordmark-transparent-white.svg', true);
    $light = rrp_asset('assets/img/logos/sipaw-wordmark-transparent-black.svg', true);
    echo '<img class="' . rrp_h($cls . ' on-dark') . '" src="' . rrp_h($dark) . '" alt="SI^PAW" width="92" height="54" />';
    echo '<img class="' . rrp_h($cls . ' on-light') . '" src="' . rrp_h($light) . '" alt="SI^PAW" width="92" height="54" />';
}

function rrp_logo_tile(string $class = ''): void
{
    $cls = trim('logo-tile ' . $class);
    $src = rrp_asset('assets/img/logos/sipaw-symbol-on-black.svg', true);
    echo '<img class="' . rrp_h($cls) . '" src="' . rrp_h($src) . '" alt="SI element tile" width="104" height="104" />';
}

function rrp_owner_nav_context(int $ownerId): array
{
    $unread = 0;
    $lostActive = false;
    try {
        $unread = rrp_notifications_unread_count($ownerId);
        $open = rrp_lost_for_owner($ownerId);
        foreach ($open as $la) {
            if (($la['status'] ?? '') === 'open') {
                $lostActive = true;
                break;
            }
        }
    } catch (Throwable $e) {
        // layout should not crash pages if DB is mid-migrate
    }
    return ['unread' => $unread, 'lost_active' => $lostActive];
}

function rrp_layout_foot(array $opts = []): void
{
    $scripts = $opts['scripts'] ?? [];
    $includePush = $opts['push'] ?? true;
    ?>
<?php if ($includePush): ?>
<script src="<?= rrp_h(rrp_asset('assets/js/push.js', true)) ?>" defer></script>
<?php endif; ?>
<script src="<?= rrp_h(rrp_asset('assets/js/app.js', true)) ?>" defer></script>
<script src="<?= rrp_h(rrp_asset('assets/js/pet-switcher.js', true)) ?>" defer></script>
<script src="<?= rrp_h(rrp_asset('assets/js/install.js', true)) ?>" defer></script>
<script src="<?= rrp_h(rrp_asset('assets/js/menu.js', true)) ?>" defer></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= rrp_h(str_starts_with($src, 'http') || str_starts_with($src, '//') ? $src : rrp_asset($src, true)) ?>"<?= str_contains($src, 'cdn') ? ' crossorigin="anonymous"' : '' ?>></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

function rrp_flash_get(): ?array
{
    rrp_start_session();
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function rrp_flash_set(string $type, string $message): void
{
    rrp_start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function rrp_render_flash(): void
{
    $flash = rrp_flash_get();
    if (!$flash) {
        return;
    }
    $cls = $flash['type'] === 'error' ? 'flash flash-error' : 'flash flash-ok';
    echo '<div class="' . $cls . '" role="status">' . rrp_h($flash['message']) . '</div>';
}
