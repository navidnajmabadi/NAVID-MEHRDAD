# 2. Features (specification)

Status: **Live** = in the product and in use. **Built, off** = code exists but is switched off. **Needs keys** = built, waits for a key or setting.
Main files are in `code font`. "Pages" are PHP files in the repo root; "API" files are in `api/`.

## A. Accounts and onboarding
| Feature | How it works | Main files |
|---------|--------------|------------|
| Sign up, log in, log out | Email + password; asks only what SIPO should call you, email, password, terms. Google sign-in when keys are set | `signup.php`, `login.php`, `lib/auth.php`, `lib/google_sso.php`, `oauth/` |
| Stay logged in | 60-day "keep me logged in" token (hashed, rotated). Sessions live in their own folder; form token also lives in a cookie so open pages survive | `lib/auth.php`, `lib/bootstrap.php` |
| Safer login | 5 wrong tries per email+IP in 15 minutes, open-redirect fix, password reset by email link | `lib/auth.php`, `lib/password_reset.php` |
| Welcome flow | SIPO says hello, asks name and country, then the chat adds the pet; photo and profile steps are plain code, not model whim | `welcome.php`, `lib/onboarding.php` |
| Settings | Profile, appearance (Friendly default, Clean), time zone, weekly email, password, delete account | `settings.php`, `account*.php` |
| Units by country | US: lb, miles, °F, mph. Everyone else: kg, km, °C, km/h. Stored data is always kg and km | `lib/units.php` |

## B. SIPO, the companion (chat)
| Feature | How it works | Main files |
|---------|--------------|------------|
| One chat per pet | A thread per pet, forever; onboarding thread becomes the pet's | `si-chat.php`, `lib/sipo/engine.php` |
| Speaks first | Greeting on first open each day; up to 4 nudges a day at the hours the owner usually opens the app, never 21:00 to 09:00 local | `lib/sipo/nudges.php` |
| Tools (it does things) | Create/update pet, save facts, set reminders, record care done, treatments, food, meals, bathroom, walks, routine plan, weather, find vets, open support ticket, flag emergency | `lib/sipo/tools.php`, `lib/sipo/app_tools.php` |
| Guided setup | A driver asks the next missing question (pet info, routines, health, money) and shows a progress bar | `lib/sipo/driver.php`, `lib/sipo/setup.php` |
| Quick-answer buttons | Buttons guarded so they match the question and never repeat an answered one | `lib/sipo/choices.php` |
| Files in chat | Photos and PDFs (several at once): lab reports, vaccine cards, food labels, pet photo; paste, drag and drop, camera. Photos are shrunk before upload | `api/si/upload.php`, `lib/care/uploads.php`, `assets/js/upload-many.js` |
| Memory | Facts per pet plus a rolling summary; recent turns only, so cost stays flat | `lib/sipo/memory.php` |
| Token diet | Only the tools and prompt blocks a message needs, low reasoning effort, 3 tool rounds max | `lib/sipo/router.php`, `docs/TOKENS.md` |
| Safety | No diagnoses, no doses, emergency lifts every limit and alerts the owner | `lib/sipo/prompt.php` |
| Waiting bubble | Says what SIPO is doing ("Setting that up as a reminder…") and changes if it takes long | `assets/js/sipo-chat.js` |
| Honest numbers | Weight, distance and weather are spoken in the owner's units | `lib/units.php`, `lib/sipo/prompt.php` |

## C. Health, daily life and money
| Feature | Details | Main files |
|---------|---------|------------|
| Health dashboard | Status, routines (vaccines, parasites, nails, teeth…), weight chart, treatments, labs with ranges and "ask SIPO about this result", exams, vet appointments | `care.php`, `health-care.php`, `lib/views/health_dashboard.php`, `lib/care/` |
| Routines | Closed-by-default list with one line of help, due dates, done/snooze; daily routines show under "Every day" | `routines.php`, `lib/routines.php`, `lib/care/routines.php` |
| Labs and documents | Read from photo or PDF, values saved with flags, same report counted once | `lib/care/labs.php`, `lib/care/uploads.php` |
| Food | Calories estimate, food label reading, meal times, supply running low | `lib/care/food.php`, `lib/food_photo.php` |
| Daily life | Walk tracker with GPS map (Leaflet), manual walks, bathroom log, wellness check-ins, patterns and warnings | `daily.php`, `walk.php`, `lib/care/daily.php`, `lib/wellness.php` |
| Weight | Dated weights, trend in dashboard, weekly email and a "weight changed" alert | `lib/report.php`, `lib/care/watch.php` |
| Expenses | Per pet and per month, categories, usual vs this month | `finance.php`, `lib/finance.php` |
| Weekly health report | Sunday email per owner with walks, weight bars, due items, lab flags, spending, SIPO's note | `lib/report.php`, `lib/email.php` |

## D. Notifications
| Channel | Status | Main files |
|---------|--------|------------|
| In-app bell and inbox | Live (unread count, clear read) | `lib/notify.php`, `assets/js/bell.js`, `alerts.php` |
| Web Push on installed PWA | Needs keys (VAPID), pure PHP, no library | `lib/webpush.php`, `assets/js/push.js`, `docs/WEB_PUSH.md` |
| Email | Live: SMTP (Hostinger) or PHP `mail()`; designed templates; can attach files | `lib/mailer.php`, `lib/email.php` |
| Reminders | Owner's own reminders ignore quiet hours; follow-ups until done | `lib/care/routines.php`, `cron/` |

## E. QR tags, scan and Lost mode
| Feature | Details | Main files |
|---------|---------|------------|
| Collar QR | Per-pet QR (PNG/PDF) and lost poster download | `lib/pets.php`, `assets/js/poster.js` |
| Public scan | A finder sees the pet and the owner's contact, can share location | `tag.php`, `scan` API, `lib/scans.php` |
| Finder chat | Per-scan chat between finder and owner, with push | `finder-chat.php`, `lib/chat.php` |
| LOST HELP ME | Alert centered on the last-seen spot, nearby owners notified within about 25 km, escalation at start, +1 h, +3 h, +8 h, sightings, "Found" ends it with a rescued page | `lost.php`, `lib/lost.php`, `lib/lost/playbook.php`, `cron/lost-escalate.php` |
| Search playbook | Advice by species (for example most lost dogs are found within about 1.5 km) | `lib/lost/playbook.php` |

## F. Rewards and growth
| Feature | Details | Main files |
|---------|---------|------------|
| Paw coins | Earned for caring actions with daily caps (for example vaccine 10, lab report 15, walk 3, meal 1); spent only on Plus discounts; balance in every header | `lib/rewards/coins.php`, `rewards.php` |
| Perfect weeks | Every reminder done and 4+ active days in a Monday-Sunday week = 5% off the next payment, up to 20% | `lib/rewards/program.php` |
| Referrals | Share link; every 3 paying friends = 1 free month, stacking | `lib/rewards/program.php` |
| Pack (social board) | Posts, comments, weekly boards | **Built, off** (`RRP_SOCIAL_ENABLED`) `forum*.php`, `lib/forum.php`, `lib/social/` |

## G. Billing (Plus)
| Feature | Details | Main files |
|---------|---------|------------|
| Plan | Free = 1 pet; Plus = up to 3 pets. Limits are off for everyone during the test period (`sipo.limits_enabled`) | `lib/plan.php` |
| Billing page | Menu, Billing: pay, saved card, auto-renew, "use my coins", payment history, invoices | `billing.php`, `assets/js/plus-checkout.js` |
| Price build-up | List price, then coupon, perfect weeks, paw coins (value and cap set by admin), then tax (HST 13%, countries set by admin) | `lib/payments/billing.php` |
| Card payment | Square Web Payments SDK form; card saved on file; real charge in CAD. **Needs keys** | `api/billing/subscribe.php` |
| Auto-renew | Cron charges the saved card shortly before Plus ends; 3 daily tries; coins spent only on success; referral free month used first | `lib/payments/billing.php` (`rrp_billing_auto_tick`) |
| Coupons | Percent, fixed amount or free month; typed on the Billing page | `admin/coupons.php`, `lib/coupons.php` |
| Invoices | Number `SIPAW-year-000123`; PDF with logo, seller, discounts, tax line; download or email | `invoice.php`, `lib/invoice.php`, `lib/pdf.php` |
| Old payment links | Still handled: signed webhook marks the order paid | `api/square/webhook.php` |

## H. Admin console (`/admin`)
Overview (SIPO health), Insights and Map, Accounts (tokens per message, filters), Tickets, **Pricing** (price, coin value, cap, tax, invoice seller details,
Square connection check), Coupons, Emails (preview templates), **NAKI brain**, **Brain graph**, **Test lab**, SIPO check, **Site health**, **Errors**, Logs, Moderation (when Pack is on).

## I. Quality and operations
| Feature | Details | Main files |
|---------|---------|------------|
| NAKI red team | Scores real replies (personal data removed), runs a fixed exam, proposes prompt edits every 3 days, applies only if the exam passes and rolls back if live quality drops | `lib/naki/naki.php`, `admin/naki.php` |
| Brain graph | Requests grouped by topic (TF-IDF nearest neighbours); clickable nodes with score and how to improve | `lib/naki/graph.php`, `admin/graph.php` |
| Test lab | Manual tests, AI-made bulk tests, scored runs | `lib/naki/lab.php`, `admin/lab.php` |
| Error log | Crashes, warnings, browser errors and failed requests with a 6-letter code, Copy and Fixed buttons; crash pages show the code | `lib/ops/health.php`, `admin/errors.php`, `assets/js/error-report.js` |
| Site health | Uptime from cron check-ins, database speed, disk, biggest tables | `admin/site.php` |
| PWA | Installable app, home-screen icon is SIPO's face, offline page, service worker caches static files only | `manifest.webmanifest`, `sw.js` |
| Looks | Friendly (default) and Clean; saved on the account | `assets/css/skins.css`, `lib/layout.php` |
