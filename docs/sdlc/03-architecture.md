# 3. Architecture

## Stack
| Part | Choice |
|------|--------|
| Language | PHP 8 (production 8.3; uses `declare(strict_types=1)` and `match`, so 8.0 or newer) |
| Database | MySQL / MariaDB through PDO, utf8mb4 |
| Front end | Server-rendered PHP pages + small vanilla JavaScript files; installable PWA. No build step, no framework, no Composer |
| Maps | Leaflet with OpenStreetMap tiles; d3 (from a CDN) for the admin brain graph |
| Hosting | Hostinger shared hosting; `sipaw.nakilab.com` document root is the app folder |
| Background work | One cron URL every 5 minutes (`cron/tick.php`) |
| Source control and deploy | GitHub, branch `PetFinder`; a GitHub Action pulls onto the server |

Why so plain: shared hosting cannot run workers or websockets, so the design is "render on the server, poll lightly, use Web Push for the rest".

## Code layout
```
/ (app root, the web document root)
├─ *.php             Pages (login.php, si-chat.php, care.php, billing.php, …). Thin: load a ViewModel, print HTML
├─ api/              JSON endpoints used by the pages' JavaScript (api/si/*, api/billing/*, api/pets/*, …)
├─ admin/            Admin console pages (separate admin login)
├─ lib/              All logic (see below)
│  ├─ bootstrap.php  Starts everything: config, session, CSRF cookie, error handlers, loads the modules
│  ├─ sipo/          The AI companion engine
│  ├─ care/          Health, food, daily life, labs, reminders
│  ├─ lost/          Lost-pet playbook
│  ├─ rewards/       Paw coins, perfect weeks, referrals
│  ├─ payments/      Square billing
│  ├─ naki/          Red team, brain graph, test lab
│  ├─ social/        Pack board helpers (switched off)
│  ├─ insights/      Admin metrics
│  ├─ ops/           Error log and site health
│  ├─ openai/        OpenAI client
│  ├─ viewmodels/    One plain array per screen
│  └─ views/         Shared HTML fragments
├─ assets/           css/, js/, img/ (logos, mascot), mascot sprites
├─ blog.php, blog-post.php, sitemap.php, llms.php, robots.txt   Public blog and the files for search engines and assistants (rewrites for /blog, /sitemap.xml, /llms.txt in `.htaccess`)
├─ cron/             tick.php (the one job) and the older per-job URLs
├─ scripts/          Command-line tools: migrate.php, create-admin.php, generate-vapid.php, …
├─ sql/              Numbered migrations 001 to 028
├─ config/           Closed to the web (.htaccess). Holds the live config file and session files; never committed
├─ uploads/          Pet photos (PHP files blocked)
├─ docs/             Project documents (this folder, tasks, install notes)
└─ .github/workflows/deploy-sipaw.yml
```
Counts today: about 19,000 lines in `lib/`, about 5,900 in the page files.

### Layers (MVVM on PHP)
| Layer | Where | Rule |
|-------|-------|------|
| Model | `lib/*.php`, `lib/<area>/*.php` | Database and rules only. No HTML, no `$_GET` |
| ViewModel | `lib/viewmodels/*.php` | One plain array per screen. The same array is printed by PHP and returned as JSON by `api/`, so a native app can reuse it |
| View | the `*.php` pages and `assets/js/*.js` | Prints the ViewModel. No SQL |
Older pages move to this shape when they are touched. Every function is prefixed `rrp_` (the project's old name, RingRingPaw).

## A request, step by step
1. Apache maps `/dashboard` to `dashboard.php` (clean URLs in `.htaccess`).
2. `lib/bootstrap.php` runs first: it finds the **config file** (first readable of a fixed list of paths, including `config/sipaw.php` and the legacy `ringringpaw.php`),
   sets the PHP session (own folder `config/sessions`), sends the **form-token cookie** (it must be sent before any output), installs the error handlers, and loads every module.
3. The page calls `rrp_require_owner()` (or `rrp_require_admin()`); a "remember me" token restores a login when the session is gone. For owners, `lib/access.php` then decides the **access state** (admin, plus, trial, expired, full): an expired owner is sent to Billing (pages) or gets HTTP 402 (APIs) unless the page is on the short allow-list.
4. The page builds its ViewModel, then prints HTML through `lib/layout.php` (header, side menu, icons, skin).
5. JavaScript talks to `api/*.php`. Every POST carries the CSRF token; `rrp_api_owner_post()` checks login and token and returns JSON.

## SIPO, the engine
Flow for one owner message (`lib/sipo/engine.php`, `rrp_sipo_send`):
```
message in
  → store it, log the request (domain, redaction)
  → onboarding intercept  (button taps for the photo/profile steps, no model)
  → labs intercept        (file uploads)
  → learn intercept       (answers to SIPO's learning questions)
  → model call            (OpenAI chat with tools; at most 3 tool rounds)
        system prompt = core prompt (versioned, editable by NAKI) + pet card + memory facts + summary + live context
                        (local time, weather, units) + only the blocks this message needs
  → guards                (button guard, greeting fix, false "Done" fix, reminder-time check, yes-confirmation)
  → store reply, score it (si_request_log, si_brain_scores), offer next setup question
reply out (text, buttons, links, mascot mood)
```
Key ideas:
- **One thread per pet** (`chat_threads`, `chat_messages`); facts in `sipo_memory`; long chats are summarised so tokens stay flat.
- **Tools** are real PHP functions; SIPO may only claim an action if the tool ran (a guard rewrites false "Done").
- **Router** (`lib/sipo/router.php`) offers only the tools and prompt sections a message needs; this cut the per-call prompt from about 11,200 to 6,400 to 8,200 tokens (estimated).
- **Proactive messages** (`lib/sipo/nudges.php`): cron picks owners at their usual hours (quiet 21:00 to 09:00), takes a **database lock per owner** so overlapping cron runs cannot double-send.
- **Limits** (cool-down, daily caps) exist but are off during the test period (`sipo.limits_enabled`). The **trial rules** are separate (`lib/access.php`, switch in Admin → Pricing): owners past the trial without Plus cannot chat, and SIPO sends them no messages.

## NAKI, the red team
Scores real replies (emails/phones removed first), runs a fixed exam of 6 cases against the live prompt, proposes a small prompt edit every 3 days,
applies it only if the exam does not get worse, keeps versions in `si_prompt_versions`, and rolls back if live quality drops by more than 0.3.
The brain graph (TF-IDF nearest neighbours, no paid embeddings) groups requests by topic; the test lab runs manual and AI-made test cases.
Uses a separate key (`openai.brain_api_key`).

## Background jobs
`cron/tick.php?key=<cron_secret>` every 5 minutes. Every run: lost-pet escalation, reminders, follow-ups, meals, food supply, onboarding nudge, heartbeat.
Every 15 minutes: pattern watch, learning, SIPO nudges, rewards, renewal reminders, **auto-renew charges**, weekly report (Sundays), NAKI.
Older per-job URLs still work but should be removed from the hosting cron list.

## Data (about 80 tables, `sql/001` to `028`)
| Area | Tables (main) |
|------|---------------|
| Accounts | `owners`, `auth_remember_tokens`, `auth_login_attempts`, `password_reset_tokens`, `admin_users`, `owner_blocks` |
| Pets | `pets`, `pet_weight_logs`, `pet_expenses`, `pet_routines`, `routine_templates`, `pet_register_*` |
| Health | `vaccination_records`, `medication_schedules`, `vet_appointments`, `vet_treatments`, `treatment_logs`, `health_examinations`, `health_lab_results`, `health_share_links`, `care_reminders`, `pet_foods`, `feeding_*` |
| Daily | `pet_walks`, `pet_bathroom_logs`, `pet_daily_checks`, `wellness_*` |
| SIPO | `chat_threads`, `chat_messages`, `sipo_memory`, `sipo_nudges`, `sipo_token_window`, `si_message_secrets`, `si_usage_daily` |
| NAKI | `si_prompt_versions`, `si_brain_runs`, `si_brain_scores`, `si_eval_cases`, `si_request_log`, `si_clusters`, `si_tests`, `si_kpi_daily`, `naki_proposals` |
| Lost and scans | `scans`, `lost_alerts`, `lost_alert_recipients`, `lost_sightings`, `geocode_cache`, `owner_activity_hours` |
| Content | `blog_posts` (slug, title, summary, markdown-lite body, cover, status, publish date) |
| Access | columns on `owners`: `trial_ends_at`, `canceled_at`, `cancel_reason`, `plan`, `plan_expires_at` |
| Rewards and billing | `sip_ledger`, `reward_weeks`, `referrals`, `subscriptions`, `billing_orders`, `billing_events`, `coupons`, `promo_first`, `app_settings` |
| Notifications | `notifications`, `push_subscriptions` |
| Ops | `app_errors`, `site_heartbeats`, `admin_action_logs`, `support_tickets` |
| Social (off) | `forum_*`, `xp_events`, `leaderboard_weekly_snapshots`, `moderation_log` |
Stored units are always kg and km. Times are stored in UTC; each owner has a time zone.

## Integrations
| Service | Used for | Config key |
|---------|----------|------------|
| OpenAI | SIPO replies, file reading, NAKI | `openai.api_key`, `openai.brain_api_key` |
| Square | Card form (Web Payments SDK), customers, cards on file, payments | `square.*` (production only) |
| Open-Meteo | Weather for SIPO and walk windows (free, no key) | none |
| Nominatim | Address to coordinates, cached | `geocode` |
| Google | Sign in with Google | `google.*` |
| Web Push | Push to installed app (VAPID, pure PHP) | `vapid.*` |
| SMTP / `mail()` | Email | `smtp.*`, `mail_from` |
| OpenStreetMap, cdnjs | Map tiles, Leaflet, d3 | none |

## Security design
- **Secrets only in the server config file** (outside git, `config/` denied to the web). The repo contains `config.example.php` with placeholders only.
- **CSRF:** a long-lived cookie token (double submit) on every form and API call; `SameSite=Lax`, `HttpOnly`.
- **Sessions:** own folder, 7-day lifetime; "remember me" is a hashed, rotating 60-day token.
- **Login:** throttle per email+IP; password reset by one-time link; open-redirect check.
- **Payments:** the card number is typed into Square's form and never reaches the server; the server holds only Square's customer and card ids and the last four digits. Prices are computed on the server, never taken from the browser.
- **Privacy:** personal details are removed before text goes to the review model; admins see a super-owner-only error detail.
- **Uploads:** PHP files are blocked in `uploads/`; photos from the camera are shrunk in the browser; files sent to SIPO are read and then deleted.
- **Headers:** HSTS, nosniff, frame protection (`.htaccess`).
- **Known gap:** the two super-owner email addresses are written in `lib/plan.php` as defaults; move them to the config file (see the plan).
