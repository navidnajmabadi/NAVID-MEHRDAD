# Chat: Square setup help

Branch: `cursor/claude-config-hint`

| # | Task | Status | Notes |
|---|---|---|---|
| 32 | Admin → Pricing said the keys go in `config/sipaw.php`, but the server file is `ringringpaw.php` | Done | The box now shows the exact file path the app reads and says to edit that one. Tested the path lookup locally; the page itself not opened (needs the live admin) |
| 33 | Add the Square Application ID, Access token and Location ID to the server config | Pending | Needs you, in the file shown on Admin → Pricing |
| 34 | One real test payment, then check Site health shows a recent cron check-in | Pending | Needs you |
| 35 | Where is the payment in the app? Add a Billing menu: pay, saved card, history, invoices, PDF download, email PDF | Done | Menu, Billing (also Settings). Old Plus links still work (they redirect). Page has: plan status, pay with coupon/coins/tax, saved card and auto-renew, payment history, per-payment Download PDF and Email PDF. Tested locally with a fake Square and a fake mail server: PDF opens, email arrives with the PDF attached, other owners cannot open your invoice |
| 36 | Fill in the invoice seller details | Pending | Needs you: Admin, Pricing, Invoice details (business name, address, HST number, billing email). Without them invoices show only the business name |
| 37 | Plus page: "The amount is too small to charge" at CAD 0.02 (coupon NAVIDNAJM took 99.9% off) | Done | My code refused anything under 1.00. Square's own minimum for Canada is 0.01, so the limit is gone (a 2 cent charge was sent to the fake Square in a test). Not tried with real Square |
| 38 | Where is HST? | Done | Tax was only added for owners whose country is Canada; the test account's country is not Canada. Default is now everyone (`*`). If Admin, Pricing was saved before, set "Owners in these countries pay the tax" to `*`. On tiny amounts 13% rounds to 0 cents so the line is hidden |
| 39 | Invoice: use the SI PAW "by NAKILAB" logo in high quality | Done | The invoice header now draws the logo from the brand file `assets/img/logos/sipaw-wordmark-endorsed-on-white.svg` as sharp vector shapes (SI, PAW, by) plus the NAKILAB mark as a 1006 px image (`nakilab-mark-on-white.jpg`). If the files are missing it falls back to the text name. Checked on a rendered sample invoice, not yet on a real one |
