# RingRingPaw — QR Pet ID MVP (a Nakilab product)

A fast, throwaway MVP to test the product concept: a pet tag with a QR code that,
when scanned, shows the pet's info and the owner's contact details, and lets the
finder optionally share their live location with the owner to help reunite them.

**Live (static v1):** https://ringringpaw.nakilab.com  
Parent studio: https://nakilab.com/

## Status: design/capability prototype

This build has **no backend** — it's intentionally client-only (`localStorage`)
so the flow can be tried fast in a single browser. It is not multi-device and
not secure; it exists to validate the UX and interaction model before investing
in real infrastructure (auth, database, live sync).

For a real deployment on this stack you'd add:
- **Hostinger PHP + MySQL** (secrets outside the web root, same pattern as nakilab.com)
  so a scan on one phone reaches the owner's dashboard on another
- Real authentication (hashed passwords or sessions) instead of plaintext localStorage
- Server-stored scan/location events instead of localStorage polling

## Flow

1. **Register** (`register.html`) — first-time setup: owner creates a demo
   account (username/password, stored in localStorage — demo only) and enters
   pet info. This generates a unique pet ID and a QR code linking to
   `pet.html?id=<id>`.
2. **Scan** (`pet.html?id=<id>`) — anyone who scans the QR code sees the pet's
   info and the owner's contact info, and is asked (via the browser's native
   geolocation permission prompt) whether they want to share their location —
   either a one-time snapshot or live sharing for 30 minutes. Nothing is
   collected without that explicit browser permission prompt.
3. **Dashboard** (`dashboard.html`) — the owner logs in and sees scan
   notifications and, if the finder opted in, a live map of their location
   until it expires or the finder stops sharing.

## Run it locally

No build step — just open `index.html` in a browser, or serve the folder:

```bash
python3 -m http.server 8000
```

Then visit `http://localhost:8000`.

Geolocation and camera flows need a secure context; use `localhost` or HTTPS.

## Deploy to Hostinger (static v1)

Branch `PetFinder` is cloned on the server at:

`/home/u233722910/domains/nakilab.com/public_html/ringringpaw`

→ https://ringringpaw.nakilab.com

**Auto-deploy (chosen):** `.github/workflows/deploy-ringringpaw.yml` runs on push to
`PetFinder` and SSHs a `git pull`. Enable it by adding a **separate** deploy key and
repo secret `HOSTINGER_SSH_KEY` — steps in Project `docs/hostinger-deploy.md`
(**Your steps**). This is **not** hPanel Git.

**Manual fallback** (Mac key `~/.ssh/nakilab_hostinger`):

```bash
ssh -i ~/.ssh/nakilab_hostinger -p 65002 u233722910@46.202.198.223 \
  "cd domains/nakilab.com/public_html/ringringpaw && git pull --ff-only"
```

**Server-only `.htaccess`:** the live clone keeps an untracked `.htaccess` (blocks
`/.git`). Do not delete it; do not commit a conflicting one without care.

Files at the branch root that matter for the site: `index.html`, `register.html`,
`pet.html`, `dashboard.html`, `admin.html`, `app.js`, `style.css`, `robots.txt`.

QR links use `location.origin`, so tags generated on the live HTTPS host encode
`https://ringringpaw.nakilab.com/pet.html?id=…` automatically.

**Fallback only:** `./scripts/pack-hostinger.sh` → File Manager zip (not the normal path).
