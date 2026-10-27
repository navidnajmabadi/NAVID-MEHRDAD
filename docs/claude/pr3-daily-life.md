# PR 3 — Daily life: bathroom, walks, grooming

Stacked on PR 2 (`cursor/claude-health-food`). Source: brief §C + Navid's answers.
Same layers: Model `lib/care/daily.php` · ViewModel `lib/viewmodels/daily_vm.php` · View `daily.php`, `walk.php`.

## 1. Bathroom (brief §C1)
- Log pee / poop / accident (and stool type: normal, soft, diarrhoea, hard, blood, mucus)
  by telling SIPO or tapping a button on the Daily page.
- **Usual pattern = this pet's own history** (median per day over the previous 14 days — there is
  no universal "normal count"). Today vs usual → signals: much more / much less than usual,
  diarrhoea 2+ days, no poop 3+ days, blood.
- Weather + area: on hot days (≥ 28 °C) SIPO mentions water and heat before assuming illness;
  in deep cold it mentions shorter outings.
- Emergencies: a cat (especially male) straining with little or no pee is an emergency
  (urethral obstruction can kill within ~24 h) → added to the urgent words that lift limits.

## 2. Walks (brief §C2, dogs)
- Log by chat ("40 min, about 3 km") or with the **walk tracker** (`walk.php`): GPS while the app
  is open, distance, time, route on a map; keeps the screen awake. iPhone/Android stop web GPS in
  the background, so the page says "keep SIPAW open".
- Daily goal in minutes (owner/vet sets it); week chart, streak, encouragement.
- **Walk window:** Open-Meteo hourly forecast → best dry, comfortable slot in waking hours today;
  SIPO suggests walking earlier when rain/snow is coming, or not worrying when it isn't.
- Routes are stored (simplified) for Lost Mode in PR 4 (last known areas, favourite paths).

## 3. Grooming, teeth, baths (brief §C3)
Added to the routine catalogue (same reminder + follow-up engine as PR 2, softer wording, fewer
follow-ups): teeth brushing (AVMA: daily), coat brushing, nail trim (dogs ~3–4 weeks; cats
~10–14 days, ASPCA), bath (dogs ~every 3 months, ASPCA; cats rarely), ear check. Owner can set
their own interval with SIPO.

## Database — `sql/011_daily_life.sql`
`pet_bathroom_logs` · `pet_walks` · `pets` (+walk_goal_minutes).
