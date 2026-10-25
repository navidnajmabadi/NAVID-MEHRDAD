-- =============================================================================
-- siPaw v1 product schema (006) — ADDITIVE only
-- =============================================================================
-- NAVID — phpMyAdmin import (Hostinger):
--   1. Open phpMyAdmin → select database: u233722910_sipaw
--   2. Import this file (or SQL tab → paste → Go)
--   3. Prerequisites: 001–005 already applied (sipaw-full-schema.sql / live DB)
--   4. Safe to re-run: CREATE TABLE IF NOT EXISTS
--   5. ALTERs may warn "Duplicate column" / "Duplicate key" — ignore and continue
--
-- Product locks (v1):
--   - English only (locale default 'en'; no i18n tables)
--   - Weekly leaderboards: Canada TZ America/Toronto, Mon 00:00 – Sun 23:59:59
--   - No physical tags (Finder = broadcast ~25 km + PDF; digital only)
--   - Free = 1 pet / Plus = 3 pets — enforced in app; schema supports multi-pet
--   - Pet accounting = real-world $ only (no Sip / XP foreign keys by design)
--   - SI chat reuses chat_threads / chat_messages via thread_kind (no duplicate tables)
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- -----------------------------------------------------------------------------
-- ALTER: owners — plan, Sip balance, promo, settings/export markers
-- Ignore "Duplicate column name" if already applied.
-- -----------------------------------------------------------------------------
ALTER TABLE owners
  ADD COLUMN username VARCHAR(40) NULL DEFAULT NULL AFTER name,
  ADD COLUMN plan ENUM('free', 'plus') NOT NULL DEFAULT 'free' AFTER status,
  ADD COLUMN plan_expires_at TIMESTAMP NULL DEFAULT NULL AFTER plan,
  ADD COLUMN sip_balance INT NOT NULL DEFAULT 0 AFTER wallet_balance,
  ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT 'en' AFTER sip_balance,
  ADD COLUMN stripe_customer_id VARCHAR(64) NULL DEFAULT NULL AFTER referral_code,
  ADD COLUMN first100_promo TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_customer_id,
  ADD COLUMN first100_promo_ends_at TIMESTAMP NULL DEFAULT NULL AFTER first100_promo,
  ADD COLUMN referral_free_months_owed INT UNSIGNED NOT NULL DEFAULT 0 AFTER first100_promo_ends_at,
  ADD COLUMN terms_accepted_at TIMESTAMP NULL DEFAULT NULL AFTER referral_free_months_owed,
  ADD COLUMN privacy_accepted_at TIMESTAMP NULL DEFAULT NULL AFTER terms_accepted_at,
  ADD COLUMN settings_export_md_at TIMESTAMP NULL DEFAULT NULL AFTER privacy_accepted_at,
  ADD COLUMN delete_requested_at TIMESTAMP NULL DEFAULT NULL AFTER settings_export_md_at;

ALTER TABLE owners
  ADD UNIQUE KEY uq_owners_username (username);


-- -----------------------------------------------------------------------------
-- ALTER: pets — species popularity set + forum persona + per-pet XP denorm
-- Species by popularity: Dog, Cat, Bird, Rabbit, Hamster, Other
-- Keep legacy 'custom' for existing rows; app maps new pets to species_key.
-- Free=1 / Plus=3 pet caps are app-enforced (see plan on owners).
-- -----------------------------------------------------------------------------
ALTER TABLE pets
  MODIFY COLUMN type ENUM('dog', 'cat', 'bird', 'rabbit', 'hamster', 'other', 'custom') NOT NULL;

ALTER TABLE pets
  ADD COLUMN species_key VARCHAR(40) NULL DEFAULT NULL AFTER type,
  ADD COLUMN age_years DECIMAL(4, 1) NULL DEFAULT NULL AFTER breed,
  ADD COLUMN age_months TINYINT UNSIGNED NULL DEFAULT NULL AFTER age_years,
  ADD COLUMN forum_bio VARCHAR(280) NULL DEFAULT NULL AFTER image_path,
  ADD COLUMN xp_total INT NOT NULL DEFAULT 0 AFTER forum_bio,
  ADD COLUMN sip_earned_total INT NOT NULL DEFAULT 0 AFTER xp_total,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sip_earned_total;

ALTER TABLE pets
  ADD KEY idx_pets_species (species_key),
  ADD KEY idx_pets_owner_active (owner_id, is_active);


-- -----------------------------------------------------------------------------
-- ALTER: chat_threads / chat_messages — extend for SI (prefer extend, no dupe)
-- thread_kind='finder' = existing finder↔owner; 'si' = Muse-like SI per pet
-- finder_token nullable for SI threads; still required in app for finder kind.
-- -----------------------------------------------------------------------------
ALTER TABLE chat_threads
  ADD COLUMN thread_kind ENUM('finder', 'si') NOT NULL DEFAULT 'finder' AFTER id,
  ADD COLUMN title VARCHAR(120) NULL DEFAULT NULL AFTER thread_kind,
  ADD COLUMN si_model VARCHAR(80) NULL DEFAULT NULL AFTER title;

ALTER TABLE chat_threads
  MODIFY COLUMN finder_token CHAR(64) NULL DEFAULT NULL;

ALTER TABLE chat_threads
  ADD KEY idx_chat_kind_pet (thread_kind, pet_id, last_message_at);

ALTER TABLE chat_messages
  MODIFY COLUMN sender_role ENUM('owner', 'finder', 'user', 'assistant') NOT NULL;

ALTER TABLE chat_messages
  ADD COLUMN token_count INT UNSIGNED NULL DEFAULT NULL AFTER body;


-- -----------------------------------------------------------------------------
-- ALTER: support_tickets — bug Sip rewards (T23)
-- -----------------------------------------------------------------------------
ALTER TABLE support_tickets
  ADD COLUMN category ENUM('general', 'bug', 'billing', 'abuse', 'other') NOT NULL DEFAULT 'general' AFTER subject,
  ADD COLUMN is_bug TINYINT(1) NOT NULL DEFAULT 0 AFTER category,
  ADD COLUMN sip_reward INT NOT NULL DEFAULT 0 AFTER is_bug,
  ADD COLUMN sip_rewarded_at TIMESTAMP NULL DEFAULT NULL AFTER sip_reward,
  ADD COLUMN pet_id INT UNSIGNED NULL DEFAULT NULL AFTER owner_id;

ALTER TABLE support_tickets
  ADD KEY idx_tickets_bug (is_bug, status),
  ADD KEY idx_tickets_pet (pet_id);


-- -----------------------------------------------------------------------------
-- ALTER: notifications — channel + polymorphic related entity (bell / push nag)
-- -----------------------------------------------------------------------------
ALTER TABLE notifications
  ADD COLUMN channel ENUM('inbox', 'push', 'email') NOT NULL DEFAULT 'inbox' AFTER type,
  ADD COLUMN related_type VARCHAR(40) NULL DEFAULT NULL AFTER channel,
  ADD COLUMN related_id INT UNSIGNED NULL DEFAULT NULL AFTER related_type;

ALTER TABLE notifications
  ADD KEY idx_notifications_related (related_type, related_id);


-- =============================================================================
-- NEW TABLES
-- =============================================================================


-- —— SI rate limit / usage counters (T05–T06) ——
-- usage_day is calendar day in America/Toronto (app converts; stored as DATE).
CREATE TABLE IF NOT EXISTS si_usage_daily (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  usage_day DATE NOT NULL COMMENT 'America/Toronto calendar day',
  message_count INT UNSIGNED NOT NULL DEFAULT 0,
  token_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_si_usage_owner_day (owner_id, usage_day),
  CONSTRAINT fk_si_usage_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Fact-based routine templates (C3 / T11–T12) ——
-- Every default interval must cite reputable sources; Admin audits source_urls.
-- merge_group e.g. 'grooming' = bath + nails as one care pack.
CREATE TABLE IF NOT EXISTS routine_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  species_key VARCHAR(40) NOT NULL COMMENT 'dog|cat|bird|rabbit|hamster|other',
  breed_note VARCHAR(120) NULL DEFAULT NULL,
  pack_key VARCHAR(40) NOT NULL COMMENT 'grooming|feeding|walk|vet_check|other',
  title VARCHAR(120) NOT NULL,
  description TEXT NULL,
  interval_days INT UNSIGNED NULL DEFAULT NULL,
  frequency_note VARCHAR(255) NULL DEFAULT NULL,
  merge_group VARCHAR(40) NULL DEFAULT NULL COMMENT 'e.g. grooming merges bath+nails',
  source_urls TEXT NOT NULL COMMENT 'JSON array of citation URLs',
  last_verified_at DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_routine_tpl_species (species_key, is_active),
  KEY idx_routine_tpl_pack (pack_key, merge_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Per-pet routines (starter pack from templates; user edits) ——
CREATE TABLE IF NOT EXISTS pet_routines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NULL DEFAULT NULL,
  pack_key VARCHAR(40) NOT NULL,
  title VARCHAR(120) NOT NULL,
  description TEXT NULL,
  interval_days INT UNSIGNED NULL DEFAULT NULL,
  frequency_note VARCHAR(255) NULL DEFAULT NULL,
  merge_group VARCHAR(40) NULL DEFAULT NULL,
  user_edited TINYINT(1) NOT NULL DEFAULT 0,
  approved_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Must approve before reminders fire',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pet_routines_pet (pet_id, active),
  KEY idx_pet_routines_tpl (template_id),
  CONSTRAINT fk_pet_routines_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_pet_routines_tpl FOREIGN KEY (template_id) REFERENCES routine_templates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Care reminders — approve/check flow; merged grooming packs (C4) ——
-- No quiet hours in v1. Push: ask per reminder event (push_consent_asked_at).
CREATE TABLE IF NOT EXISTS care_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  pet_routine_id INT UNSIGNED NULL DEFAULT NULL,
  title VARCHAR(160) NOT NULL,
  due_at DATETIME NOT NULL,
  status ENUM('pending_approval', 'scheduled', 'sent', 'completed', 'dismissed', 'snoozed') NOT NULL DEFAULT 'pending_approval',
  approved_at TIMESTAMP NULL DEFAULT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  snoozed_until DATETIME NULL DEFAULT NULL,
  merge_group VARCHAR(40) NULL DEFAULT NULL,
  push_consent_asked_at TIMESTAMP NULL DEFAULT NULL,
  ics_uid VARCHAR(80) NULL DEFAULT NULL COMMENT '.ics only — no Google Calendar OAuth',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_care_reminders_due (status, due_at),
  KEY idx_care_reminders_pet (pet_id, status),
  KEY idx_care_reminders_routine (pet_routine_id),
  CONSTRAINT fk_care_reminders_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_care_reminders_routine FOREIGN KEY (pet_routine_id) REFERENCES pet_routines (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Forum: pet pages (pet-persona UI) ——
CREATE TABLE IF NOT EXISTS forum_pet_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  display_name VARCHAR(80) NOT NULL,
  bio VARCHAR(280) NULL DEFAULT NULL,
  avatar_path VARCHAR(255) NULL DEFAULT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_page_pet (pet_id),
  KEY idx_forum_page_owner (owner_id),
  CONSTRAINT fk_forum_page_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_page_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Forum posts: 250 char + optional image ——
CREATE TABLE IF NOT EXISTS forum_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL COMMENT 'Author = pet persona',
  owner_id INT UNSIGNED NOT NULL,
  body VARCHAR(250) NOT NULL,
  image_path VARCHAR(255) NULL DEFAULT NULL,
  like_count INT UNSIGNED NOT NULL DEFAULT 0,
  dislike_count INT UNSIGNED NOT NULL DEFAULT 0,
  comment_count INT UNSIGNED NOT NULL DEFAULT 0,
  moderation_status ENUM('visible', 'hidden', 'removed') NOT NULL DEFAULT 'visible',
  ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forum_posts_pet (pet_id, created_at),
  KEY idx_forum_posts_owner (owner_id, created_at),
  KEY idx_forum_posts_mod (moderation_status, created_at),
  CONSTRAINT fk_forum_posts_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_posts_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Forum likes / dislikes (public downvote) ——
CREATE TABLE IF NOT EXISTS forum_post_votes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id INT UNSIGNED NOT NULL,
  voter_owner_id INT UNSIGNED NOT NULL,
  vote TINYINT NOT NULL COMMENT '1 = like, -1 = dislike',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_vote (post_id, voter_owner_id),
  KEY idx_forum_votes_voter (voter_owner_id),
  CONSTRAINT fk_forum_votes_post FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_votes_owner FOREIGN KEY (voter_owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Forum comments ——
CREATE TABLE IF NOT EXISTS forum_comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NOT NULL COMMENT 'Commenter pet persona',
  owner_id INT UNSIGNED NOT NULL,
  body VARCHAR(500) NOT NULL,
  moderation_status ENUM('visible', 'hidden', 'removed') NOT NULL DEFAULT 'visible',
  ai_flagged TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forum_comments_post (post_id, id),
  KEY idx_forum_comments_pet (pet_id),
  CONSTRAINT fk_forum_comments_post FOREIGN KEY (post_id) REFERENCES forum_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_comments_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_comments_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Forum follows (follow a pet page) ——
CREATE TABLE IF NOT EXISTS forum_follows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  follower_owner_id INT UNSIGNED NOT NULL,
  following_pet_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_follow (follower_owner_id, following_pet_id),
  KEY idx_forum_follow_pet (following_pet_id),
  CONSTRAINT fk_forum_follow_owner FOREIGN KEY (follower_owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_follow_pet FOREIGN KEY (following_pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Forum reports → Admin + support notify (C1 / T14–T15) ——
CREATE TABLE IF NOT EXISTS forum_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_owner_id INT UNSIGNED NOT NULL,
  target_type ENUM('post', 'comment', 'pet_page', 'owner') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  reason VARCHAR(40) NOT NULL,
  detail TEXT NULL,
  status ENUM('open', 'reviewing', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
  admin_note TEXT NULL,
  support_ticket_id INT UNSIGNED NULL DEFAULT NULL,
  notified_support_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_forum_reports_status (status, created_at),
  KEY idx_forum_reports_target (target_type, target_id),
  KEY idx_forum_reports_reporter (reporter_owner_id),
  CONSTRAINT fk_forum_reports_reporter FOREIGN KEY (reporter_owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_reports_ticket FOREIGN KEY (support_ticket_id) REFERENCES support_tickets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Block list (C1 keep) ——
CREATE TABLE IF NOT EXISTS owner_blocks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  blocker_owner_id INT UNSIGNED NOT NULL,
  blocked_owner_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_owner_block (blocker_owner_id, blocked_owner_id),
  KEY idx_owner_blocked (blocked_owner_id),
  CONSTRAINT fk_owner_blocks_blocker FOREIGN KEY (blocker_owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_owner_blocks_blocked FOREIGN KEY (blocked_owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— XP events (daily login, forum, boards) ——
-- event_day = America/Toronto calendar day for daily-login uniqueness.
CREATE TABLE IF NOT EXISTS xp_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NULL DEFAULT NULL,
  event_type VARCHAR(40) NOT NULL COMMENT 'daily_login|forum_post|board_rank|other',
  xp_delta INT NOT NULL,
  event_day DATE NULL DEFAULT NULL COMMENT 'America/Toronto day when applicable',
  meta_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_xp_owner_day (owner_id, event_day),
  KEY idx_xp_pet (pet_id, created_at),
  KEY idx_xp_type (event_type, created_at),
  CONSTRAINT fk_xp_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_xp_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Weekly leaderboard snapshots (Canada TZ Mon–Sun) ——
-- week_start_date = Monday in America/Toronto; week_end_date = that Sunday.
-- Board Sip → discount: 1st 50%, 2nd 25%, 3rd 10%, 4th–10th 5%.
CREATE TABLE IF NOT EXISTS leaderboard_weekly_snapshots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  week_start_date DATE NOT NULL COMMENT 'Monday America/Toronto',
  week_end_date DATE NOT NULL COMMENT 'Sunday America/Toronto',
  species_key VARCHAR(40) NOT NULL COMMENT 'dog|cat|bird|rabbit|hamster|other|all',
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  rank_position INT UNSIGNED NOT NULL,
  xp_earned INT NOT NULL DEFAULT 0,
  sip_awarded INT NOT NULL DEFAULT 0,
  discount_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_board_week_species_pet (week_start_date, species_key, pet_id),
  KEY idx_board_week_rank (week_start_date, species_key, rank_position),
  KEY idx_board_owner (owner_id),
  CONSTRAINT fk_board_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_board_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Sip ledger (C9) — simple statement lines; running balance on owners.sip_balance ——
-- Plain UX descriptions e.g. "Daily login +2", "Board #2 +…", "Bug reward +20"
-- No cash value; not linked to pet_expenses.
CREATE TABLE IF NOT EXISTS sip_ledger (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Optional attribution only',
  entry_type VARCHAR(40) NOT NULL COMMENT 'daily_login|board_rank|bug_reward|referral|adjust|other',
  amount INT NOT NULL COMMENT 'Signed Sip delta',
  balance_after INT NOT NULL,
  description VARCHAR(200) NOT NULL,
  related_type VARCHAR(40) NULL DEFAULT NULL,
  related_id INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sip_ledger_owner (owner_id, created_at),
  KEY idx_sip_ledger_pet (pet_id, created_at),
  KEY idx_sip_ledger_type (entry_type, created_at),
  CONSTRAINT fk_sip_ledger_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_sip_ledger_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Referrals: every 3 paid → 1 free month (stacks) ——
CREATE TABLE IF NOT EXISTS referrals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_owner_id INT UNSIGNED NOT NULL,
  referred_owner_id INT UNSIGNED NOT NULL,
  status ENUM('signed_up', 'paid', 'rewarded') NOT NULL DEFAULT 'signed_up',
  rewarded_free_month TINYINT(1) NOT NULL DEFAULT 0,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  rewarded_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_referral_referred (referred_owner_id),
  KEY idx_referral_referrer (referrer_owner_id, status),
  CONSTRAINT fk_referral_referrer FOREIGN KEY (referrer_owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_referred FOREIGN KEY (referred_owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Subscriptions / Stripe + first-100 promo flags (T21–T22) ——
-- Standard $19.99/mo; first 100 paid → $4.99/mo for 3 months.
CREATE TABLE IF NOT EXISTS subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  provider ENUM('stripe', 'manual', 'promo') NOT NULL DEFAULT 'stripe',
  provider_subscription_id VARCHAR(64) NULL DEFAULT NULL,
  plan_code VARCHAR(40) NOT NULL DEFAULT 'plus_monthly',
  status ENUM('trialing', 'active', 'past_due', 'canceled', 'expired') NOT NULL DEFAULT 'active',
  price_cents INT UNSIGNED NOT NULL COMMENT '1999 standard; 499 first100 promo',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  is_first100 TINYINT(1) NOT NULL DEFAULT 0,
  current_period_start TIMESTAMP NULL DEFAULT NULL,
  current_period_end TIMESTAMP NULL DEFAULT NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subs_owner (owner_id, status),
  KEY idx_subs_provider (provider_subscription_id),
  KEY idx_subs_first100 (is_first100, created_at),
  CONSTRAINT fk_subs_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— First 100 paid promo counter (singleton row id=1) ——
CREATE TABLE IF NOT EXISTS promo_first100 (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  paid_count INT UNSIGNED NOT NULL DEFAULT 0,
  cap INT UNSIGNED NOT NULL DEFAULT 100,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO promo_first100 (id, paid_count, cap) VALUES (1, 0, 100);


-- —— Pet accounting (C11 / T20b) — real $ only; NO Sip/XP FKs ——
CREATE TABLE IF NOT EXISTS pet_expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  category ENUM('food', 'vet', 'groom', 'meds', 'supplies', 'other') NOT NULL DEFAULT 'other',
  amount DECIMAL(10, 2) NOT NULL COMMENT 'Real-world money; never convert from Sip/XP',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  spent_on DATE NOT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pet_expenses_pet_month (pet_id, spent_on),
  KEY idx_pet_expenses_owner (owner_id, spent_on),
  CONSTRAINT fk_pet_expenses_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_pet_expenses_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- —— Optional export job marker (T26 Settings / export MD) ——
CREATE TABLE IF NOT EXISTS data_export_jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  format ENUM('md') NOT NULL DEFAULT 'md',
  status ENUM('queued', 'ready', 'downloaded', 'expired', 'failed') NOT NULL DEFAULT 'queued',
  file_path VARCHAR(255) NULL DEFAULT NULL,
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ready_at TIMESTAMP NULL DEFAULT NULL,
  downloaded_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  error_note VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_export_owner (owner_id, requested_at),
  CONSTRAINT fk_export_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- ALTER summary (document for Navid / ops)
-- =============================================================================
-- owners:     +username, plan, plan_expires_at, sip_balance, locale,
--             stripe_customer_id, first100_promo*, referral_free_months_owed,
--             terms/privacy_accepted_at, settings_export_md_at, delete_requested_at
-- pets:       type ENUM expanded; +species_key, age_*, forum_bio, xp_total,
--             sip_earned_total, is_active
-- chat_*:     +thread_kind/title/si_model; finder_token nullable; sender_role
--             adds user|assistant; +token_count on messages
-- support_tickets: +category, is_bug, sip_reward*, pet_id
-- notifications:   +channel, related_type, related_id
--
-- New tables (19):
--   si_usage_daily, routine_templates, pet_routines, care_reminders,
--   forum_pet_pages, forum_posts, forum_post_votes, forum_comments,
--   forum_follows, forum_reports, owner_blocks, xp_events,
--   leaderboard_weekly_snapshots, sip_ledger, referrals, subscriptions,
--   promo_first100, pet_expenses, data_export_jobs
--   (+ promo_first100 seed row id=1)
-- =============================================================================
-- END 006_v1_product.sql
-- =============================================================================
