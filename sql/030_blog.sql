-- SIPAW 030: the blog lives in the database and is written from Admin → Blog (the three starter posts are kept and can be edited).
CREATE TABLE IF NOT EXISTS blog_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(90) NOT NULL,
  title VARCHAR(160) NOT NULL,
  excerpt VARCHAR(300) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  cover VARCHAR(200) NULL DEFAULT NULL,
  cover_alt VARCHAR(160) NULL DEFAULT NULL,
  author VARCHAR(80) NOT NULL DEFAULT 'The SIPAW team',
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL DEFAULT NULL,
  created_by_admin_id INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_slug (slug),
  KEY idx_blog_status (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO blog_posts (slug, title, excerpt, body, status, published_at) VALUES ('meet-sipo', 'Meet SIPO, your pet''s SI companion', 'SIPO remembers breed, habits, and health notes so you never retype the same story in every chat.', 'SIPO is the SI assistant inside SIPAW. Ask about meals, meds, or "is this normal?" and get a clear answer grounded in your pet''s profile.

Profiles are short and useful: breed, allergies, the fact that Milo hates baths. That way Super Intelligence actually has something to work with.

Ready to try it? Register, add your first pet, and say hello to SIPO.', 'published', '2026-10-20 09:00:00');
INSERT IGNORE INTO blog_posts (slug, title, excerpt, body, status, published_at) VALUES ('reminders-that-stick', 'Reminders that stick (without the guilt trip)', 'Vaccines, pills, and meals: SIPO nudges you the day before and on the day. Nudges beat guilt trips.', 'Pet care fails when the calendar lives in your head. SIPAW keeps walks, meds, and check-ins in one place.

SIPO writes them down, then reminds you on time. Your cat can still judge. We just make sure the flea meds happen.', 'published', '2026-10-18 09:00:00');
INSERT IGNORE INTO blog_posts (slug, title, excerpt, body, status, published_at) VALUES ('boards-not-doomscroll', 'Owner boards, not doomscroll', 'Species tips from other owners. Social lite for people with lint rollers, no endless feed.', 'Sometimes you need another beagle owner, not another algorithm. SIPAW boards are short threads by breed and life stage.

Ask, share a tip, move on. SIPO stays available when you want Super Intelligence instead of a thread.', 'published', '2026-10-15 09:00:00');
