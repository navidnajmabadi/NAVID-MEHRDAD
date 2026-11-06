# Square: card payments and monthly auto-renew (live)

SIPAW charges cards through Square. Card numbers are typed into Square's own form on the Plus page, so they
never reach SIPAW's server. Everything is **live** (no sandbox): the first real payment is a real charge.

## 1. What to set up (about 10 minutes)

1. Sign in at <https://developer.squareup.com/apps> with the Square account that receives the money.
2. **Create an application** (any name, for example "SIPAW"). Open it and switch the top toggle to **Production**.
3. On the **Credentials** page, copy two values:
   - **Application ID** (starts with `sq0idp-`)
   - **Access token** (starts with `EAAA`). Treat it like a password.
4. In the left menu of the dashboard open **Locations** and copy the **Location ID** of the shop that takes the money.
5. Put the three values in the server file `config/sipaw.php`, in the `square` block (see `config.example.php`). Hostinger:
   File Manager → the app folder → `config/sipaw.php`. **Never** paste them into chat, git or a screenshot.
   Square's receipts and payouts then work as for any other payment.
6. Make sure the Square account currency is **CAD** (Admin → Pricing → Currency must match it).
7. Open **Admin → Pricing**: the "Square connection" box shows three green ticks when all is set.
8. Do one real test with a card of yours and refund it from the Square dashboard if you like.

Optional, only for old payment links: Developer Dashboard → Webhooks → add
`https://sipaw.nakilab.com/api/square/webhook.php` for `payment.created` and `payment.updated`, and copy its
signature key into `webhook_signature_key`. Card payments from the Plus page do not need it.

## 2. What the customer sees

- Price, then every discount on its own line: coupon code, perfect care weeks, paw coins, then tax, then the total.
- Tick boxes: **Use my paw coins** (on by default) and **Renew automatically every month** (on by default).
- The card is saved with Square (customer + card on file). Settings on the same page: auto-renew on/off,
  use coins on/off, remove card.
- Auto-renew: the cron job (`cron/tick.php`, every 5 minutes, work done in the 15-minute window) charges the saved card
  when Plus ends within 6 hours. The price is recomputed at that moment. If the card is declined it tries again the next
  day, up to 3 times, then switches auto-renew off and tells the owner. A referral free month is used before any charge.

## 3. Discount rules (Admin → Pricing)

Order: price → coupon → perfect care weeks → paw coins → tax.

- A paw coin is worth `coin value` cents (default 1). 255 coins = CAD 2.55 = 12.8% of CAD 19.99.
- Coupons, care weeks and coins together can take off at most `max discount %` (default 50) of the price.
  A "Free month" coupon is the exception.
- Coins used are **spent**: they are taken off the balance when the payment succeeds (not when it fails), so
  the next payment's coin discount starts from the new balance.
- Tax (default 13% HST) is added after the discounts, for owners in the listed countries (default `CA`).

## 4. Check list after deploy

- `php scripts/migrate.php` runs migration 027 (settings table, card columns, order columns).
- Admin → Pricing: set price, currency, coin value, discount limit, tax; confirm the three Square ticks.
- Plus page: pay with a real card, see "Payment received", see the coins drop on Rewards.
- Cron: Admin → Site health shows the last check-in; renewals appear in notifications.
