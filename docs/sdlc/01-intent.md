# 1. Project definition (intent)

## Product in one line
SIPAW is a caring, always-there companion for pet owners: a chat called **SIPO** that remembers everything about each pet,
reminds the owner before anything is missed, reads lab reports, and helps find a pet that is lost.

## Problem
Busy owners forget vaccines, medicines, walks and check-ups; keep records in photos, PDFs and memory; and panic when a pet is
unwell or lost. Existing pet apps are forms and lists the owner must keep filling in, so they stop opening them.

## Who it is for
| User | Need |
|------|------|
| **Pet owner** (main user; dogs and cats first, up to 3 pets on Plus, 1 on Free) | One place that knows the pet, speaks first, reminds, and keeps health, food, walks and spending |
| **A stranger who finds a pet** | Scan the collar QR, see the pet and reach the owner, optionally share live location |
| **Nearby owners** | Hear about a lost pet within about 25 km and report a sighting |
| **Admins** (Navid, Mehrdad) | See growth, accounts, errors, money and the quality of SIPO's answers |

## Core idea
Not a form. A conversation. SIPO asks one short question at a time, saves the answer, and keeps the app's pages (Health, Routines,
Expenses) filled in behind the scenes. The owner can also use the pages directly.

SIPO's character: a mindful, gentle, Baymax-like health companion. It notices changes, closes the loop on worries, never
diagnoses, never gives drug doses, and escalates real emergencies.

## Goals
1. **Habit:** the owner opens SIPAW several times a day (SIPO speaks first, never between 21:00 and 09:00 local).
2. **Health outcomes:** no missed vaccine, parasite treatment, medicine course or check-up; lab results understood and shared with the vet.
3. **Lost-pet response:** a lost alert reaches nearby owners within minutes and escalates on a schedule.
4. **Revenue:** Plus subscription (CAD, monthly, auto-renew) with rewards that lower the price for owners who care consistently.
5. **Quality and cost control:** SIPO answers well and cheaply, and the team can see and improve both.

## Success measures (what the admin console already shows)
- Daily active owners and growth (Admin → Insights), conversations per owner.
- SIPO reply score and tokens per message (Admin → Accounts, Brain graph, NAKI).
- Routines on track, reminders completed, perfect care weeks (Rewards).
- Plus subscribers, auto-renew count, payments taken (Admin → Pricing).
- Errors open (Admin → Errors), cron check-ins (Admin → Site health).

## Scope
**In:** accounts and onboarding; pets and profiles; SIPO chat with tools; health care (vaccines, parasite control, treatments, labs from
photos/PDF, weight, food); daily life (walks, bathroom, wellness); reminders and notifications (in-app, push, email); QR tags, scan and
finder chat; Lost mode; expenses; paw coins, perfect weeks, referrals; Plus billing (Square), coupons, tax, invoices; weekly email report;
admin console; NAKI quality red team; units and themes.

**Out (for now):** native iOS/Android apps (the code is shaped for them later); SMS; physical tag sales; a public social board
("Pack" is built but switched off); languages other than English; vet-clinic accounts.

## Constraints
- **Hosting:** Hostinger shared hosting. No websockets, no Composer, no long-running workers. Chat polls and Web Push covers the rest. Background work runs from a cron URL every 5 minutes.
- **Team:** two founders and an AI assistant; one person (Navid) approves and merges every change.
- **Privacy:** pet and owner data stays on the server; personal details are removed before text goes to the quality-review model; card numbers never reach the server.
- **Brand:** always "SI", never "Si"; Si Blue, black and white; Friendly and Clean looks only.
- **Safety:** no diagnoses, no drug doses, emergencies are flagged to the owner.

## Main risks
See [05-plan.md](05-plan.md#risks). In short: the AI answer quality and cost, a single shared host, payments only tested against a stand-in so far,
and no automated tests.
