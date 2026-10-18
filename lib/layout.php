<?php
declare(strict_types=1);

function rrp_layout_head(string $title, array $opts = []): void
{
    $desc = $opts['description'] ?? 'Ring Ring Paw — Scan the tag. Call the owner. Pet goes home.';
    $extra = $opts['extra_head'] ?? '';
    $canonical = $opts['canonical'] ?? (rrp_config_ready() ? rrp_app_url() : 'https://ringringpaw.nakilab.com/');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="description" content="<?= rrp_h($desc) ?>" />
<meta name="theme-color" content="#0D9488" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="RingRingPaw" />
<link rel="canonical" href="<?= rrp_h($canonical) ?>" />
<link rel="manifest" href="<?= rrp_h(rrp_asset('manifest.webmanifest')) ?>" />
<link rel="icon" type="image/jpeg" href="<?= rrp_h(rrp_asset('assets/logo.jpg')) ?>" />
<link rel="apple-touch-icon" href="<?= rrp_h(rrp_asset('assets/icons/icon-192.png')) ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700&family=Varela+Round&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?= rrp_h(rrp_asset('assets/css/app.css', true)) ?>" />
<meta name="rrp-sw" content="<?= rrp_h(rrp_asset('sw.js')) ?>" />
<meta name="rrp-push-vapid" content="<?= rrp_h(rrp_asset('api/push-vapid.php')) ?>" />
<meta name="rrp-push-subscribe" content="<?= rrp_h(rrp_asset('api/push-subscribe.php')) ?>" />
<title><?= rrp_h($title) ?></title>
<?= $extra ?>
</head>
<body class="<?= rrp_h($opts['body_class'] ?? '') ?>">
<?php
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
