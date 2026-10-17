# RingRingPaw Phase 1 — Hostinger install (one-time)

Do this **once** on Hostinger. Agents cannot SSH. After merge to `PetFinder`, auto-deploy pulls code; you still create the DB and drop `config` outside the web root.

## Paths

| What | Exact path |
|------|------------|
| Web root (git clone) | `/home/u233722910/domains/nakilab.com/public_html/ringringpaw` |
| Site URL | https://ringringpaw.nakilab.com |
| **Config (secrets)** | `/home/u233722910/domains/nakilab.com/config/ringringpaw.php` |
| Schema file (in repo) | `sql/001_schema.sql` |
| Config template (in repo) | `config.example.php` |

Same pattern as nakilab.com: secrets live in `domains/nakilab.com/config/`, never inside `public_html`.

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

```bash
# On server (SSH from Navid’s Mac), or use File Manager:
mkdir -p /home/u233722910/domains/nakilab.com/config
cp /home/u233722910/domains/nakilab.com/public_html/ringringpaw/config.example.php \
   /home/u233722910/domains/nakilab.com/config/ringringpaw.php
# Then edit ringringpaw.php — set db name/user/pass, mail_from, app_url
```

Edit `/home/u233722910/domains/nakilab.com/config/ringringpaw.php`:

- `db.name`, `db.user`, `db.pass` — from step 1  
- `app_url` → `https://ringringpaw.nakilab.com`  
- `mail_from` → an address Hostinger allows (e.g. `noreply@nakilab.com`)  
- Optional: enable `smtp` if `mail()` is unreliable  
- Optional: VAPID keys for future Web Push (email + inbox work without them)

**Never commit** `config/ringringpaw.php` or any real passwords.

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

## Smoke-test checklist

1. Open https://ringringpaw.nakilab.com — branded landing (logo, headline, CTA).  
2. **Phone A:** Sign up → add a pet (photo, type, breed, magic word, distinctive marks, medical) → download QR PNG (and PDF).  
3. **Install PWA:** Chrome Android “Install” / iOS Share → Add to Home Screen.  
4. **Phone B:** Scan QR (or open public URL) → see **pet name + owner name + phone** → Call works.  
5. On scan, Phone A gets **email** and **inbox** entry.  
6. Phone B taps **Share once** or **Share live** → Phone A dashboard shows scan + **Leaflet map** (live updates ~4s while sharing).  
7. Confirm second account cannot see first account’s pets.  
8. Confirm max 5 pets enforced.  
9. Confirm `config.example.php` has no real passwords; live secrets only under `config/ringringpaw.php`.

## Local PHP check (optional)

```bash
cp config.example.php config.local.php   # gitignored
# edit DB settings for a local MySQL
mysql -u root -p your_db < sql/001_schema.sql
php -S localhost:8000
```
