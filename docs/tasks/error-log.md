# Chat: error log and Square payments

Branch: `cursor/claude-error-log`

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Check migration 024 ran on the live database | Done | Confirmed in phpMyAdmin: 024 recorded, all three `si_` tables exist |
| 2 | Admin → Errors page (list, Copy, Fixed, test error) | Done | Tested locally in the browser |
| 3 | Capture warnings, crashes, fatals with a 6-letter code | Done | Crash page shows the same code |
| 4 | Capture browser errors and failed (5xx) requests | Done | `assets/js/error-report.js` + `api/client-error.php` |
| 5 | PHP log file the web cannot download | Done | `config/php-error.log` (needs `config/` writable on the server) |
| 6 | Migration 025 (owner, browser, trace on errors) | Check live | Runs on deploy with `php scripts/migrate.php` |
| 7 | Fix the errors Navid found this week | Pending | Waiting for his screenshots |
| 8 | Square payments | Pending | Waiting for answers: what is charged, Sandbox first, Canada or US |
