<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

$admin = rrp_require_admin();
$cities = rrp_admin_geo_list(100);
$points = rrp_admin_geo_points(500);

rrp_admin_shell_start('Map / cities', 'geo');
?>
  <h1>Cities &amp; map</h1>
  <p class="lede">Best-effort from address geocode cache (Nominatim). Owners without lat/lng are omitted from the map.</p>

  <div id="adminMap" class="admin-map"></div>

  <section class="panel" aria-labelledby="cityHeading">
    <h2 id="cityHeading">City / country counts</h2>
    <?php if (!$cities): ?>
      <p class="empty">No geo data yet. Owners get lat/lng when their address is geocoded.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>City</th><th>Country</th><th>Owners</th></tr></thead>
        <tbody>
          <?php foreach ($cities as $c): ?>
            <tr>
              <td><?= rrp_h((string) $c['city']) ?></td>
              <td><?= rrp_h((string) $c['country']) ?></td>
              <td><?= (int) $c['owners'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<script>
window.RRP_ADMIN_GEO = <?= json_encode($points, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
<script src="../assets/js/admin-geo.js" defer></script>
<?php rrp_admin_shell_end(); ?>
