# siPaw (PHP + MySQL PWA)

**Live:** https://sipaw.nakilab.com  
**Branch:** `PetFinder` · **Parent studio:** https://nakilab.com/  
**Hostinger folder:** `domains/nakilab.com/public_html/sipaw`

Scan a collar QR → see pet + owner phone → optional live location → owner gets email + inbox alert. Owners can broadcast **LOST HELP ME** to nearby app owners (~25 km). Navid & Mehrdad use the **admin** console.

## Features

- Real **accounts** and **pets** (max 5) with photo, type, breed, magic word, distinctive marks, medical
- Marketing **landing** at `/` (AI companion + community; Register CTA) + **PWA** · demo swap: `docs/LANDING_DEMO.md`
- **Public scan** + owner **dashboard** (scans, inbox, Leaflet live map)
- **Finder ↔ owner chat** (per scan, guest cookie; inbox + push notify)
- **Health Care** — structured vaccines, med reminders, feeding checklist, vet appointments
- Per-pet **QR** (PNG/PDF) + **lost poster** download (PNG/PDF)
- **LOST HELP ME** — geocode address (Nominatim + cache), notify owners within ~25 km, escalate t0 / +1h / +3h / +8h, **Yoho rescued** on Found
- **Admin** — stats, accounts/suspend, geo map, tickets, coupons stub, logs
- Secrets outside web root — see `docs/HOSTINGER_INSTALL.md` + `docs/LOST_ADMIN.md`
- **Web Push** on installed PWA (VAPID) — see `docs/WEB_PUSH.md`

### v1 migration 006

After 001–005 (or the combined full schema), import **`sql/006_v1_product.sql`** into `u233722910_sipaw` (phpMyAdmin → select DB → Import). Additive only: `CREATE TABLE IF NOT EXISTS` + documented `ALTER`s (ignore Duplicate column/key). Same file mirrored at `docs/sql/006_v1_product.sql`. Covers Free/Plus plan columns, SI chat kind on existing `chat_*`, rate limits, fact-based routine templates (`source_urls` / `last_verified_at`), forum, XP + Canada Mon–Sun boards, Sip ledger, referrals/subscriptions/first-100 promo, real-$ pet accounting, bug Sip reward fields, export markers. **English-only v1; no physical tags.** Full UI for forum/SI/Stripe comes in later tasks.

**Later:** Stripe checkout UI, walk GPS, food photo rating, GA4, ToS pages.

## Install on Hostinger

**Phase 1 DB/config:** [`docs/HOSTINGER_INSTALL.md`](docs/HOSTINGER_INSTALL.md)  
**LOST + Admin (SQL 002, cron, first admins):** [`docs/LOST_ADMIN.md`](docs/LOST_ADMIN.md)

Summary:

1. Create MySQL DB + user in hPanel  
2. Import `sql/001_schema.sql` → `002_lost_admin.sql` → `003_push_subscriptions.sql` → **`004_chat.sql`** → **`005_health.sql`** → **`006_v1_product.sql`** → **`007_t03_t15_deltas.sql`** (home area, wellness, SI secrets, template seeds)  
3. Copy `config.example.php` → live `sipaw.php` (or keep legacy `ringringpaw.php` filename) — include `cron_secret` + `admin_setup_secret` + optional `vapid` + empty `openai.api_key` / `google.*` placeholders; set `app_url` to `https://sipaw.nakilab.com`. **Add real OpenAI key when you are ready** (T05–T06); app works with empty key (graceful SIPO fallback).  
4. Point subdomain **sipaw.nakilab.com** document root at `public_html/sipaw`  
5. Merge/push to `PetFinder` (auto `git pull` into `…/public_html/sipaw`)  
6. Create Navid + Mehrdad via `/admin/setup.php`  
7. Schedule cron every 5–10 minutes:
   - `cron/lost-escalate.php?key=…`
   - `cron/health-reminders.php?key=…` (same `cron_secret`)
8. Optional: generate VAPID keys and enable PWA push (`docs/WEB_PUSH.md`)

## Local run

```bash
cp config.example.php config.local.php   # edit DB settings + secrets
mysql … < sql/001_schema.sql
mysql … < sql/002_lost_admin.sql
php scripts/create-admin.php "Navid" navid@example.com 'password-here'
php -S localhost:8000
```

### Sipo mascot preview

Open **`/sipo-preview.php`** after starting the PHP server — grid of 6 states × 4 themes (Ice Blue default, blue-body, mono, dark). Source package under `assets/mascot/` (untouched); public names at `assets/mascot/public/` (`sipo-{state}.svg`, `sipo-{state}-{theme}.webp|.gif`, `themes.json`).

## Deploy

Push/merge to **`PetFinder`** → GitHub Action SSHs `git pull` on Hostinger into `public_html/sipaw`.  
Manual fallback: see `docs/HOSTINGER_INSTALL.md`.

## Notifications

| Channel | Status |
|---------|--------|
| Email (`mail()` or SMTP) | Primary |
| In-app inbox | Primary |
| Web Push (installed PWA) | Pure PHP VAPID when keys set — `docs/WEB_PUSH.md` |
| SMS | Out of scope |
