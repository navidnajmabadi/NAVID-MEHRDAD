-- =============================================================================
-- SIPAW 013 — Social: moderation log, removal reasons, suspension, photo sizes
-- Prerequisites: 001–012. Applied automatically on deploy (scripts/migrate.php).
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE forum_posts
  ADD COLUMN image_w SMALLINT UNSIGNED NULL DEFAULT NULL AFTER image_path,
  ADD COLUMN image_h SMALLINT UNSIGNED NULL DEFAULT NULL AFTER image_w,
  ADD COLUMN removed_reason VARCHAR(160) NULL DEFAULT NULL AFTER ai_flagged,
  ADD COLUMN removed_at DATETIME NULL DEFAULT NULL AFTER removed_reason,
  ADD KEY idx_forum_posts_feed (moderation_status, created_at),
  ADD KEY idx_forum_posts_pet_feed (pet_id, moderation_status, created_at);

ALTER TABLE forum_comments
  ADD COLUMN removed_reason VARCHAR(160) NULL DEFAULT NULL AFTER ai_flagged,
  ADD COLUMN removed_at DATETIME NULL DEFAULT NULL AFTER removed_reason;

-- Every moderation decision, per user (Navid: AI deletes + keeps a log per user)
CREATE TABLE IF NOT EXISTS moderation_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL COMMENT 'author of the content',
  target_type ENUM('post','comment') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  action ENUM('removed','hidden','restored') NOT NULL,
  source ENUM('ai','reports','admin','filter') NOT NULL,
  reason VARCHAR(160) NOT NULL,
  categories_json TEXT NULL DEFAULT NULL,
  excerpt VARCHAR(250) NULL DEFAULT NULL,
  admin_id INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modlog_owner (owner_id, action),
  KEY idx_modlog_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suspension after more than 5 removals (Support reviews manually)
ALTER TABLE owners
  ADD COLUMN suspended_reason VARCHAR(160) NULL DEFAULT NULL AFTER status,
  ADD COLUMN suspended_at DATETIME NULL DEFAULT NULL AFTER suspended_reason;
