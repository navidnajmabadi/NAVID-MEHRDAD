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
| 7 | Fix the errors Navid found this week | In progress | Rows 9 to 13 below came from his screenshots |
| 8 | Square payments | Pending | Waiting for answers: what is charged, Sandbox first, Canada or US |
| 9 | Chat header: face overlaps the SIPO name and the progress bar on phones | Done | The face draws 64px wide inside a 48px tag; now scaled to 48px with a gap |
| 10 | Admin on phone: menu button hidden under the clock and notch | Done | Admin top bar and drawer now start below the safe area |
| 11 | Chat: header pushed under the phone's blurred status bar and a wide empty band at the bottom | Check live | Chat now sizes itself to the visible area (keyboard) and keeps the page at the top; tighter bottom gap. Could not test on a real iPhone |
| 12 | Three "Good evening" messages in the same minute (walk, tooth brushing, tooth brushing) | Check live | Cause: two cron URLs firing together each saw "nothing sent yet". One run at a time per owner now (database lock); greeting locked per chat. Reminder wording: "overdue" only for an earlier day; no repeated questions |
| 13 | Sent 7 photos but only 4 shown; want the text on top and a horizontal carousel | Done | All photos shown in one swipeable row under the text |
| 14 | Remove old per-job cron URLs in hPanel (only tick.php should remain) | Pending | Needs you: Hostinger → Cron jobs |
| 15 | Red dot on the menu (hamburger) button, always shown, unclear what it means | Done | Removed. It only meant "you have unread notifications", which the bell already shows with its number, and the notification center keeps them |
