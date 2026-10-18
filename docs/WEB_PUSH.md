# RingRingPaw — Web Push (PWA notifications)

Real notifications on the **installed** home-screen app (Android Chrome + iOS Safari 16.4+).  
Email + in-app inbox stay primary and are never disabled when push fails.

## Why inbox worked but the PWA did not

LOST HELP ME already wrote inbox rows and sent email. The service worker had **no** `push` handler, and `rrp_try_web_push()` was a stub. Installed PWAs only show system notifications via **Web Push** (VAPID + PushManager).

## Navid — one-time VAPID setup

### 1. Generate keys

On any machine with PHP OpenSSL:

```bash
php scripts/generate-vapid.php
```

Or use https://vapidkeys.com (same base64url format).

### 2. Add to Hostinger config

Edit **one** of (same file you already use for DB):

- `/home/u233722910/domains/nakilab.com/config/ringringpaw.php`
- `/home/u233722910/domains/ringringpaw.nakilab.com/config/ringringpaw.php`

```php
'vapid' => [
    'subject' => 'mailto:you@nakilab.com',  // contact mailto: or https: URL
    'public_key' => 'PASTE_PUBLIC',
    'private_key' => 'PASTE_PRIVATE',
],
```

Leave keys empty to skip push (no crash — email/inbox only).

### 3. SQL (optional but recommended)

In phpMyAdmin, on the RingRingPaw database:

```text
sql/003_push_subscriptions.sql
```

Adds `endpoint_hash`, `user_agent`, success/fail tracking. App still works without it (fallback inserts).

### 4. Deploy

Merge/push to **`PetFinder`** → auto `git pull`. No Composer on Hostinger — push crypto is pure PHP in `lib/webpush.php`.

### 5. Confirm

Open https://ringringpaw.nakilab.com/health.php — look for `"vapid_configured": true`.  
Logged-in: Dashboard → **Phone notifications** → Enable.

## Smoke-test checklist

### Android (Chrome)

1. Open https://ringringpaw.nakilab.com in Chrome → log in.  
2. Install (banner or menu → Install app / Add to Home Screen).  
3. Open from home screen → Dashboard → **Enable notifications** → Allow.  
4. Badge should show **On**.  
5. From another account within ~25 km, tap **LOST HELP ME** (or scan that owner’s QR).  
6. Android should show a system notification; tap opens lost-alert / dashboard.  
7. Escalation: wait for cron (+1h / +3h / +8h) or trigger cron URL — push again.

### iOS (Safari 16.4+)

1. **Must** use Share → **Add to Home Screen** (notifications do **not** work in Safari tab).  
2. Open the icon from the home screen (standalone).  
3. Log in → Enable notifications → Allow.  
4. If blocked: Settings → Notifications → Ring Ring Paw → Allow.  
5. Same LOST / scan test as Android.  
6. iOS &lt; 16.4: push unsupported — email + inbox only (UI explains).

### Permission denied

- **Android:** site settings → Notifications → Allow → tap Enable again.  
- **iOS:** Settings → Notifications → Ring Ring Paw.  
- UX copy lives on the dashboard push banner.

### VAPID empty

- Server does not crash.  
- Subscriptions may still be saved for later.  
- Dashboard shows admin hint; `api/push-vapid.php` returns `configured: false`.

## What sends push

| Event | Opens |
|-------|--------|
| LOST HELP ME t0 + cron escalations | `lost-alert.php?id=…` |
| Mark Found (helpers) | `dashboard.php` |
| QR scan (owner) | `dashboard.php` |

Implementation: `lib/webpush.php` + `rrp_try_web_push()` in `lib/notify.php`. Dead endpoints (HTTP 410/404) are deleted automatically.
