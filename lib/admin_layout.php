<?php
declare(strict_types=1);

/** Inline SVG icons for the admin (one set, 1.6 stroke, 20 px). */
function rrp_admin_icon(string $name): string
{
    static $p = [
        'home' => 'M3 11 12 4l9 7M5 10v9h5v-5h4v5h5v-9',
        'users' => 'M16 19v-1.5A3.5 3.5 0 0 0 12.5 14h-5A3.5 3.5 0 0 0 4 17.5V19M10 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M20 19v-1.5a3.5 3.5 0 0 0-2.5-3.4M15.5 5.2a3 3 0 0 1 0 5.6',
        'mail' => 'M4 6h16v12H4zM4 7l8 6 8-6',
        'map' => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2zM9 4v14M15 6v14',
        'ticket' => 'M4 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h16v-2a2 2 0 0 1 0-4v0a2 2 0 0 0 0-4V6H4zM14 6v12',
        'tag' => 'M3 12V4h8l10 10-8 8zM7.5 8.5h.01',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'brain' => 'M9 4a3 3 0 0 0-3 3 3 3 0 0 0-2 5 3 3 0 0 0 2 5 3 3 0 0 0 6 1V4a3 3 0 0 0-3 0M15 4a3 3 0 0 1 3 3 3 3 0 0 1 2 5 3 3 0 0 1-2 5 3 3 0 0 1-6 1',
        'graph' => 'M6 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4M18 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4M9 21a2 2 0 1 0 0-4 2 2 0 0 0 0 4M7.5 6l9 3M16.5 11.5 10 17M6.5 7 8.5 17',
        'flask' => 'M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2h12.4a1.5 1.5 0 0 0 1.3-2L14 9V3M7.5 14h9',
        'pulse' => 'M3 12h4l2-6 4 12 2-6h6',
        'check' => 'M5 12l4 4 10-10',
        'shield' => 'M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6zM9 12l2 2 4-4',
        'alert' => 'M12 3 2 20h20L12 3zM12 10v5M12 18h.01',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'logout' => 'M10 4H5v16h5M15 8l4 4-4 4M19 12H9',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'dots' => 'M5 12h.01M12 12h.01M19 12h.01',
        'external' => 'M14 4h6v6M20 4l-9 9M18 14v5H5V6h5',
    ];
    $d = $p[$name] ?? $p['dots'];
    return '<svg class="ad-ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function rrp_admin_nav(string $active = 'home'): void
{
    $groups = [
        'Overview' => [['home', 'index.php', 'Overview', 'home'], ['insights', 'insights.php', 'Insights', 'chart'], ['geo', 'geo.php', 'Map and cities', 'map']],
        'People' => [['accounts', 'accounts.php', 'Accounts', 'users'], ['tickets', 'tickets.php', 'Tickets', 'ticket'], ['coupons', 'coupons.php', 'Coupons', 'tag'], ['emails', 'emails.php', 'Emails', 'mail']],
        'SIPO brain' => [['brain', 'naki.php', 'NAKI brain', 'brain'], ['graph', 'graph.php', 'Brain graph', 'graph'], ['lab', 'lab.php', 'Test lab', 'flask'], ['sipo', 'sipo-check.php', 'SIPO check', 'check']],
        'System' => [['site', 'site.php', 'Site health', 'pulse'], ['errors', 'errors.php', 'Errors', 'alert'], ['logs', 'logs.php', 'Logs', 'list']],
    ];
    if (rrp_social_enabled()) {
        $groups['People'][] = ['moderation', 'moderation.php', 'Moderation', 'shield'];
    }
    echo '<nav class="ad-nav" id="adNav" aria-label="Admin">' . "\n";
    foreach ($groups as $title => $items) {
        echo '  <p class="ad-group">' . rrp_h($title) . '</p>' . "\n";
        foreach ($items as [$key, $href, $label, $icon]) {
            $cur = $key === $active;
            echo '  <a href="' . rrp_h($href) . '"' . ($cur ? ' class="is-active" aria-current="page"' : '') . '>' . rrp_admin_icon($icon) . '<span>' . rrp_h($label) . '</span></a>' . "\n";
        }
    }
    echo '  <p class="ad-group">Account</p>' . "\n";
    echo '  <a href="' . rrp_h(rrp_asset('index.php')) . '">' . rrp_admin_icon('external') . '<span>Open the site</span></a>' . "\n";
    echo '  <a href="logout.php">' . rrp_admin_icon('logout') . '<span>Log out</span></a>' . "\n";
    echo '</nav>';
}

function rrp_admin_shell_start(string $title, string $active = 'home'): void
{
    rrp_layout_head($title . ' — Admin', [
        'body_class' => 'admin-body',
        'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />'
            . '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/admin-dash.css', true)) . '" />'
            . '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/admin-ui.css', true)) . '" />'
            . (in_array($active, ['geo', 'insights'], true)
                ? '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />' : ''),
    ]);
    ?>
<a class="ad-skip" href="#adMain">Skip to content</a>
<div class="ad-shell">
  <header class="ad-top">
    <button type="button" class="ad-burger" aria-controls="adNav" aria-expanded="false" aria-label="Open menu"><?= rrp_admin_icon('menu') ?></button>
    <a class="ad-brand" href="index.php">
      <img src="<?= rrp_h(rrp_asset('assets/img/logos/sipaw-symbol-on-black.svg', true)) ?>" alt="" width="32" height="32" />
      <span>SIPAW Admin</span>
    </a>
  </header>
  <aside class="ad-side">
    <?php rrp_admin_nav($active); ?>
  </aside>
  <main class="ad-main" id="adMain">
    <?php
}

function rrp_admin_shell_end(array $opts = []): void
{
    echo '</main></div>';
    echo '<script>(function(){var b=document.querySelector(".ad-burger"),s=document.querySelector(".ad-side");if(!b||!s)return;'
        . 'b.addEventListener("click",function(){var o=s.classList.toggle("is-open");b.setAttribute("aria-expanded",o?"true":"false");});'
        . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&s.classList.contains("is-open")){s.classList.remove("is-open");b.setAttribute("aria-expanded","false");b.focus();}});})();</script>';
    rrp_layout_foot($opts);
}
