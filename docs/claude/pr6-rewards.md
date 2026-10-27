# PR 6 — Paw coins, perfect weeks, referrals, Square

Navid 2026-10-28: coin with a paw (no name), balance always shown, rules not explained up front;
doing all routines weekly → 20% off a month; 3 paying friends = 1 free month (stacks); Square.

- Coins (`lib/rewards/coins.php`): vaccine/check-up 10, parasite 8, lab report 15 (same report once),
  food label 5, walk ≥10 min 3 (2/day), meal 1 (3/day), bathroom 1 (3/day), treatment check-in 2
  (1/day), grooming 2 (4/day), profile field 2 (once), post 2 (3/day), comment 1 (5/day),
  perfect week 25. Removed posts lose their coins. Amounts overridable in config.
  Coins accumulate now; their $ value is decided later (Navid: "in future talk about").
- Perfect week (Mon–Sun local): every reminder due that week done + used on ≥ 4 days →
  5% off the next Plus payment, max 20% (4 weeks); weeks are marked spent when used.
- Referrals: 6-char code + link; signup attaches it; first completed Square payment counts;
  every 3 → +1 free month (stacks); SIPO + push tells the referrer.
- Square: Payment Link per purchase (price already discounted), signed webhook
  (`payment.created/updated`, HMAC over URL + body), idempotent; Plus extended by months;
  renewal reminder 3 days before the end. Free months extend Plus with no payment.

SQL: `sql/014_rewards_billing.sql` (automatic on deploy).
