<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/admin_layout.php';

// Price, currency, paw-coin value, discount limit and tax for Plus. Everything the Plus page charges comes from here.
$admin = rrp_require_admin();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rrp_verify_csrf($_POST['csrf'] ?? null)) {
    $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
    $cur = strtoupper(trim((string) ($_POST['currency'] ?? 'CAD')));
    $coin = (int) ($_POST['coin_value_cents'] ?? 1);
    $max = (int) ($_POST['max_total_pct'] ?? 50);
    $tax = (float) str_replace(',', '.', (string) ($_POST['tax_pct'] ?? '0'));
    $taxLabel = trim((string) ($_POST['tax_label'] ?? 'HST'));
    $trialDays = (int) ($_POST['trial_days'] ?? 7);
    $countries = strtoupper(preg_replace('/[^A-Za-z,*]/', '', (string) ($_POST['tax_countries'] ?? '')) ?? '');
    if ($price < 1 || $price > 500) {
        $error = 'The monthly price must be between 1 and 500.';
    } elseif (!preg_match('/^[A-Z]{3}$/', $cur)) {
        $error = 'Currency must be a 3-letter code such as CAD. It must match your Square account currency.';
    } elseif ($coin < 0 || $coin > 100) {
        $error = 'A paw coin can be worth 0 to 100 cents.';
    } elseif ($max < 0 || $max > 90) {
        $error = 'The maximum discount must be between 0 and 90 percent.';
    } elseif ($trialDays < 0 || $trialDays > 365) {
        $error = 'The trial can be 0 to 365 days.';
    } elseif ($tax < 0 || $tax > 40) {
        $error = 'Tax must be between 0 and 40 percent.';
    } else {
        foreach (['biz_name' => 60, 'biz_address' => 200, 'biz_country' => 40, 'biz_tax_no' => 40, 'biz_email' => 100] as $k => $max) {
            rrp_setting_set($k, mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max));
        }
        rrp_setting_set('trial_days', (string) $trialDays);
        rrp_setting_set('trial_enforced', !empty($_POST['trial_enforced']) ? '1' : '0');
        rrp_setting_set('price_monthly_cents', (string) (int) round($price * 100));
        rrp_setting_set('currency', $cur);
        rrp_setting_set('coin_value_cents', (string) $coin);
        rrp_setting_set('max_total_pct', (string) $max);
        rrp_setting_set('tax_pct', rtrim(rtrim(number_format($tax, 2, '.', ''), '0'), '.') ?: '0');
        rrp_setting_set('tax_label', mb_substr($taxLabel, 0, 20) ?: 'Tax');
        rrp_setting_set('tax_countries', $countries !== '' ? $countries : '*');
        rrp_admin_log((int) $admin['id'], 'pricing_update', 'app_settings', 0, "price=$price $cur coin={$coin}c max={$max}% tax={$tax}%");
        rrp_flash_set('ok', 'Pricing saved. It applies to the next payment straight away.');
        rrp_redirect('pricing.php');
    }
}

$prices = rrp_billing_prices();
$pol = rrp_billing_policy();
$tax = rrp_billing_tax_rule();
$sq = rrp_square_cfg();
$cfgFile = rrp_config_file();
$stat = ['plus' => 0, 'auto' => 0, 'paid30' => 0, 'rev30' => 0];
try {
    $db = rrp_db();
    $stat['plus'] = (int) $db->query("SELECT COUNT(*) FROM owners WHERE plan = 'plus' AND plan_expires_at > UTC_TIMESTAMP()")->fetchColumn();
    $stat['auto'] = (int) $db->query("SELECT COUNT(*) FROM owners WHERE auto_renew = 1 AND square_card_id IS NOT NULL")->fetchColumn();
    $r = $db->query("SELECT COUNT(*) n, COALESCE(SUM(amount_cents),0) s FROM billing_orders WHERE status = 'paid' AND provider = 'square' AND paid_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetch();
    $stat['paid30'] = (int) $r['n'];
    $stat['rev30'] = (int) $r['s'];
} catch (Throwable $e) {
}
$cur = $prices['currency'];
$coinExample = 255;
$exDisc = min($coinExample * $pol['coin_value_cents'], (int) floor($prices['monthly'] * $pol['max_total_pct'] / 100));
$check = [
    'Application ID' => $sq['application_id'] !== '', 'Access token' => $sq['access_token'] !== '', 'Location ID' => $sq['location_id'] !== '',
];
$csrf = rrp_csrf_token();

rrp_admin_shell_start('Pricing', 'pricing');
?>
  <h1>Pricing</h1>
  <p class="lede">The Plus price, what a paw coin is worth, the discount limit and the tax. Customers see changes on their next payment. Coupon codes are on the <a class="link" href="coupons.php">Coupons</a> page.</p>
  <?php rrp_render_flash(); ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= rrp_h($error) ?></div><?php endif; ?>

  <div class="kpis">
    <div class="kpi"><b><?= $stat['plus'] ?></b><span>On Plus now</span></div>
    <div class="kpi"><b><?= $stat['auto'] ?></b><span>Auto-renew on</span></div>
    <div class="kpi"><b><?= $stat['paid30'] ?></b><span>Payments, 30 days</span></div>
    <div class="kpi"><b><?= rrp_h(rrp_money($stat['rev30'], $cur)) ?></b><span>Taken, 30 days (with tax)</span></div>
  </div>

  <form method="post" class="dash-card" style="margin-top:14px;max-width:640px">
    <input type="hidden" name="csrf" value="<?= rrp_h($csrf) ?>" />
    <h2 style="margin:0 0 4px">Free trial</h2>
    <div class="field"><label for="td">Trial length for new accounts (days)</label>
      <div class="in"><input id="td" name="trial_days" inputmode="numeric" required value="<?= (int) rrp_trial_days_default() ?>" /></div>
      <p class="meta">After the trial, owners without Plus can only read their Health page and open Billing. You can change the trial of any single account in <a class="link" href="accounts.php">Accounts</a>. Admin accounts never have a trial or a subscription.</p></div>
    <label class="pp-check" style="display:flex;gap:10px;align-items:flex-start;margin:6px 0 18px"><input type="checkbox" name="trial_enforced" value="1" <?= rrp_access_enforced() ? 'checked' : '' ?> style="width:22px;height:22px;margin-top:2px" />
      <span><b>Trial rules on</b><br /><small class="meta">Untick to open everything for everyone again (a safety switch).</small></span></label>

    <h2 style="margin:0 0 4px">Price</h2>
    <div class="field"><label for="pr">Monthly price (before tax)</label>
      <div class="in"><input id="pr" name="price" inputmode="decimal" required value="<?= rrp_h(number_format($prices['monthly'] / 100, 2, '.', '')) ?>" /></div></div>
    <div class="field"><label for="cu">Currency</label>
      <div class="in"><input id="cu" name="currency" maxlength="3" required value="<?= rrp_h($cur) ?>" /></div>
      <p class="meta">Must be the same currency as your Square account.</p></div>
    <div class="field"><label for="cv">One paw coin is worth (cents)</label>
      <div class="in"><input id="cv" name="coin_value_cents" inputmode="numeric" required value="<?= (int) $pol['coin_value_cents'] ?>" /></div>
      <p class="meta">1 = 100 coins are worth 1.00. 0 turns coin discounts off.</p></div>
    <div class="field"><label for="mx">Most the coins and care weeks can take off one payment (%)</label>
      <div class="in"><input id="mx" name="max_total_pct" inputmode="numeric" required value="<?= (int) $pol['max_total_pct'] ?>" /></div></div>
    <div class="field"><label for="tp">Tax added to the price (%)</label>
      <div class="in"><input id="tp" name="tax_pct" inputmode="decimal" required value="<?= rrp_h((string) (+$tax['pct'])) ?>" /></div>
      <p class="meta">0 = no tax (you are under the CAD 30,000 small-supplier limit). If you register later, type the rate here, for example 13, and add the HST number below.</p></div>
    <div class="field"><label for="tl">Tax name on the receipt</label>
      <div class="in"><input id="tl" name="tax_label" maxlength="20" value="<?= rrp_h($tax['label']) ?>" /></div></div>
    <div class="field"><label for="tc">Owners in these countries pay the tax</label>
      <div class="in"><input id="tc" name="tax_countries" value="<?= rrp_h(implode(',', $tax['countries'])) ?>" /></div>
      <p class="meta">Two-letter codes separated by commas, for example <b>CA</b> or <b>CA,US</b>. Use <b>*</b> for everyone (the default). With a list, owners without a country, or from another country, are not taxed. Ask your accountant which customers owe tax.</p></div>
    <h2 style="margin:22px 0 4px">Invoice details (printed on every invoice)</h2>
    <div class="field"><label for="bn">Business name</label>
      <div class="in"><input id="bn" name="biz_name" maxlength="60" value="<?= rrp_h((string) rrp_setting('biz_name', 'NAKILAB')) ?>" /></div></div>
    <div class="field"><label for="ba">Address</label>
      <div class="in"><input id="ba" name="biz_address" maxlength="200" placeholder="Street, City, Province, Postal code, Country" value="<?= rrp_h((string) rrp_setting('biz_address', '')) ?>" /></div>
      <p class="meta">Separate the lines with commas.</p></div>
    <div class="field"><label for="bc">Business country</label>
      <div class="in"><input id="bc" name="biz_country" maxlength="40" value="<?= rrp_h((string) rrp_setting('biz_country', 'Canada')) ?>" /></div></div>
    <div class="field"><label for="bt">Tax registration number (GST/HST), if you have one</label>
      <div class="in"><input id="bt" name="biz_tax_no" maxlength="40" placeholder="123456789 RT0001" value="<?= rrp_h((string) rrp_setting('biz_tax_no', '')) ?>" /></div>
      <p class="meta">Leave empty while you are under the small-supplier limit and charge no tax.</p></div>
    <div class="field"><label for="be">Billing email</label>
      <div class="in"><input id="be" name="biz_email" type="email" maxlength="100" value="<?= rrp_h((string) rrp_setting('biz_email', '')) ?>" /></div></div>
    <button class="ad-btn is-primary" type="submit">Save pricing</button>
  </form>

  <section class="dash-card" style="margin-top:14px;max-width:640px">
    <h2>What this means today</h2>
    <p>Price <b><?= rrp_h(rrp_money($prices['monthly'], $cur)) ?></b> before tax.
      With tax at <?= rrp_h((string) (+$tax['pct'])) ?>% (<?= rrp_h(implode(', ', $tax['countries'])) ?>): <b><?= rrp_h(rrp_money((int) round($prices['monthly'] * (1 + $tax['pct'] / 100)), $cur)) ?></b> with no discount.</p>
    <p><?= $coinExample ?> coins = <b><?= rrp_h(rrp_money($exDisc, $cur)) ?></b> off
      (<?= $prices['monthly'] > 0 ? rrp_h((string) round($exDisc * 100 / $prices['monthly'], 1)) : '0' ?>% of the price)<?= $exDisc < $coinExample * $pol['coin_value_cents'] ? ', held back by the ' . (int) $pol['max_total_pct'] . '% limit' : '' ?>.
      A full price month would take <?= $pol['coin_value_cents'] > 0 ? number_format((int) ceil($prices['monthly'] / $pol['coin_value_cents'])) : '–' ?> coins, but the limit stops it at <?= (int) $pol['max_total_pct'] ?>% off.</p>
  </section>

  <section class="dash-card" style="margin-top:14px;max-width:640px">
    <h2>Square connection</h2>
    <ul style="list-style:none;margin:0;padding:0">
      <?php foreach ($check as $label => $ok): ?><li><?= $ok ? '✅' : '❌' ?> <?= rrp_h($label) ?> <span class="meta"><?= $ok ? 'set' : 'missing in the file below' ?></span></li><?php endforeach; ?>
    </ul>
    <p class="meta"><?= rrp_square_cards_ready() ? 'Card payments are on.' : 'Card payments stay off ("Payments open soon") until all three are set.' ?> The keys go only in the server's config file, never here. Steps: <code>docs/SQUARE.md</code>.</p>
    <p class="meta"><b>The app is reading this file:</b><br /><code style="word-break:break-all"><?= rrp_h($cfgFile ?? 'none found') ?></code><br />Add the <code>'square' =&gt; [ ... ]</code> block inside this file, above the last line <code>];</code>. Editing any other copy changes nothing.</p>
  </section>
<?php rrp_admin_shell_end(); ?>
