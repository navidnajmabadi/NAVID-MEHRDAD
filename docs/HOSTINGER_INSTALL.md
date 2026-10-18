# RingRingPaw Phase 1 — Hostinger install (one-time)

Do this **once** on Hostinger. Agents cannot SSH. After merge to `PetFinder`, auto-deploy pulls code; you still create the DB and drop `config` outside the web root.

## Paths

| What | Exact path |
|------|------------|
| Web root (possible) | `/home/u233722910/domains/nakilab.com/public_html/ringringpaw` **or** `/home/u233722910/domains/ringringpaw.nakilab.com/public_html` |
| Site URL | https://ringringpaw.nakilab.com |
| **Config (secrets) — either works** | `/home/u233722910/domains/nakilab.com/config/ringringpaw.php` **or** `/home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php` |
| Schema file (in repo) | `sql/001_schema.sql` |
| Config template (in repo) | `config.example.php` |

hPanel may list **ringringpaw.nakilab.com** as its own website (separate docroot). The app checks both absolute paths above, then relative fallbacks, then **`{app_root}/config/ringringpaw.php`** (temporary if File Browser put `config/` next to `tag.php` — blocked from HTTP by `.htaccess`). **Long-term correct:** keep secrets **outside** `public_html` at one of the absolute paths.

## Navid — one-time steps

### 1. Create MySQL database

In **hPanel → Databases → MySQL Databases**:

1. Create a database (example name: `u233722910_ringringpaw`).
2. Create a user with a strong password (example: `u233722910_rrp`).
3. Assign the user to the database with **All privileges**.
4. Note host (usually `localhost`), DB name, user, password.

### 2. Import schema

In **phpMyAdmin** (or MySQL CLI):

1. Select the new database.
2. Import / run the contents of `sql/001_schema.sql` from the repo  
   (after deploy: `/home/u233722910/domains/nakilab.com/public_html/ringringpaw/sql/001_schema.sql`).

### 3. Place config outside web root

Put the live file at **one** of these (both are checked by `lib/bootstrap.php`):

1. `/home/u233722910/domains/nakilab.com/config/ringringpaw.php`
2. `/home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php` ← use this if the subdomain is its **own** hPanel website

```bash
# Example if subdomain is its own site:
mkdir -p /home/u233722910/domains/ringringpaw.nakilab.com/config
cp /path/to/config.example.php \
   /home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php

# Or under the parent domain (also fine — app checks both):
mkdir -p /home/u233722910/domains/nakilab.com/config
cp /path/to/config.example.php \
   /home/u233722910/domains/nakilab.com/config/ringringpaw.php
```

Edit the file: `db.name` / `db.user` / `db.pass`, `app_url` → `https://ringringpaw.nakilab.com`, `mail_from` (e.g. `noreply@nakilab.com`).

**Never commit** real `ringringpaw.php` or passwords.

### 4. Deploy code

Merge this Phase 1 PR into **`PetFinder`** (or push). GitHub Action SSHs `git pull` into the web root.  
Until then, manual:

```bash
ssh -i ~/.ssh/nakilab_hostinger -p 65002 u233722910@46.202.198.223 \
  "cd domains/nakilab.com/public_html/ringringpaw && git pull --ff-only"
```

### 5. Uploads writable

Ensure `uploads/pets/` is writable by PHP (usually `755` or `775` on Hostinger).

### 6. Optional: default document

If the host still serves old `index.html` over `index.php`, remove or rename `index.html` after merge (Phase 1 replaces the static demo). This PR deletes the old HTML demo files.

## Notification reality (Hostinger)

| Channel | Phase 1 status |
|---------|----------------|
| **Email** | Primary — PHP `mail()` or SMTP from config |
| **In-app inbox** | Primary — dashboard Inbox |
| **Web Push** | Subscriptions can be saved; full VAPID push needs a PHP push library (Composer). Documented as follow-up. |
| **SMS** | Out of scope |

## Health check

After deploy: open https://ringringpaw.nakilab.com/health.php  
JSON should show `ok: true`, `pdo_connect: true`, `owners_table: true`, and `db_name` matching the DB where you imported `sql/001_schema.sql`. If `owners_table` is false, schema was imported into a **different** database than config.

## Smoke-test checklist

1. Open https://ringringpaw.nakilab.com — branded landing (logo, headline, CTA).  
2. **Phone A:** Sign up → add a pet (photo, type, breed, magic word, distinctive marks, medical) → download QR PNG (and PDF).  
3. **Install PWA:** Chrome Android “Install” / iOS Share → Add to Home Screen.  
4. **Phone B:** Scan QR (or open public URL) → see **pet name + owner name + phone** → Call works.  
5. On scan, Phone A gets **email** and **inbox** entry.  
6. Phone B taps **Share once** or **Share live** → Phone A dashboard shows scan + **Leaflet map** (live updates ~4s while sharing).  
7. Confirm second account cannot see first account’s pets.  
8. Confirm max 5 pets enforced.  
9. Confirm `config.example.php` has no real passwords; live secrets only under one of the two absolute `…/config/ringringpaw.php` paths above.

## Local PHP check (optional)

```bash
cp config.example.php config.local.php   # gitignored
# edit DB settings for a local MySQL
mysql -u root -p your_db < sql/001_schema.sql
php -S localhost:8000
```
