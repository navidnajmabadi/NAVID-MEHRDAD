<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/tour.php';

/**
 * One animated demo scene on sample data (Milo, a beagle), shown in an iframe on tour.php and on the landing page.
 * Public on purpose: it reads and writes nothing of the visitor's. ?d=health|vet|chat|routines|lost|money|walk|coins
 */
$key = (string) ($_GET['d'] ?? '');
if (!in_array($key, RRP_TOUR_DEMOS, true)) {
    http_response_code(404);
    exit('Unknown demo');
}
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: public, max-age=300');

rrp_layout_head('Demo — SIPAW', [
    'extra_head' => '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/care.css', true)) . '" />'
        . '<link rel="stylesheet" href="' . rrp_h(rrp_asset('assets/css/tour.css', true)) . '" />',
    'body_class' => 'page-care td-body',
]);
?>
<div class="td-stage" data-demo="<?= rrp_h($key) ?>">
<?php rrp_tour_demo($key); ?>
</div>
<script src="<?= rrp_h(rrp_asset('assets/js/tour-demo.js', true)) ?>"></script>
</body>
</html>
