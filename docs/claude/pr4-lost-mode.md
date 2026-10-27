# PR 4 — Lost Mode: search plan, confirmed broadcast, helpers, Found

Brief §D + Navid's answers (20 km, 0 / +1 h / +5 h, never show the distance number).

- **Confirm once:** owner types the pet's name before neighbours are alerted; optional
  "I'm where they were last seen" GPS pin + note.
- **Search plan** (`lib/lost/playbook.php`): steps by time (right now / first hour / tonight or today /
  next days), personal to species, indoor/outdoor, age, shy temperament (saved facts), weather now,
  and the dog's most-walked spots from GPS walks (OpenStreetMap names).
  Evidence: cats — 75% found within 500 m, indoor cats much closer, physical search helps
  (Huang et al. 2018); dogs — most found within ~1.5 km.
- **Helpers:** "How you can help" guide, approximate map (rounded ~100 m, never the home),
  "I saw this pet" with optional rounded location → owner gets push + chat message.
- **Found is forced:** alerts no longer expire 5 h after the start (bug); SIPO asks daily "Is X home?"
  until Found; red banner in the pet's chat; Lost Mode is top priority in SIPO's prompt.
- **Bug fixes:** Lost Mode used only the typed address (signup has none since PR 1) → it failed for
  new users and map-only neighbours were never alerted. Now home area from the map is used first.

SQL: `sql/012_lost_playbook.sql`.
