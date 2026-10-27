<?php
declare(strict_types=1);

function rrp_layout_head(string $title, array $opts = []): void
{
    $desc = $opts['description'] ?? 'siPaw by NAKILAB — AI pet care companion and community for pet owners.';
    $extra = $opts['extra_head'] ?? '';
    $canonical = $opts['canonical'] ?? (rrp_config_ready() ? rrp_app_url() : 'https://sipaw.nakilab.com/');
    $bodyClass = trim((string) ($opts['body_class'] ?? ''));
    $theme = $opts['theme'] ?? 'dark';
    ?>
<!doctype html>
<html lang="en" data-theme="<?= rrp_h((string) $theme) ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="description" content="<?= rrp_h($desc) ?>" />
<meta name="theme-color" content="#000000" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="SIPAW" />
<link rel="canonical" href="<?= rrp_h($canonical) ?>" />
<link rel="manifest" href="<?= rrp_h(rrp_asset('manifest.webmanifest')) ?>" />
<link rel="icon" type="image/png" href="<?= rrp_h(rrp_asset('assets/icons/icon-192.png')) ?>" />
<link rel="apple-touch-icon" href="<?= rrp_h(rrp_asset('assets/icons/apple-touch-icon.png')) ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/sipaw.css', true)) ?>" />
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/sipo-app.css', true)) ?>" />
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
        rrp_tabbar((string) $nav, (int) ($opts['unread'] ?? 0), !empty($opts['lost_active']));
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

function rrp_tabbar(string $active, int $unread = 0, bool $lostActive = false): void
{
    $tabs = [
        'home' => ['href' => 'dashboard.php', 'label' => 'Home', 'icon' => 'i-home'],
        'health' => ['href' => 'care.php', 'label' => 'Health', 'icon' => 'i-heart'],
        'lost' => ['href' => 'lost.php', 'label' => 'Lost', 'icon' => 'i-lost'],
        'alerts' => ['href' => 'alerts.php', 'label' => 'Alerts', 'icon' => 'i-bell'],
        'chat' => ['href' => 'si-chat.php', 'label' => 'SIPO', 'icon' => 'i-chat'],
    ];
    echo '<nav class="tabbar" id="tabbar" aria-label="Main">' . "\n";
    foreach ($tabs as $key => $tab) {
        $current = $active === $key ? ' aria-current="page"' : '';
        $lostClass = ($key === 'lost' && $lostActive) ? ' class="is-lost"' : '';
        echo '<a href="' . rrp_h($tab['href']) . '"' . $current . $lostClass . '>';
        echo '<svg aria-hidden="true"><use href="#' . rrp_h($tab['icon']) . '"/></svg>';
        echo rrp_h($tab['label']);
        if ($key === 'alerts' && $unread > 0) {
            echo '<span class="badge">' . rrp_h((string) min(99, $unread)) . '</span>';
        }
        echo '</a>' . "\n";
    }
    echo "</nav>\n";
}

/**
 * Instagram-style pet switcher: avatar + name; tap opens a sheet with every pet
 * and "Add a pet". Switching changes chat, dashboards and forms.
 * @param array{pets:list<array>, current:?array, can_add:bool} $switcher from rrp_vm_pet_switcher()
 */
function rrp_pet_switcher(array $switcher, ?array $showing = null): void
{
    $cur = $showing ?? $switcher['current'];
    ?>
<button type="button" class="petswitch" data-petswitch data-csrf="<?= rrp_h(rrp_csrf_token()) ?>" aria-haspopup="dialog" aria-controls="petsheet">
  <?php rrp_pet_avatar($cur); ?>
  <span class="petswitch-name"><?= rrp_h($cur['name'] ?? 'New pet') ?></span>
  <svg class="petswitch-chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
</button>
<dialog class="petsheet" id="petsheet" aria-label="Switch pet">
  <div class="petsheet-head"><span class="label">Your pets</span><button type="button" class="iconbtn" data-close aria-label="Close">×</button></div>
  <ul class="petsheet-list" role="list">
    <?php foreach ($switcher['pets'] as $p): ?>
      <li><button type="button" class="petsheet-row" data-pet-id="<?= (int) $p['id'] ?>" <?= ($cur && (int) $cur['id'] === (int) $p['id']) ? 'aria-current="true"' : '' ?>>
        <?php rrp_pet_avatar($p); ?>
        <span><b><?= rrp_h($p['name']) ?></b><small><?= rrp_h($p['kind']) ?></small></span>
        <svg class="petsheet-check" aria-hidden="true"><use href="#i-check"/></svg>
      </button></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($switcher['can_add']): ?>
    <a class="petsheet-row petsheet-add" href="si-chat.php?new=1"><span class="pet-avatar is-add" aria-hidden="true">+</span><span><b>Add a pet</b><small>SIPO will set it up with you</small></span></a>
  <?php else: ?>
    <p class="sub petsheet-note">Free includes 1 pet for now.</p>
  <?php endif; ?>
</dialog>
<?php
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
    $dark = rrp_asset('assets/img/logos/sipaw-wordmark-endorsed-on-black.svg');
    $light = rrp_asset('assets/img/logos/sipaw-wordmark-endorsed-on-white.svg');
    echo '<img class="' . rrp_h($cls . ' on-dark') . '" src="' . rrp_h($dark) . '" alt="SI^PAW by NAKILAB" width="210" height="154" />';
    echo '<img class="' . rrp_h($cls . ' on-light') . '" src="' . rrp_h($light) . '" alt="SI^PAW by NAKILAB" width="210" height="154" />';
}

function rrp_logo_wordmark(string $class = ''): void
{
    $cls = trim('logo-word wordmark ' . $class);
    $dark = rrp_asset('assets/img/logos/sipaw-wordmark-transparent-white.svg');
    $light = rrp_asset('assets/img/logos/sipaw-wordmark-transparent-black.svg');
    echo '<img class="' . rrp_h($cls . ' on-dark') . '" src="' . rrp_h($dark) . '" alt="SI^PAW" width="92" height="54" />';
    echo '<img class="' . rrp_h($cls . ' on-light') . '" src="' . rrp_h($light) . '" alt="SI^PAW" width="92" height="54" />';
}

function rrp_logo_tile(string $class = ''): void
{
    $cls = trim('logo-tile ' . $class);
    $src = rrp_asset('assets/img/logos/sipaw-symbol-on-black.svg');
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
<?php foreach ($scripts as $src): ?>
<script src="<?= rrp_h(str_starts_with($src, 'http') || str_starts_with($src, '//') ? $src : rrp_asset($src)) ?>"<?= str_contains($src, 'cdn') ? ' crossorigin="anonymous"' : '' ?>></script>
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
