# LOST HELP ME + Admin — Hostinger setup

Import **after** Phase 1 schema. Config stays at `{app}/config/ringringpaw.php` (or outside web root — see `HOSTINGER_INSTALL.md`).

## 1. SQL to import

In phpMyAdmin, select the **same** database as `db.name` in config, then import:

1. `sql/001_schema.sql` (if not already applied)
2. **`sql/002_lost_admin.sql`** ← this release

If an `ALTER TABLE` says “Duplicate column”, that piece already exists — run the remaining `CREATE TABLE` statements.

## 2. Config additions

Merge these keys into live `ringringpaw.php` (from `config.example.php`):

| Key | Purpose |
|-----|---------|
| `cron_secret` | Long random string for the escalation cron URL |
| `admin_setup_secret` | Long random string for `admin/setup.php` (first two admins) |
| `lost_radius_km` | Default `25` |
| `geocode.user_agent` | Nominatim-required app + contact string |

**Geocoder:** OpenStreetMap **Nominatim** (free). Results cached in `geocode_cache`; lat/lng/city/country stored on `owners` at signup and when address is saved on **Account**.

## 3. Create Navid + Mehrdad admin accounts

Admins live in **`admin_users`** (separate from owner accounts).

### Option A — browser setup (recommended once)

1. Set `admin_setup_secret` in config.
2. Open https://ringringpaw.nakilab.com/admin/setup.php  
3. Create **Navid**, then **Mehrdad** (setup closes after 2 admins).
4. Log in at https://ringringpaw.nakilab.com/admin/login.php  
5. Optionally blank `admin_setup_secret` after both exist.

### Option B — CLI on Hostinger

```bash
cd domains/nakilab.com/public_html/ringringpaw   # or your docroot
php scripts/create-admin.php "Navid" navid@example.com 'ChooseALongPassword'
php scripts/create-admin.php "Mehrdad" mehrdad@example.com 'ChooseALongPassword'
```

## 4. Cron (escalation: t0 already sent; +1h, +3h, +8h)

**URL:**

```text
https://ringringpaw.nakilab.com/cron/lost-escalate.php?key=YOUR_CRON_SECRET
```

**Hostinger:** hPanel → **Advanced → Cron Jobs** (or Scheduled Tasks) → fetch that URL every **5 or 10 minutes**.

Examples:

```bash
# wget
*/10 * * * * wget -q -O - "https://ringringpaw.nakilab.com/cron/lost-escalate.php?key=YOUR_CRON_SECRET" >/dev/null 2>&1

# curl
*/10 * * * * curl -fsS "https://ringringpaw.nakilab.com/cron/lost-escalate.php?key=YOUR_CRON_SECRET" >/dev/null 2>&1
```

Expect JSON `{"ok":true,"stats":{...}}`. Wrong key → HTTP 403.

Schedule meaning:

| Ping | When |
|------|------|
| t0 | Immediate on owner tap LOST HELP ME |
| +1h | Cron when `next_ping_at` due |
| +3h | Cron |
| +8h | Cron, then stop |
| Found | Owner marks Found → status `found`, no more pings + helpers get “Yoho rescued” |

## 5. Smoke-test checklist

### LOST HELP ME

1. Two owner accounts with addresses that geocode within ~25 km of each other (Account page shows lat/lng).
2. Account A: add pet with photo, distinctive marks, magic word, medical → **LOST HELP ME**.
3. Account B: inbox + email with alert; dashboard “Nearby LOST”; open alert → marks / magic word / medical.
4. Account A: **Mark Found** → **Yoho rescued** screen; B gets found notice; no further escalations.
5. Hit cron URL manually once with secret → JSON ok (no due pings if schedule not reached).

### Admin

1. `/admin/setup.php` → create Navid + Mehrdad → `/admin/login.php`.
2. Overview: user/pet counts and pets-by-type.
3. Accounts: search, suspend / unsuspend.
4. Map / cities: list + Leaflet points (after geocodes exist).
5. Support: owner sends ticket on `/support.php`; admin updates status/note.
6. Coupons: create + disable (checkout later).
7. Logs: scans, lost alerts, admin actions.

### Phase 1 regression

Signup, pet CRUD, QR scan, call, location share, owner inbox — still work.

## 6. Notifications

| Channel | LOST HELP ME |
|---------|----------------|
| Email | Yes |
| In-app inbox | Yes |
| Web Push | Yes when VAPID is set — installs PWA + permission; see [`WEB_PUSH.md`](./WEB_PUSH.md) |
