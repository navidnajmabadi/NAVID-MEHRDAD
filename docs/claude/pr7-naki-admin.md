# PR 7 — NAKI Red Team + admin dashboards

Brief §4 + Navid: auto-apply prompt changes but keep versions to undo; brain graph Admin only;
keep all aggregate items and a report factory; ticket SLA < 7 days.

- **NAKI** (`lib/naki/naki.php`, brain key): scores real SIPO replies (redacted) with fixed signals
  (thanks, frustration, several questions, dashes, too long, false claims, "set your own timer",
  "you already asked") + the brain model's 1–5 judgement. Exam of 6 fixed cases through the real
  prompt. Every 3 days: proposal → guardrails (all sections kept, length ±30%) → exam must not get
  worse → auto-apply as a new version → after 3 days kept, or **rolled back automatically** if live
  quality fell by more than 0.3. Admin can run any step now. Memory cleanup daily.
- **Admin → NAKI brain**: quality KPIs, repeat rate, brain growth charts, issues, versions with
  "Make live", proposals with exam/live before → after, scored replies.
- **Admin → Insights**: revenue, Plus, owners new/active, AI tokens + $ estimate, monthly vs yearly,
  map (10 km cells), countries, species/sex/breeds, conditions by city (%), lab out-of-range rates,
  boards topics, **report factory** (6 CSV datasets, small groups hidden).
- **Admin → Site health**: uptime from cron heartbeats, gaps, DB speed/size, memory, load, disk,
  uploads, app errors (grouped, "Fixed"), PHP error log tail (secrets masked).
- **Support loop**: resolving/answering a ticket → SIPO tells the owner in chat + push.

SQL: `sql/015_naki_ops.sql` (automatic on deploy).
