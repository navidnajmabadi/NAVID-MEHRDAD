# 5. Plan: roadmap, backlog, decisions, risks

## Where we are (2026-11-07)
Live and in daily use by the founders: accounts, SIPO, health, daily life, reminders, Lost mode, rewards, admin console, NAKI, error log.
Just built, **waiting for the first real payment**: Square card payments, auto-renew, coupons, tax, invoices (`docs/tasks/square-setup.md`).

## Next, in order
| # | Item | Why | Owner |
|---|------|-----|-------|
| 1 | **Make the repository private**, after the server has a read-only deploy key (see operations) | Source, SIPO prompts and server details are public today | Navid |
| 2 | Put the Square Application ID, Access token and Location ID in the server config; make one real 2 cent payment; check the invoice PDF and email | Payments are only tested against a stand-in | Navid |
| 3 | Fill Admin → Pricing → Invoice details (name, address, HST number, billing email) | Invoices need the seller's details | Navid |
| 4 | Remove the old per-job cron URLs in Hostinger; confirm "Last cron check-in" is recent | Auto-renew and nudges depend on the one job | Navid |
| 5 | Watch the CAD 30,000 small-supplier limit (GST/HST registration is required once taxable sales pass it, over four consecutive quarters, to confirm with the accountant). If you register: set the rate and HST number in Admin → Pricing | No HST is charged today | Navid |
| 6 | Verify on a real iPhone: chat position after the keyboard closes, camera photo upload, home-screen icon | Fixed from the code and a desktop browser only | Navid, then assistant |
| 7 | Add the site to Google Search Console and Bing Webmaster Tools and submit `sitemap.xml`; write the first real blog posts in Admin → Blog | Needed for the blog to be found | Navid |
| 8 | Confirm Hostinger backups (files and database) and write the restore steps into `04-operations.md` | Not documented | Navid |

## Backlog (approved ideas, not built)
- Daily health check card, weight trend chart on Health, vaccine/drops course reminder, one private doctor link for the pet's whole health folder.
- PDF reading cost: limit pages or ask for single-page photos (the largest remaining token cost).
- NAKI "approve before applying" button instead of auto-apply with rollback.
- Local TF-IDF grouping vs paid embeddings for the brain graph.
- Turn limits (cool-down, daily caps, Plus-only features) on when the test period ends (`sipo.limits_enabled`).
- Bring the Pack board back (`RRP_SOCIAL_ENABLED`) if wanted.
- Native iOS/Android apps on the same ViewModel/JSON layer.

## Engineering debt (small, real)
| Item | Detail |
|------|--------|
| **No automated tests** | Checks are manual plus the Admin test lab. Start with a smoke script: lint every PHP file, run migrations on a throwaway database, hit key pages and APIs, price maths for the quote, PDF builds |
| README is out of date | It still says pets max 5 and lists old install steps; the code says Free 1 pet, Plus 3. Refresh it from this pack |
| Super-owner emails in code | Defaults are written in `lib/plan.php`; move them to the config file |
| Old per-job cron files | Keep only `tick.php` once the hosting cron is switched |
| Two config file names | `sipaw.php` and the legacy `ringringpaw.php`; pick one and retire the other |
| `rrp_` prefix | Historic name (RingRingPaw); harmless, rename only if it ever matters |
| Large files | `lib/payments/billing.php`, `lib/sipo/engine.php`, `lib/naki/naki.php` are big; split by area when next touched |

## Decisions already made
| Decision | Why |
|----------|-----|
| PHP + MariaDB on shared hosting, no framework | Cheap, simple to run, fits the team |
| One chat per pet, SIPO speaks first, quiet hours 21:00 to 09:00 | Habit without waking people |
| Models never act without a tool; guards fix false "Done" | Trust: the app must never claim something it did not do |
| Token diet: route tools and prompt blocks per message | Roughly a third to 40% less per call (estimated, to confirm with real numbers) |
| NAKI auto-applies prompt edits only if the exam does not get worse, with rollback | Improve without a person in the loop, safely |
| Coins have no cash price by default (1 cent each, set in Admin) | Reward without making coins a currency |
| Discounts: coupon, then perfect weeks, then coins, capped at 50% in total; coins spent only on a successful payment | Predictable price, no double discount |
| Square live only, card typed in Square's form, our own scheduler for renewals | No card data on our server; price can change each month |
| No tax charged now (small supplier, under CAD 30,000); the admin can switch tax on later | Simplest and correct while under the limit; confirm with the accountant |
| 7-day free trial per account (admin can change each one); after it, Health is read-only and SIPO is locked until Plus | Gives a real try, then a clear reason to subscribe; Lost-pet alerts stay open because a lost pet must never wait for a payment |
| Cancel subscription the standard way (access until the end of the paid period, no refund of the current period, easy to resume) | Fair and expected by customers and card networks |
| Friendly (default) and Clean looks, saved on the account; Studio removed | Consistent look across devices |
| Units by country (lb/miles/°F in the US) with metric storage | Local feel, one source of truth |
| One PR per working batch, merged by Navid only; per-chat task table | Control and traceability |

## Risks
| Risk | Effect | Mitigation |
|------|--------|------------|
| AI cost grows faster than revenue | Margin | Token diet, tokens per message in Admin, limits ready to switch on |
| AI says something unsafe or wrong | Harm, trust | Safety rules in the prompt, emergency flag, NAKI exam, no doses or diagnoses |
| Single shared host | Downtime | Site health, cron check-ins, backups to confirm |
| Payments untested live | Failed or wrong charges | One real small payment first; failed payments charge nothing and spend no coins |
| Public repository | Source and server details exposed | Go private (item 1); history was scanned and holds no secrets |
| Cron stops | No reminders, nudges, renewals | Last check-in on Site health; single job |
| No automated tests | Regressions reach users | Smoke script (debt), local checks before every PR |
| One approver | Bottleneck | Clear PR tables so review is quick |

## Open questions
1. When will taxable sales near CAD 30,000, and who handles HST registration?
2. Annual plan or only monthly?
3. Should NAKI wait for Navid's approval before changing SIPO's prompt?
4. When does the test period end (limits on, Plus-only features)?
5. Which languages after English?
