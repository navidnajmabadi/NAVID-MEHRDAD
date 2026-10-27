# PR 1 — SIPO brain, one chat per pet, login, PWA

Source of truth: `claude-sipaw-full-product-brief.md` + Navid's answers (2026-10-28).
When old code and the brief disagree, the brief wins.

## Intent

SIPAW should feel like one caring chat ("حواس جمع" for pets). SIPO speaks first,
remembers everything about each pet, knows the time / weather / area, and keeps
the owner coming back 3–4× a day — without waking them between 21:00 and 09:00.

## Architecture (MVVM on PHP)

PHP on Hostinger renders pages; new code is split in three layers so the same
logic can feed the web app now and a native app later.

| Layer | Where | Rule |
|-------|-------|------|
| **Model** | `lib/*.php`, `lib/sipo/*.php` | DB + domain logic only. No HTML, no `$_GET`. |
| **ViewModel** | `lib/viewmodels/*.php` | Builds one plain array per screen from models. Same array is rendered by PHP *and* returned as JSON by `api/`. |
| **View** | `*.php` pages + `assets/js/*.js` | Renders the ViewModel. No SQL. |

Old pages move to this shape when touched; no big-bang rewrite.

**Speed / scale:** every hot query indexed; SIPO sends a compact context
(pet card + memory facts + rolling summary + last 24 turns) instead of the whole
history, so tokens stay flat as chats grow. **Realtime:** shared hosting can't
hold websockets, so the chat polls every 20 s while visible and Web Push covers
the rest; the API is shaped so SSE/websockets can replace polling later.

## Scope

1. **Login clean-up**
   - Root cause of "login keeps dropping": session cookie dies when the PWA
     closes and PHP garbage-collects sessions after 24 min. Fix: 60-day
     remember-me token (selector + hashed validator, rotated on use, cleared on
     logout / password reset).
   - Throttle: 5 wrong tries per email+IP in 15 min → wait.
   - Open-redirect fix on `next=` (`//evil.com/x.php` was accepted).
   - Signup asks only: what SIPO should call you, email, password, terms.
     No legal name / phone / address (brief: no human PII). Area comes later
     from the map.
   - `secure` cookie also honours `X-Forwarded-Proto` (Hostinger proxy).
2. **SIPO brain** (`lib/sipo/`)
   - Layered system prompt: core (versioned in `si_prompt_versions`, rollback) →
     scope lock → safety → module playbooks → pet card → memory → live context.
   - Long memory: all messages kept; rolling summary per thread; `sipo_memory`
     facts (weight, food, vet, habits…) written by tools.
   - Tools (OpenAI function calling): save pet facts, update profile, set
     reminder, local weather (Open-Meteo), nearby vets (OpenStreetMap), open
     support ticket, create pet profile.
   - Cool Down: >100K tokens in a rolling 2 h → pause 2 h. Any urgency
     ("I need help", sick, bleeding…) lifts all limits.
   - Name sanity check and kind tone are in the prompt.
   - Empty OpenAI key → friendly fallback, never a 500.
3. **Proactive SIPO** — `cron/sipo-nudges.php` (every 15 min): learns the owner's
   active hours, max 4 nudges/day, ≥3 h apart, never 21:00–09:00 local; sends
   chat message + Web Push. Opening the chat on a new day → SIPO greets first.
4. **One chat per pet** — "Add pet" opens a new SIPO thread; SIPO onboards in that
   same thread and creates the pet; thread lives forever. Old
   `pet-register.php` redirects into it.
5. **Instagram-style pet switcher** — pet avatar in the header; tap → sheet with
   all pets + "Add pet". Current pet drives chat, dashboard and forms.
6. **Mascot states** in chat: idle / listening / thinking / writing / happy /
   alert (Ice Blue).
7. **Brief alignment** — Lost pings 0 / +1 h / +5 h, 20 km (not shown to users);
   leaderboard hidden behind a flag; Free plan gets full SIPO while in test mode
   (limits live in config).
8. **Add to Home Screen** — Android/Chrome install prompt; iOS sheet with
   "Add SIPAW to your Home Screen so SIPO can reach you."
9. **Layouts** — phone (tab bar), tablet (chat + pet panel), desktop (side nav +
   chat + pet panel).

**Not in PR 1:** labs, food photos, medical conditions under vet care (PR 2),
walks/bathroom (PR 3), Lost broadcast upgrade (PR 4), social photos/moderation
(PR 5), coins/referrals/Square (PR 6), NAKI + admin dashboards (PR 7).

## Database — `sql/009_sipo_brain.sql`

- `auth_remember_tokens`, `auth_login_attempts`
- `owners`: `nickname`, `timezone`, `sipo_cooldown_until`; `phone`/`address` nullable
- `pets`: `sex`, `birth_date`, `weight_kg`, `neutered`
- `chat_threads`: `pet_id` nullable (onboarding), `summary`, `summary_upto_id`, `onboarding`
- `chat_messages`: `meta_json` (tool calls, mascot state)
- `sipo_memory`, `sipo_nudges`, `owner_activity_hours`

## Test plan

- PHP lint on every file; migrations 001→009 on a clean MariaDB.
- Login: signup, login, remember-me after session wipe, throttle, bad `next`.
- SIPO without key (fallback) and with a stubbed model (tool calls, memory,
  cooldown, emergency bypass).
- Nudge cron: quiet hours, daily cap, spacing.
- Screenshots: phone 375, tablet 768, desktop 1280.
