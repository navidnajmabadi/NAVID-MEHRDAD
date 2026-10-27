<?php
declare(strict_types=1);

/** Lost Mode search plan view: owner sees the full plan, helpers see "how to help". */
function rrp_view_lost_playbook(array $pb, bool $forHelper): void
{
    ?>
<section class="care-card lost-plan">
  <h3 class="label"><?= $forHelper ? 'How you can help' : 'Search plan' ?></h3>
  <p class="sub"><?= rrp_h($pb['intro']) ?></p>
  <?php foreach ($pb['weather'] as $w): ?><p class="daily-window">🌦 <?= rrp_h($w) ?></p><?php endforeach; ?>
  <?php if ($forHelper): ?>
    <ol class="lost-steps"><?php foreach ($pb['helper'] as $h): ?><li><?= rrp_h($h) ?></li><?php endforeach; ?></ol>
  <?php else: ?>
    <?php foreach ($pb['steps'] as $i => $s): ?>
      <details class="lost-step" <?= $i < 2 ? 'open' : '' ?>>
        <summary><span class="chip-s"><?= rrp_h($s['when']) ?></span></summary>
        <ol class="lost-steps"><?php foreach ($s['items'] as $it): ?><li><?= rrp_h($it) ?></li><?php endforeach; ?></ol>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($pb['spots']): ?>
    <p class="label" style="margin-top:14px">Places <?= $forHelper ? 'this dog' : 'your dog' ?> walks most</p>
    <ul class="lost-spots" role="list">
      <?php foreach ($pb['spots'] as $sp): ?>
        <li><?= rrp_h($sp['label'] ?: 'Walk spot') ?><?= $sp['km_from_home'] !== null && !$forHelper ? ' · ' . rrp_h((string) $sp['km_from_home']) . ' km from home' : '' ?> <span class="meta">· <?= (int) $sp['walks'] ?> <?= (int) $sp['walks'] === 1 ? 'walk' : 'walks' ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <details class="care-sources"><summary class="meta">Why these steps</summary>
    <ul><?php foreach ($pb['sources'] as [$label, $url]): ?><li><a class="link" href="<?= rrp_h($url) ?>" target="_blank" rel="noopener"><?= rrp_h($label) ?></a></li><?php endforeach; ?></ul>
  </details>
</section>
<?php
}
