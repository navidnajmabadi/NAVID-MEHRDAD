# PR 5 — Social boards

Brief §E + Navid: AI removes porn / drugs / fraud / junk with a log per user; more than 5
removals → account paused until Support reviews it.

- **Posts:** as one of your pets, ≤ 250 characters + **1 photo**. The phone shrinks the photo
  first (2000 px), the server re-encodes to ≤ 1600 px / ≤ 350 KB JPEG and drops EXIF (location).
- **Moderation** (`lib/social/moderation.php`): blocklist + junk rules always; OpenAI Moderation
  (text + image, free endpoint) for sexual / illicit (drugs) / violence / hate; a short classifier
  for fraud, spam and junk. Removed content stays in the DB for review; the photo file is deleted.
- **Per-user log** `moderation_log`; author is told why and how many removals are left; on the 6th
  standing removal → suspended, logged out, remember-me cleared, Support ticket + email.
- **Reports:** 7 reasons; 3 different reporters → hidden until an admin restores or removes it.
- **Admin → Moderation:** queue, restore / remove, per-owner log, unsuspend.
- **Likes / dislikes** toggle; **follow** pets; **Following** feed; **pet profile** pages (owner
  nickname only).

SQL: `sql/013_social.sql` (applied automatically on deploy).
