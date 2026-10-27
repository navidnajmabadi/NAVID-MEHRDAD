-- =============================================================================
-- SIPAW 012 — Lost Mode: last-seen point, sightings from helpers, owner Found nudges
-- Prerequisites: 001–011. Run once in phpMyAdmin.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE lost_alerts
  ADD COLUMN last_seen_lat DECIMAL(9,6) NULL DEFAULT NULL AFTER center_lng,
  ADD COLUMN last_seen_lng DECIMAL(9,6) NULL DEFAULT NULL AFTER last_seen_lat,
  ADD COLUMN last_seen_note VARCHAR(255) NULL DEFAULT NULL AFTER last_seen_lng,
  ADD COLUMN owner_nudged_at DATETIME NULL DEFAULT NULL AFTER next_ping_at;

-- "I saw this pet" reports from nearby helpers (location rounded to ~100 m)
CREATE TABLE IF NOT EXISTS lost_sightings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lost_alert_id INT UNSIGNED NOT NULL,
  reporter_owner_id INT UNSIGNED NOT NULL,
  lat DECIMAL(9,6) NULL DEFAULT NULL,
  lng DECIMAL(9,6) NULL DEFAULT NULL,
  seen_at DATETIME NOT NULL,
  note VARCHAR(500) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sightings_alert (lost_alert_id, seen_at),
  CONSTRAINT fk_sightings_alert FOREIGN KEY (lost_alert_id) REFERENCES lost_alerts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
