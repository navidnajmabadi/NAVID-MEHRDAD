# Ring Ring Paw — Phase 1 (PHP + MySQL PWA)

**Live:** https://ringringpaw.nakilab.com  
**Branch:** `PetFinder` · **Parent studio:** https://nakilab.com/

Scan a collar QR → see pet + owner phone → optional live location → owner gets email + inbox alert (and a map when location is shared).

## Phase 1 features

- Real **accounts** (name, address, phone, email, hashed password) and **pets** (max 5) with photo, type, breed suggest, color, magic word, distinctive marks, medical fields
- Short branded **landing** (teal + rose-gold, motion, demo placeholder)
- **PWA** — `manifest.webmanifest` + `sw.js` + install / Add to Home Screen prompt
- **Public scan page** + owner **dashboard** (scans, inbox, Leaflet live map)
- Per-pet **QR** with PNG + PDF download
- Secrets **outside web root** — see `docs/HOSTINGER_INSTALL.md`

**Not in Phase 1:** payments, referrals, LOST HELP ME 25 km broadcast, metal tags, full admin.

## Install on Hostinger

**Exact one-time steps for Navid:** [`docs/HOSTINGER_INSTALL.md`](docs/HOSTINGER_INSTALL.md)

Summary:

1. Create MySQL DB + user in hPanel  
2. Import `sql/001_schema.sql`  
3. Copy `config.example.php` → `/home/u233722910/domains/nakilab.com/config/ringringpaw.php` and fill credentials  
4. Merge/push to `PetFinder` (auto `git pull`)

## Local run

```bash
cp config.example.php config.local.php   # edit DB settings
mysql … < sql/001_schema.sql
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
| Web Push | Subscriptions stored; full send needs VAPID + push lib later |
| SMS | Out of scope |
