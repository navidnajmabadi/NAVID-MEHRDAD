<?php
declare(strict_types=1);
/**
 * Dev preview: Sipo mascot — 6 states × 4 themes.
 * Open: /sipo-preview.php (local: php -S localhost:8000 then visit that path).
 */
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

$states = ['idle', 'thinking', 'writing', 'listening', 'happy', 'alert'];
$themes = [
    'ice-blue' => 'Ice Blue (default)',
    'blue-body' => 'Blue Body',
    'mono' => 'Mono',
    'dark' => 'Dark',
];
$base = rtrim(rrp_asset('assets/mascot/public'), '/');

rrp_layout_head('Sipo preview — SIPAW', [
    'description' => 'Sipo mascot state × theme grid for SIPaw.',
    'body_class' => 'page-sipo-preview',
]);
rrp_app_start(['nav' => false]);
?>
<div class="topbar">
  <a class="iconbtn" href="index.php" aria-label="Home"><svg aria-hidden="true"><use href="#i-back"/></svg></a>
  <div class="center"><h2>Sipo preview</h2></div>
  <span style="width:40px"></span>
</div>

<div class="pad stack" style="gap:16px;padding-top:12px">
  <p class="sub" style="margin:0;font-size:13px">
    Sipo (SI = Super Intelligence). Default theme for the app is <b>Ice Blue</b> (<span class="data">#2E6AFF</span>).
    Animated webp/gif for ice-blue &amp; blue-body; mono/dark always static themable SVG.
    Respects <span class="data">prefers-reduced-motion</span>.
  </p>

  <label class="row" style="gap:10px;align-items:center;font-size:13px">
    <input type="checkbox" id="forceStatic" />
    Force static SVG (ignore baked animations)
  </label>

  <div class="sipo-preview-grid" id="sipoGrid">
    <?php foreach ($themes as $themeKey => $themeLabel): ?>
      <section class="sipo-preview-theme">
        <h3 class="data" style="margin:0 0 10px;font-size:12px;letter-spacing:.04em;text-transform:uppercase"><?= rrp_h($themeLabel) ?></h3>
        <div class="sipo-preview-row">
          <?php foreach ($states as $state): ?>
            <figure class="sipo-preview-cell">
              <sipo-mascot
                state="<?= rrp_h($state) ?>"
                theme="<?= rrp_h($themeKey) ?>"
                size="96"
                animated="true"
                label="Sipo <?= rrp_h($state) ?> · <?= rrp_h($themeKey) ?>"
              ></sipo-mascot>
              <figcaption class="data"><?= rrp_h($state) ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="gap"></div>
</div>

<style>
.page-sipo-preview .sipo-preview-grid{display:flex;flex-direction:column;gap:28px}
.page-sipo-preview .sipo-preview-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
@media (min-width:720px){
  .page-sipo-preview .sipo-preview-row{grid-template-columns:repeat(6,minmax(0,1fr))}
}
.page-sipo-preview .sipo-preview-cell{margin:0;text-align:center;display:flex;flex-direction:column;align-items:center;gap:8px}
.page-sipo-preview .sipo-preview-cell figcaption{font-size:11px;color:var(--sub)}
.page-sipo-preview .sipo-preview-theme{padding-bottom:8px;border-bottom:1px solid var(--line)}
</style>
<script>
window.SIPO_ASSET_BASE = <?= json_encode($base) ?>;
</script>
<script src="<?= rrp_h(rrp_asset('assets/js/sipo.js', true)) ?>" defer></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var box = document.getElementById("forceStatic");
  if (!box) return;
  box.addEventListener("change", function () {
    document.querySelectorAll("sipo-mascot").forEach(function (el) {
      el.setAttribute("animated", box.checked ? "false" : "true");
    });
  });
});
</script>
<?php
rrp_app_end(['nav' => false, 'push' => false]);
