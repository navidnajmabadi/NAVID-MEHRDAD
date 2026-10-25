# Chat + Health — SQL & cron for Navid

After this code is on Hostinger (`PetFinder` deploy), run these **once** in **phpMyAdmin** on the same database as `sipaw.php` / legacy `ringringpaw.php` → `db.name`.

## 1. Import SQL (order matters)

| File | What it adds | Screens |
|------|----------------|---------|
| `sql/004_chat.sql` | `chat_threads`, `chat_messages` | Chat tab, Message owner on scan, Alerts → Messages |
| `sql/005_health.sql` | `vaccination_records`, `medication_schedules`, `feeding_schedules`, `feeding_logs`, `vet_appointments` | Health → Vaccines / Meds / Food / Vet |

Steps:

1. hPanel → **phpMyAdmin** → select the siPaw database.
2. **Import** → choose `004_chat.sql` → Go.
3. **Import** → choose `005_health.sql` → Go.
4. Or paste each file into the SQL tab and run.

Safe to re-run: both use `CREATE TABLE IF NOT EXISTS`.

If Chat or Health show “tables not installed”, the import did not hit the correct database — check `db.name` in config.

## 2. Cron (same secret as lost escalate)

Add a second Hostinger scheduled task (or append to the existing curl script), every ~10 minutes:

```bash
curl -fsS "https://sipaw.nakilab.com/cron/health-reminders.php?key=YOUR_CRON_SECRET"
```

Uses the same `cron_secret` as `cron/lost-escalate.php`.

Sends:

- Vaccine due within 3 days
- Daily medication at `schedule_time` (stored/compared as **UTC**)
- Vet appointments within 24 hours

## 3. How chat works

1. Finder opens `pet.php?id=…` (scan creates a `scans` row).
2. Finder taps **Message owner** → `finder-chat.php?scan=…`.
3. Guest cookie `rrp_finder` identifies the finder; thread is 1:1 with that scan.
4. Owner sees the thread under **Chat** (empty until a real finder starts one — no demo messages).
5. New finder messages → owner inbox (`type=chat`) + email + Web Push (if VAPID configured).

## 4. Composer / UI

Chat composer matches the siPaw prototype (text field + blue send). CSS overrides the global input styles that previously looked like a broken blue bar / empty box.
