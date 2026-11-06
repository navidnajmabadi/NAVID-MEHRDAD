# 4. Operations

## Environments
| Environment | Where | Notes |
|-------------|-------|-------|
| **Production** | https://sipaw.nakilab.com on Hostinger (app folder `domains/nakilab.com/public_html/sipaw`, subdomain document root points at it) | The only live copy. There is no staging server |
| **Local** | A developer machine with PHP, MariaDB and a browser | `config.local.php` (gitignored) holds a throwaway database. The assistant uses stand-ins for Square and mail when testing |

## Configuration
Secrets and settings live in **one PHP file on the server**, never in git. The app reads the first readable of these (see `rrp_config_paths()` in `lib/bootstrap.php`):
1. `/home/<user>/domains/nakilab.com/config/sipaw.php` (preferred, outside the web folder) or `ringringpaw.php`
2. `/home/<user>/domains/sipaw.nakilab.com/config/sipaw.php` or `ringringpaw.php`
3. relative fallbacks, then `config/sipaw.php` or `config/ringringpaw.php` inside the app (blocked from the web by `.htaccess`)
4. `config.local.php`, then `config.php` (local only)

**Admin → Pricing → Square connection shows the exact file the app is reading.** Edit that one. Never create a second, nearly empty `sipaw.php`: it is read first and would hide the real settings.

Keys (names only; copy `config.example.php` for the shape):
| Key | Purpose |
|-----|---------|
| `db` | Database host, name, user, password |
| `app_url` | `https://sipaw.nakilab.com` |
| `openai.api_key`, `openai.brain_api_key` | SIPO (empty = polite fallback), NAKI red team |
| `square.application_id`, `access_token`, `location_id` | Card payments (all three needed); `webhook_signature_key` only for old payment links |
| `billing.*` | Fallback price and currency; the live values are set in Admin → Pricing |
| `smtp.*`, `mail_from`, `mail_from_name` | Email (without SMTP the PHP `mail()` fallback is used) |
| `vapid.*` | Web Push keys (`php scripts/generate-vapid.php`) |
| `google.*` | Google sign-in |
| `cron_secret` | Key that protects the cron URLs |
| `admin_setup_secret` | One-time admin creation page |
| `super_owners` | Emails treated as super owners |
| `sipo.*`, `features`, `lost_radius_km`, `insights`, `geocode`, `session_name`, `live_ttl_seconds` | Tuning |

## Deploy
1. A branch `cursor/claude-<topic>` is cut from `PetFinder`; work is committed there.
2. When Navid says "finished", **one PR** into `PetFinder` is opened with a Done / Check live / Pending table.
3. **Navid merges.** The merge triggers `.github/workflows/deploy-sipaw.yml`: SSH to Hostinger, `cd` to the app folder, `git pull --ff-only`, `php scripts/migrate.php`.
4. The repository secret `HOSTINGER_SSH_KEY` (a deploy key, not a personal key) lets the Action log in. If the repository is ever made private, the server's own `git pull` also needs read access (see below).
5. After deploy, check the PR's "Check live" list on the real site.

Branch `main` is intentionally empty; `PetFinder` is the production branch. Nobody pushes to it directly.

### If the repository is private (recommended)
The server pulls from GitHub, so it must be able to sign in. One-time setup on the server: create an SSH key pair, add the **public** key to the repository as a read-only **deploy key**
(GitHub, repository Settings, Deploy keys), and switch the server clone's remote to the `git@github.com:` address. Do this **before** switching the repository to private, or deploys stop.

## Database migrations
- Files `sql/NNN_name.sql`, numbered, **additive only** (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN`).
- `scripts/migrate.php` runs the ones not yet in `schema_migrations`, **one statement at a time**; "already exists" errors are skipped, any other error stops the run and that file is retried on the next deploy.
- Therefore: **one change per `ALTER` statement** (a multi-part ALTER fails as a whole if one part exists).
- Check what ran: `SELECT name, applied_at FROM schema_migrations ORDER BY applied_at DESC;`
- Code must keep working for a moment between `git pull` and migrate (new columns are read inside try/catch with a fallback).

## Cron
One job in Hostinger → Cron jobs, every 5 minutes: `curl -fsS "https://sipaw.nakilab.com/cron/tick.php?key=<cron_secret>"`.
It does reminders, lost-pet escalation, follow-ups, SIPO nudges, rewards, renewals, **auto-renew charges**, the weekly email and NAKI.
Remove the older per-job URLs (`cron/lost-escalate.php`, `health-reminders.php`, `sipo-nudges.php`): they do work twice. Overlapping runs are guarded by database locks.
Check: Admin → Site health → "Last cron check-in" should be minutes old.

## Monitoring
| What | Where |
|------|-------|
| Errors (server crash, warning, browser error, failed request) | Admin → Errors; each has a 6-letter code, which crash pages also show to the user. Copy button gives one block to paste to a developer |
| PHP's own log | `config/php-error.log` (closed to the web) and the bottom of Admin → Errors |
| Uptime, speed, disk | Admin → Site health |
| SIPO quality and cost | Admin → Overview, Accounts (tokens per message), NAKI, Brain graph, Test lab |
| Payments | Admin → Pricing (counts, amount taken); Square dashboard |

## Runbooks
| Situation | Do this |
|-----------|---------|
| A user sends a screenshot or code of an error | Find the code in Admin → Errors, press Copy, add a row to the chat's `docs/tasks/` file, fix, test locally |
| "Session expired" for users | Check `config/` is writable (a `sessions` folder appears); check the cron job is not the cause; see `docs/tasks/error-log.md` row 25 |
| Deploy did not run | GitHub, Actions tab: read the failed step; common causes: server cannot read the repo, key changed, a migration error (re-run `php scripts/migrate.php` by SSH) |
| A migration failed | The runner stops and reports the file and statement; fix the SQL in a new migration if it already partly ran, never edit an applied file |
| Duplicate proactive messages | Remove old per-job cron URLs so only `tick.php` runs |
| Payment problem | Admin → Pricing shows the three Square ticks; Square dashboard shows the charge; `billing_orders.status` is `paid` or `failed`; failed cards retry daily 3 times |
| Key leaked | Rotate it at the provider (OpenAI, Square token "Replace", SMTP password), update the server config file, never commit it |
| Add a new admin | `php scripts/create-admin.php "Name" email 'password'` on the server, or `admin/setup.php` while fewer than 2 admins exist |

## Backups
Not documented in the code. To confirm: Hostinger's automatic backups for files and the database, and how long they are kept. Until confirmed, treat the database as the one irreplaceable asset
(owners, pets, health records, invoices) and take a manual export from phpMyAdmin before any risky change.
