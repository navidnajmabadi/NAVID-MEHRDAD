# Chat: morning walk nudge, simple receipt, no tax, currency, iOS app

Branch: `cursor/claude-simple-receipt`

| # | Task | Status | Notes |
|---|------|--------|-------|
| 71 | Walk reminder in the morning, not the evening | Done | SIPO suggests a walk only before noon (09:00 to 11:59 local) and its text now says "this morning". Every owner gets a morning slot (09 to 11) in SIPO's schedule, even if they usually open the app in the afternoon, so the walk message can go out in the morning. After noon it never sends the walk message. Tested: owners with local time 9 and 10 got "walk"; 15, 17 and 21 did not |
| 72 | No tax anymore | Done | The price is never taxed, whatever an old setting says (tested with a 13% setting still in the database: tax 0). The tax fields were removed from Admin → Pricing |
| 73 | Simple receipt instead of an invoice | Done | The PDF, the email, the Billing page and the menu now say "receipt". The receipt shows who it is from (name, email), who paid, the lines and discounts, the total paid and how it was paid. No tax lines, no tax number, no address. Checked by drawing the PDF |
| 74 | Customers from any country | Done (with a limit) | Square only takes ONE currency (your Square account's, CAD). So everybody is charged in CAD and their bank converts it. New: before paying, the Plus page shows an estimate in the customer's own currency from the daily European Central Bank rates (about 30 currencies, cached 12 h), plus a line explaining it. Not possible with Square: charging in the customer's own currency |
| 75 | Bug found: saving Admin → Pricing set the discount limit to 100% | Done | A loop variable overwrote `$max`, so the "most the coins and care weeks can take off" was saved as 100 whatever you typed. Fixed. Check Admin → Pricing: the limit shown there may be 100 now, set it again (it was 50 by default) |
| 76 | Payments stay on the website, with Apple Pay | Done in code, needs one setup step from you | An Apple Pay button on the Plus page (shows only where Safari offers it). Same price, no tax, receipt as before. Server tested with a stand-in for Square: Apple Pay token that can be kept on file (auto-renew works), one that cannot (this month is paid, auto-renew off, the customer is told), and a failing card (nothing charged). **Not tested:** the real Apple Pay sheet and real Square |
| 77 | Domain check for Apple Pay | Pending | Needs you: Square Developer Console, Apple Pay, add `sipaw.nakilab.com`, upload the file Square gives you to `.well-known/` (steps in `docs/SQUARE.md`) |
| 78 | iPhone app | Pending | Decided: no payment inside the app, people pay on the website. Waiting for you to say native SwiftUI or web wrapper |
