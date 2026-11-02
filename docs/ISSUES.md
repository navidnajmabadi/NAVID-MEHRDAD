# SIPAW issue log

Every screenshot or message Navid shared as a problem, in the order they came in. One row per issue.
Status: **Merged** = fixed and in `PetFinder`. **In PR** = fixed, waiting for merge. **Check live** = fixed in code, needs one look on the live site.

| # | What was shared | What was wrong | Root cause | Fix | PR | Status |
|---|---|---|---|---|---|---|
| 1 | Settings, "Your profile" photo row | "Change photo" text small and stuck at the top, "Remove" different | A `<label>` and a `<button>` styled differently | Both buttons share one size and centred text | 64 | Merged |
| 2 | Chat: breed question under Dog / Cat buttons | Wrong buttons, after tapping Dog the next question still showed Dog / Cat | Model attached the previous question's buttons; app showed whatever it sent | Button guard (`lib/sipo/choices.php`): drops answered, off-topic and typed-answer buttons; breed gets Mixed / Not sure; prompt rule | 65 | Merged |
| 3 | "Violet's profile: 5/7 ✅ +2 🪙" | "What moon is it?" | Coin emoji drawn as a grey disc on his device | Chat shows our gold paw coin icon | 65 | Merged |
| 4 | Home overview message with an empty blue pill | Button had no visible text; tour question and photo question in one bubble | Blue text on blue button (`.sipo-msg a` beat `.sipo-link-btn`); photo question appended to any reply | White link-button text; photo question waits if the reply already asks something | 65 | Merged |
| 5 | Walk goal question, "Admin: ask_choices: Give 2 to 6 options." | No answer buttons; admin-only note confusing | Model sent fewer than 2 options; note is a diagnostic for admins | Walk goal always gets 15 / 30 / 45 min / 1 hour buttons | 65 | Merged |
| 6 | "Tap the paperclip" with no paperclip | Cannot attach a photo | Paperclip hidden while the chat had no pet yet, never shown again | Always in the page, shown as soon as the pet exists | 65 | Merged |
| 7 | Sent `violet.jpeg` shows only a file name | "Show image" | Bubble only had a text label | Photos show inside the bubble (this device, files are not kept) | 65 | Merged |
| 8 | "Ask SIPO" button covers text | Cannot move it: hold more than 1 second, then drag | Browser link-dragging cancelled our drag; hold too short | Tap opens, hold 1 s (ring fills) then drag, native drag off | 65 | Merged |
| 9 | Dashboard pet picture, face cut off | Square crop cut Violet's face | Fixed square crop, no control | Round like profile picture, face-friendly crop, manual Change photo / Remove | 65 | Merged |
| 10 | Same picture: "change like Telegram / WhatsApp" | Cannot choose the position | No editor | Round position editor: drag, zoom, save; Adjust button (pet and owner photos) | 66 | Merged |
| 11 | Chat greeting "Good morning" at 12:13 AM | Wrong time of day | Model ignored the day part | Exact local time in the instruction, code fixes wrong greeting words, calm late-night opening | 66 | Merged |
| 12 | "I'll remind you at 17:00 for Violet's walk" then user typed "the next?" | SIPO stopped and waited | Nothing forced a next question | Setup driver (`lib/sipo/driver.php`): reply without a question gets the next missing item; never nags; stop words pause it | 67 | Merged |
| 13 | `sipaw.nakilab.com/vet.php`: "This link has expired" | Scary page after SIPO's "Open Vets" button | SIPO could open the vet share page, which needs a private link | Button removed; page without a link redirects the owner to Health → Share | 67 | Merged |
| 14 | Lab PDF, "error 500" | PDF not read | Most likely the 30 s PHP time limit during a long model call | 240 s limit, 256 MB, JSON errors with the real reason | 68 | Merged |
| 15 | "Paste a screenshot doesn't paste", drag and drop | Cannot paste or drop files | Only the paperclip existed | Paste into the box, drag onto the chat | 68 | Merged |
| 16 | Lab PDF: "MySQL server has gone away @ labs.php:167" | PDF read, then saving failed | Database idled during the long model call and MySQL closed the connection | Reconnect after any model call over 5 s (`rrp_db_keepalive`); still failed live, so the check now also runs right before saving a lab report and after reading the file | 69, 70 | Check live |
| 17 | "Change name of my dog to Violete" → "Done" → "name update didn't show as saved" | Reply said Done but nothing was saved; then the next "yeah" (to "send her blood test image?") got an answer about the name instead | Rename needs a tool call with a confirmed yes; the model only asked in words, then the tool refused and the model still said Done | A plain yes to a question that named the new name now counts; a false "Done" is replaced by the truth; prompt rule: a short yes/no answers only the last question | 69 | Merged |
| 18 | Same screen: "Yes, 09:00 / Another time / No thanks" under "send her blood test image?" | Reminder buttons under an unrelated question | Model attached a reminder offer to the wrong message | Reminder-offer buttons only survive when the text mentions a reminder | 69 | Merged |
| 20 | Chat header "Looking after Violet" and menu "Violet / Showing now" after the rename | Old name kept on screen | Names were drawn once when the page loaded | Every name on the page is tied to the pet record; every reply and the 20 s poll bring the database name and update header, menu, input hint | 70 | In PR |
| 21 | Dashboard "Latest" list ("Your post was removed" x3, "Support: Test") | Unwanted section; leftovers from the hidden Pack board and tests | It was a copy of the notification list, now covered by the bell popup | Section and its query removed from the dashboard | 70 | In PR |
| 22 | `sipaw.nakilab.com/finance.php`: black "Something went wrong" | Expenses page crashes live | Not reproduced locally (mixed currencies, one or many pets, strict SQL modes all render). Suspect: the new per-pet query on the live database | Per-pet bars can no longer take the page down; admins now see the real error on any crash page | 70 | Check live |
| 19 | "Add help to start onboarding manual if needed" | No way to restart the welcome | Only first sign-in showed it | Settings → Help: "Start the welcome again" | 64 | Merged |

## Still to check live after deploy

- Send the Probavet PDF again (items 14 and 16). If it still fails, send the message shown; Admin → Site health records the exact error.
- Real phone: touch dragging of the SIPO button and the photo editor (items 8, 10).
- Settings → "Send me a test" for phone notifications; live config needs VAPID keys and SMTP.

## Approved extras not built yet

Daily health check, weight trend chart on Health, vaccine/drops course reminder, pet health folder with one doctor link.
