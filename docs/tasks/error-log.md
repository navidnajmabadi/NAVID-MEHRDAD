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
| 16 | Appearance: keep Friendly and Clean (Studio removed), Friendly is the first-time default, the choice is saved on the account so every device shows it | Done | New `owners.ui_skin` (migration 026). Tested: first time Friendly; a choice follows the account to a new device; an old per-phone choice moves to the account; the old Studio cookie falls back to Friendly |
| 17 | Login and sign up pages in the same Friendly / Clean look | Done | They already follow the look; fields were still square boxes and are now rounded. Before sign-in the phone's own choice (or Friendly) is used; after sign-in the account's look takes over |
| 18 | Settings: delete the "Phone notifications" section | Done | Removed with its script. Phone notifications can still be turned on from the bell ("Turn on phone notifications"). The "Send me a test" button is gone; the push test page `api/push-test.php` still exists |
| 19 | Buttons too high contrast (bright red "My pet is lost", strong blue "Add a new pet"): make them smoother and lighter | Done | Soft tinted buttons with dark text in both looks (text contrast 5.5:1 or better; the old white-on-red was 3.9:1). Sign-in pages keep one clear blue button, a little calmer. The floating "Ask SIPO" button is unchanged |
| 20 | Side menu: two lines under the logo, make it one | Done | The header line and the "My pet" block line stacked; the second is gone |
| 21 | Chat waiting bubble: say what SIPO is doing instead of three dots | Done | Shows e.g. "Setting that up as a reminder…", then "Writing the answer…", "Still on it, this one takes a moment…", "Almost there…"; the top status line repeats it. The first words are a guess from your message (the server cannot report its steps live) |
| 22 | Camera photo taken from the file button does not reach SIPO | Check live | Every photo is now redrawn as a normal JPEG (2000 px longest side) before sending, even with no file type; tested with a 4032 px photo (1.6 MB to 299 KB) and a file with no type. Not tested with a real phone camera (HEIC and the iPhone camera app) |
