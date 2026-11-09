# CLAUDE.md: SIPAW (SI PAW by NAKILAB)

Rules for the AI coding assistant working in this repository. Read this first; the full project documents are in [`docs/sdlc/`](docs/sdlc/README.md).
This file is for the project. Navid's personal rules live in his own global Claude file; both apply.

## What this is
A pet-care companion web app (PWA) at https://sipaw.nakilab.com. **SIPO** is the chat companion. PHP 8 + MariaDB on Hostinger shared hosting. No framework, no Composer, no build step.
Owner and approver: **Navid**. Co-founder: Mehrdad. Production branch: **`PetFinder`**. Branch `main` is intentionally empty.

## People rules (non-negotiable)
- **Never merge a PR yourself.** Navid merges. Never push to `PetFinder` directly.
- **Never commit or print secrets** (API keys, tokens, passwords, the live config file). Do not ask Navid to paste keys in chat; tell him which server file to edit.
- Say **"SI"** (capital I), never "Si". Coin is "paw coin". SIPO is a caring, mindful, Baymax-like companion; never diagnoses, never gives doses.
- Reply in the language Navid writes (English or Persian). **Code, commits, PR text and app text stay English.**
- Navid sends problems as screenshots, often in several chats at once. Each chat has its own task table (below). Do not edit another chat's table.
- When you add something extra you judged useful (he pre-approved good extras), **list it clearly** in the report.
- Say plainly what you could not test (real phone, real Square, real email). Do not claim done without running it.

## Workflow (lightweight SDLC)
1. Restate the problem in one line; for more than one file, state the approach before coding.
2. Work on a branch `cursor/claude-<topic>` cut from `origin/PetFinder` (a git worktree is fine).
3. Keep a task table in `docs/tasks/<chat-name>.md`: one numbered row per screenshot/request, status **Done** / **Check live** / **Pending**, a short Notes column.
4. Test it for real before saying done (see Testing). Look at UI changes in a browser at phone width (390 px).
5. Commit small. **Open a PR only when Navid says "finished" / "open the PR"**, one PR per batch, base `PetFinder`, body = Done / Check live / Pending table + deploy steps.
6. If behaviour, data or architecture changed, update `docs/sdlc/02-spec-features.md` and `03-architecture.md` in the same PR.
7. A mistake that happens a **second** time gets written into "Gotchas" below.

## Run and check locally
```bash
cp config.example.php config.local.php        # point it at a throwaway local database (gitignored)
for f in sql/00[1-8]_*.sql; do mysql ... t < $f; done   # base schema (the runner treats 001-008 as baseline)
php scripts/migrate.php                        # applies 009 onwards
php -S 127.0.0.1:8099                          # run the app
php -l path/to/file.php                        # lint EVERY PHP file you changed
node --check assets/js/file.js                 # and every JS file
```
- There is **no automated test suite yet**. Verify by running the change: pages in a browser, APIs with curl and a cookie jar, money maths from a small PHP script that calls `rrp_billing_quote()`.
- Test Square against a tiny local stand-in: set `square.base_url` in `config.local.php`. Test mail with a local SMTP listener (`smtp.host` 127.0.0.1). Never test with real keys.
- Clean up test files, servers and databases before you finish.

## Code conventions
- Layers: **Model** `lib/**` (DB and rules, no HTML) → **ViewModel** `lib/viewmodels/` (plain array per screen) → **View** root `*.php` + `assets/js/` (no SQL). Details: `docs/sdlc/03-architecture.md`.
- Functions are prefixed `rrp_`. Escape output with `rrp_h()`. Every POST needs the CSRF token (`rrp_verify_csrf`, API: `rrp_api_owner_post()`).
- Money is **integer cents**; stored units are **kg and km** (convert for display with `lib/units.php`); times are **UTC** in the database, shown in the owner's time zone.
- Look and feel: two skins (Friendly default, Clean) in `assets/css/skins.css`. App buttons are `.btn`, admin buttons are `.ad-btn`. Phone first, 44 px touch targets, readable text weight (500).
- User-facing text is English, short, kind. SIPO speaks in one short question at a time, no dashes in its replies.
- New PHP columns on `owners` must be **read separately with a fallback** (see `rrp_billing_profile`): `rrp_current_owner()` selects a fixed column list so a deploy before its migration never locks anyone out.
- Do not add libraries. If you need PDF, mail, push or a QR, extend the small in-repo writers (`lib/pdf.php`, `lib/mailer.php`, `lib/webpush.php`).

## Database migrations
- New file `sql/NNN_name.sql` with the next number (the latest is in `sql/`). **Additive only.**
- `scripts/migrate.php` runs it **one statement at a time** and skips "already exists". So: **one change per `ALTER TABLE` statement.** Never edit a file that has been applied.
- The deploy runs `php scripts/migrate.php` automatically after `git pull`.

## Gotchas learned here
- `setcookie()` fails once output has started. Cookies the page needs (CSRF) are sent from `lib/bootstrap.php` before any HTML. Do not create a token cookie in the middle of a page.
- Shared hosting clears the common PHP session folder every ~24 minutes. Sessions live in `config/sessions`; login also has the 60-day remember token.
- The live config is `sipaw.php` **or** the legacy `ringringpaw.php`; the first readable path wins. Never create a second file. Admin → Pricing shows the one in use.
- After the 7-day trial an owner without Plus is limited to a short allow-list of pages (`rrp_access_script_allowed()` in `lib/access.php`). **A new page or API that must stay usable after the trial has to be added there**, and any new write action on Health must respect read-only (`rrp_access($owner)['readonly']`).
- The migration runner splits a file on **every `;`** (even inside quotes) and drops lines that start with `--`. Never put a semicolon or a line starting with `--` inside a text value in a migration.
- `robots.txt`: use only `Disallow` lines and one shared `User-agent` group. A crawler with its own block ignores the `*` block, and simple crawlers stop at the first matching rule, so an early `Allow: /` unblocks everything for them. Check with Python's `urllib.robotparser` before changing it.
- Blog text is stored as markdown-lite and rendered by `rrp_blog_render()` (HTML is escaped, never run). Do not output a post body any other way.
- In zsh, `echo` turns `\u003C` into `<`, which can fake an XSS finding when you test JSON output; read the raw file instead.
- Hostinger has no usable PHP error log; use Admin → Errors (every error has a 6-letter code).
- iPhone: the keyboard can leave the page pushed up; the chat sizes itself to `visualViewport`. Camera photos can be huge or have no file type; they are redrawn as JPEG before upload.
- Overlapping cron runs sent duplicate messages; per-owner database locks (`rrp_lock_try`) prevent it. Keep new cron work behind a lock.
- A 99.9% coupon left CAD 0.02 and an early rule refused it. Check Square's real limits (minimum card payment in Canada is CAD 0.01) before adding your own.
- macOS: `sed -i ''` (empty suffix); zsh treats a lone `==word` as a command and an unmatched glob as an error.
- Extensions the code relies on: `curl` (Square, OpenAI), `iconv` (PDF text), PDO MySQL. Nothing else is assumed.

## Where things are
| Need | Look at |
|------|---------|
| Product, features, architecture, ops, plan | `docs/sdlc/` |
| Running change log and open items per chat | `docs/tasks/` |
| Install and deploy notes | `docs/HOSTINGER_INSTALL.md`, `docs/SQUARE.md`, `docs/WEB_PUSH.md`, `docs/LOST_ADMIN.md` |
| SIPO token cost | `docs/TOKENS.md` |
| Older feature briefs | `docs/claude/pr*.md` |
