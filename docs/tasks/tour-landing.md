# Chat: guided tour and new landing page

Branch: `cursor/claude-blog-seo` (same PR as the blog work, PR 80)

| # | Task | Status | Notes |
|---|------|--------|-------|
| 59 | More onboarding steps | Done | New tour with 10 steps: intro, Health (sample dashboard demo and lab results upload), Vet link (the same sample dashboard shown to a vet), SIPO chat (lists how to care and the routines), Routines (open a routine, press Done), Lost pet (alert, nearby owners, to-do list), Money, Walks (map with a sample path and the distance and time running), Coins and referral, finish. After the welcome, first sign-in opens the tour, then SIPO adds the pet |
| 60 | Demos use the sample dashboard | Done | Health and Vet link draw the real Health dashboard code with sample data about Milo. Distance follows the owner's unit (km or miles) |
| 61 | Better wording for "blood test" | Done | "Lab results" everywhere; the upload also accepts urine and stool reports |
| 62 | Skip, and start again from the Menu | Done | Skip on every step. Menu → Take the tour, and Settings → Take the tour. Walks step is left out when every pet is not a dog |
| 63 | Starting the tour must NOT clear memory | Done | The tour only reads. Checked: row counts of every table identical before and after all 10 steps (pets, chats, owners, settings). The old welcome-again link, which went through the first-run flow, is gone from Settings |
| 64 | Admin "Clear SIPO memory" is dangerous | Done | Now "Delete this account's data", hidden in a closed box in the admin part of the Menu, and needs the word DELETE typed (checked on the server too; "delete" in lower case does nothing). Also fixed: this admin tool page crashed with an error after doing its work (missing include) |
| 65 | New landing page theme | Done | Light, built on the SIPAW element tile (square frame, number, symbol, name). Hero is a periodic table of the 8 parts of the system with the big SI PAW tile; Milo, SIPO and Luna stand in front |
| 66 | Body explains the other parts | Done | One chapter per part (same text and demo as the tour), "How it works", "Meet the pack", blog and call to action |
| 67 | Pet images in SIPO style | Done | Milo (beagle) and Luna (tabby cat) drawn as soft plush with stitched seam, blush cheeks and a Si Blue collar, `assets/img/pets/` |
| 68 | Tour for expired trials | Done | Allowed on the access list, so a person whose trial ended can still read what SIPAW does |
| 69 | Check on a real iPhone | Pending | Needs you: the demos, the lab upload and the landing page were checked in a desktop browser at phone width only |
| 70 | Add a picture of the real Milo/Luna? | Pending | Optional: the plush pets are illustrations, not your pets |
