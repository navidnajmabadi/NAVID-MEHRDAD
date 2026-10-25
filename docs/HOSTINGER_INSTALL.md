# siPaw — Hostinger install

Do this **once** on Hostinger. Agents cannot SSH. After merge to `PetFinder`, auto-deploy pulls code into `public_html/sipaw`; you still create the DB and drop `config` outside the web root.

## Paths

| What | Exact path |
|------|------------|
| Web root | `/home/u233722910/domains/nakilab.com/public_html/sipaw` |
| Site URL | https://sipaw.nakilab.com |
| Subdomain document root | Must point at `…/public_html/sipaw` (not the old `ringringpaw` folder) |
| **Config (secrets) — either works** | `/home/u233722910/domains/nakilab.com/config/sipaw.php` **(preferred)** or legacy `…/config/ringringpaw.php` · if subdomain is its own site: `…/domains/sipaw.nakilab.com/config/sipaw.php` |
| Schema files (in repo) | `sql/001_schema.sql` → `002` → `003` → **`004_chat.sql`** → **`005_health.sql`** |
| LOST + Admin setup | [`LOST_ADMIN.md`](./LOST_ADMIN.md) |
| Web Push (PWA) | [`WEB_PUSH.md`](./WEB_PUSH.md) |
| Config template (in repo) | `config.example.php` |

hPanel may list **sipaw.nakilab.com** as its own website (separate docroot). The app checks absolute paths above (sipaw.php first, then legacy ringringpaw.php), then relative fallbacks, then **`{app_root}/config/sipaw.php`**. **Long-term correct:** keep secrets **outside** `public_html`.

**Assets** use site-root paths (`/assets/…`), so the subdomain must be rooted at the app folder — not served as `nakilab.com/sipaw/…` with a wrong `app_url`.

## Navid — one-time steps

### 1. Create MySQL database

In **hPanel → Databases → MySQL Databases**:

1. Create a database (example name: `u233722910_ringringpaw` — rename optional; keep matching `db.name`).
2. Create a user with a strong password (example: `u233722910_rrp`).
3. Assign the user to the database with **All privileges**.
4. Note host (usually `localhost`), DB name, user, password.

### 2. Import schema

In **phpMyAdmin** (or MySQL CLI):

1. Select the new database.
2. Import / run in order:
   - `sql/001_schema.sql`
   - `sql/002_lost_admin.sql` (LOST HELP ME + Admin)
   - `sql/003_push_subscriptions.sql` (Web Push columns)
   - **`sql/004_chat.sql`** (finder ↔ owner chat) — required for Chat tab
   - **`sql/005_health.sql`** (vaccines, meds, feeding, vet) — required for Health tabs  
   Path after deploy: `…/sipaw/sql/`. Full cron/admin steps: [`LOST_ADMIN.md`](./LOST_ADMIN.md). Push: [`WEB_PUSH.md`](./WEB_PUSH.md).

### 3. Place config outside web root

Put the live file at **one** of these (checked by `lib/bootstrap.php`):

1. `/home/u233722910/domains/nakilab.com/config/sipaw.php` ← preferred  
2. `/home/u233722910/domains/nakilab.com/config/ringringpaw.php` ← legacy filename still OK  
3. `/home/u233722910/domains/sipaw.nakilab.com/config/sipaw.php` ← if subdomain is its **own** hPanel website

```bash
# Preferred under parent domain:
mkdir -p /home/u233722910/domains/nakilab.com/config
cp /path/to/config.example.php \
   /home/u233722910/domains/nakilab.com/config/sipaw.php

# Or if subdomain is its own site:
mkdir -p /home/u233722910/domains/sipaw.nakilab.com/config
cp /path/to/config.example.php \
   /home/u233722910/domains/sipaw.nakilab.com/config/sipaw.php
```

Edit the file: `db.name` / `db.user` / `db.pass`, **`app_url` → `https://sipaw.nakilab.com`**, `mail_from` (e.g. `noreply@nakilab.com`), `mail_from_name` → `siPaw`.  
If you already have a live `ringringpaw.php`, you only need to change `app_url` (and optionally rename the file to `sipaw.php`).  
For home-screen notifications: generate VAPID keys (`php scripts/generate-vapid.php`) and paste into `vapid` — see [`WEB_PUSH.md`](./WEB_PUSH.md).

**Never commit** real `sipaw.php` / `ringringpaw.php` or passwords.

### 4. Subdomain document root + deploy code

1. In hPanel, set **sipaw.nakilab.com** document root to `domains/nakilab.com/public_html/sipaw` (the renamed folder).
2. Merge/push to **`PetFinder`**. GitHub Action SSHs `git pull` into that folder.

Until then, manual:

```bash
ssh -i ~/.ssh/nakilab_hostinger -p 65002 u233722910@46.202.198.223 \
  "cd domains/nakilab.com/public_html/sipaw && git pull --ff-only"
```

### 5. Uploads writable

Ensure `uploads/pets/` is writable by PHP (usually `755` or `775` on Hostinger).

### 6. Optional: default document

If the host still serves old `index.html` over `index.php`, remove or rename `index.html` after merge.

## Notification reality (Hostinger)

| Channel | Status |
|---------|--------|
| **Email** | Primary — PHP `mail()` or SMTP from config |
| **In-app inbox** | Primary — dashboard Inbox |
| **Web Push** | Full send via pure-PHP VAPID (`lib/webpush.php`) when `vapid` keys are set — see [`WEB_PUSH.md`](./WEB_PUSH.md). No Composer required. |
| **SMS** | Out of scope |

## Health check

After deploy: open https://sipaw.nakilab.com/health.php  
JSON should show `ok: true`, `pdo_connect: true`, `owners_table: true`, and `db_name` matching the DB where you imported `sql/001_schema.sql`. If `owners_table` is false, schema was imported into a **different** database than config.

## Smoke-test checklist

1. Open https://sipaw.nakilab.com — branded landing (logo, headline, CTA); CSS and logos load.  
2. **Phone A:** Sign up → add a pet (photo, type, breed, magic word, distinctive marks, medical) → download QR PNG (and PDF).  
3. **Install PWA:** Chrome Android “Install” / iOS Share → Add to Home Screen.  
4. **Phone B:** Scan QR (or open public URL) → see **pet name + owner name + phone** → Call works.  
5. On scan, Phone A gets **email** and **inbox** entry.  
6. Phone B taps **Share once** or **Share live** → Phone A dashboard shows scan + **Leaflet map** (live updates ~4s while sharing).  
7. Confirm second account cannot see first account’s pets.  
8. Confirm max 5 pets enforced.  
9. Confirm `config.example.php` has no real passwords; live secrets only under an absolute `…/config/sipaw.php` (or legacy `ringringpaw.php`) path above.

## Local PHP check (optional)

```bash
cp config.example.php config.local.php   # gitignored
# edit DB settings for a local MySQL
mysql -u root -p your_db < sql/001_schema.sql
php -S localhost:8000
```
