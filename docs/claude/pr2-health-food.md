# PR 2 — Health, vet treatment, labs, food

Stacked on PR 1 (`cursor/claude-sipo-brain`). Source: brief §3 A–B + Navid's answers (2026-10-28).
Same layers as PR 1: Model `lib/care/*` · ViewModel `lib/viewmodels/health_vm.php` · View pages + JS.
SIPO drives everything from chat; every feature also has a manual page.

## 1. Care routines by region

- Region from (1) owner country (map/geocode) → (2) Cloudflare `CF-IPCountry` → (3) the
  browser timezone's country (`DateTimeZone::getLocation`). No paid IP API.
- Catalogue `lib/care/catalog.php`: per species × region (North America / Europe & UK /
  global), each row has the source guideline and "your vet sets the exact schedule".
  - North America: AAHA 2022 (+2024 update: leptospirosis core for dogs), AAHA/AAFP 2020
    (cats), CAPC (year-round heartworm, flea/tick, monthly intestinal control).
  - Europe/UK + global: WSAVA 2024 (core boosters no more often than every 3 years;
    non-core about yearly), ESCCAP (worming ≥4×/year when risk is unclear; indoor cats 1–2×).
  - Rabies: core where endemic; interval follows local law / vaccine label (1 or 3 years).
- SIPO asks the last date of each item → stores it → next due = last + interval →
  reminder. Unknown date → SIPO suggests asking the vet and sets a "check with vet" reminder.

## 2. Follow-ups that get an answer (brief §A1)

`care_reminders` gains `followup_stage`, `outcome`. Cron + SIPO:
day before → "Tomorrow is Milo's rabies booster" · due day → "Today…" ·
next day, no answer → "Did you manage to go? Want me to find a vet?" · repeats every
2 days up to 3 times. Owner answers in chat → `complete_reminder` tool records the outcome
(done / rescheduled / skipped) and books the next cycle. SIPO-created reminders now push.

## 3. Pets under vet treatment (Navid's addition)

`vet_treatments` (condition, vet/clinic, start, planned end, status, notes),
medicines linked to a treatment (`medication_schedules.treatment_id`), recheck visits
(`vet_appointments.treatment_id`), daily progress log `treatment_logs`
(better / same / worse + note). SIPO asks once a day how it's going and whether
the medicine was given. **SIPO never invents or changes a dose** — it stores the vet's
wording exactly and says "as your vet prescribed".

## 4. Lab reports (brief §A2)

Upload in chat or on the Health page: PDF or image, max 10 MB. Vision model extracts
analytes + units + reference ranges → `health_examinations` / `health_lab_results`.
**The file is deleted right after extraction** (Navid). Flags low/normal/high; trend
chart per analyte across reports; default card "doing well"; **only abnormal values blink**
(respects reduced-motion). SIPO explains in plain words and suggests a vet when needed —
no diagnosis.

## 5. Food (brief §B)

- Daily calories: RER = 70 × kg^0.75 × life-stage factor (WSAVA nutrition toolkit /
  Merck Veterinary Manual), or the vet's number when known (wins).
- Food label photo → product with kcal/kg (or kcal/cup), ingredient warnings
  (xylitol, grapes/raisins, onion/garlic, chocolate, high sugar), package size →
  grams per day for this pet.
- Meals: schedule + reminder; after a meal SIPO asks what, how much, liked? →
  `feeding_logs` gains grams, food, liked, note → preference chart; today's calories vs need.
- Supply: package size ÷ daily grams → run-out date; reminder 3 days before; if it runs
  out, reminder only (no shop links — Navid).
- Vitamins/supplements only when the owner says the vet recommended them.

## 6. Health dashboard + vet share link

`health.php` (per current pet): status card, treatments, meds, routines, lab trends,
weight, calories today, food supply. **Share with vet**: private link
`vet.php?t=…` (read-only, noindex, expires in 30 days, revocable).

## 7. Shared uploads (chat attach)

`api/si/upload.php`: one vision call classifies the file — lab report, food label,
prescription / vet note, pet photo, other — and routes it. Pet photo becomes the profile
picture (needed for Lost posters); every other file is deleted after its data is saved.

## Database — `sql/010_health_food.sql`
`care_reminders` (+followup_stage, outcome, outcome_at, kind, routine_key) ·
`vet_treatments` · `treatment_logs` · `medication_schedules` (+treatment_id, kind) ·
`vet_appointments` (+treatment_id) · `pet_foods` · `feeding_logs` (+grams, kcal, food_id, liked, note, logged_at) ·
`pets` (+vet_kcal_per_day, activity) · `health_share_links` · `health_examinations` (+source).

## Not in PR 2
Walks / bathroom / grooming (PR 3), Lost playbook (PR 4), coins for uploads (PR 6),
NAKI evaluation of answers (PR 7).
