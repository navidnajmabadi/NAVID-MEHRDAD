<?php
declare(strict_types=1);

function rrp_admin_nav(string $active = 'home'): void
{
    $items = [
        'home' => ['index.php', 'Overview'],
        'accounts' => ['accounts.php', 'Accounts'],
        'geo' => ['geo.php', 'Map / cities'],
        'tickets' => ['tickets.php', 'Tickets'],
        'coupons' => ['coupons.php', 'Coupons'],
        'logs' => ['logs.php', 'Logs'],
    ];
    echo '<nav class="admin-nav" aria-label="Admin">';
    foreach ($items as $key => [$href, $label]) {
        $cls = $key === $active ? ' class="is-active"' : '';
        echo '<a href="' . rrp_h($href) . '"' . $cls . '>' . rrp_h($label) . '</a>';
    }
    echo '<a href="logout.php">Log out</a>';
    echo '</nav>';
}

function rrp_admin_shell_start(string $title, string $active = 'home'): void
{
    rrp_layout_head($title . ' — Admin', [
        'body_class' => 'admin-body',
        'extra_head' => $active === 'geo'
            ? '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />'
            : '',
    ]);
    ?>
<div class="shell shell-wide">
  <div class="app-top">
    <a class="app-brand" href="index.php">
      <img src="../assets/logo.jpg" alt="" width="40" height="40" />
      <span>RRP Admin</span>
    </a>
    <a href="../index.php">Site</a>
  </div>
    <?php
    rrp_admin_nav($active);
    echo '<div class="admin-main">';
}

function rrp_admin_shell_end(array $opts = []): void
{
    echo '</div></div>';
    rrp_layout_foot($opts);
}
