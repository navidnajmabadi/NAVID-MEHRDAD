-- RingRingPaw — Web Push subscription columns (run once AFTER 002_lost_admin.sql)
-- Same database as config db.name. Ignore "Duplicate column" / "Duplicate key" if re-run.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE push_subscriptions
  ADD COLUMN endpoint_hash CHAR(64) NULL AFTER endpoint,
  ADD COLUMN user_agent VARCHAR(512) NULL AFTER auth,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN last_success_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
  ADD COLUMN fail_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_success_at;

-- Backfill hashes for existing rows
UPDATE push_subscriptions
SET endpoint_hash = SHA2(endpoint, 256)
WHERE endpoint_hash IS NULL OR endpoint_hash = '';

ALTER TABLE push_subscriptions
  ADD UNIQUE KEY uq_push_endpoint_hash (endpoint_hash);
