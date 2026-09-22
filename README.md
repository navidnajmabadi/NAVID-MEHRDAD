# PetFinder — QR Pet ID MVP

A fast, throwaway MVP to test the product concept: a pet tag with a QR code that,
when scanned, shows the pet's info and the owner's contact details, and lets the
finder optionally share their live location with the owner to help reunite them.

## Status: design/capability prototype

This build has **no backend** — it's intentionally client-only (`localStorage`)
so the flow can be tried fast in a single browser. It is not multi-device and
not secure; it exists to validate the UX and interaction model before investing
in real infrastructure (auth, database, live sync).

For a real deployment you'd add:
- A backend/database (e.g. Supabase) so a scan on one phone reaches the owner's
  dashboard on a different phone in real time
- Real authentication (hashed passwords or magic links) instead of the
  plaintext localStorage demo used here
- A live channel (WebSocket / Supabase Realtime) instead of localStorage polling

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

## Run it

No build step — just open `index.html` in a browser, or serve the folder:

```bash
python3 -m http.server 8000
```

Then visit `http://localhost:8000`.
