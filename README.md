# Ring Ring Paw (PHP + MySQL PWA)

**Live:** https://ringringpaw.nakilab.com  
**Branch:** `PetFinder` · **Parent studio:** https://nakilab.com/

Scan a collar QR → see pet + owner phone → optional live location → owner gets email + inbox alert. Owners can broadcast **LOST HELP ME** to nearby app owners (~25 km). Navid & Mehrdad use the **admin** console.

## Features

- Real **accounts** and **pets** (max 5) with photo, type, breed, magic word, distinctive marks, medical
- Short branded **landing** + **PWA**
- **Public scan** + owner **dashboard** (scans, inbox, Leaflet live map)
- **Finder ↔ owner chat** (per scan, guest cookie; inbox + push notify)
- **Health Care** — structured vaccines, med reminders, feeding checklist, vet appointments
- Per-pet **QR** (PNG/PDF) + **lost poster** download (PNG/PDF)
- **LOST HELP ME** — geocode address (Nominatim + cache), notify owners within ~25 km, escalate t0 / +1h / +3h / +8h, **Yoho rescued** on Found
- **Admin** — stats, accounts/suspend, geo map, tickets, coupons stub, logs
- Secrets outside web root — see `docs/HOSTINGER_INSTALL.md` + `docs/LOST_ADMIN.md`
- **Web Push** on installed PWA (VAPID) — see `docs/WEB_PUSH.md`

**Later:** payments / Stripe, metal tags, walk/activity, training tips.

## Install on Hostinger

**Phase 1 DB/config:** [`docs/HOSTINGER_INSTALL.md`](docs/HOSTINGER_INSTALL.md)  
**LOST + Admin (SQL 002, cron, first admins):** [`docs/LOST_ADMIN.md`](docs/LOST_ADMIN.md)

Summary:

1. Create MySQL DB + user in hPanel  
2. Import `sql/001_schema.sql` → `002_lost_admin.sql` → `003_push_subscriptions.sql` → **`004_chat.sql`** → **`005_health.sql`**  
3. Copy `config.example.php` → live `ringringpaw.php` (include `cron_secret` + `admin_setup_secret` + optional `vapid`)  
4. Merge/push to `PetFinder` (auto `git pull`)  
5. Create Navid + Mehrdad via `/admin/setup.php`  
6. Schedule cron every 5–10 minutes:
   - `cron/lost-escalate.php?key=…`
   - `cron/health-reminders.php?key=…` (same `cron_secret`)
7. Optional: generate VAPID keys and enable PWA push (`docs/WEB_PUSH.md`)

## Local run

```bash
cp config.example.php config.local.php   # edit DB settings + secrets
mysql … < sql/001_schema.sql
mysql … < sql/002_lost_admin.sql
php scripts/create-admin.php "Navid" navid@example.com 'password-here'
php -S localhost:8000
```

## Deploy

Push/merge to **`PetFinder`** → GitHub Action SSHs `git pull` on Hostinger.  
Manual fallback: see `docs/HOSTINGER_INSTALL.md`.

## Notifications

| Channel | Status |
|---------|--------|
| Email (`mail()` or SMTP) | Primary |
| In-app inbox | Primary |
| Web Push (installed PWA) | Pure PHP VAPID when keys set — `docs/WEB_PUSH.md` |
| SMS | Out of scope |
