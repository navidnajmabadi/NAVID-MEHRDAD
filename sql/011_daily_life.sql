-- =============================================================================
-- SIPAW 011 — Daily life: bathroom logs, walks (with route), walk goal
-- Prerequisites: 001–010. Run once in phpMyAdmin.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pet_bathroom_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  kind ENUM('pee','poop','accident','vomit') NOT NULL,
  stool ENUM('normal','soft','diarrhea','hard','blood','mucus') NULL DEFAULT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  source ENUM('chat','button','manual') NOT NULL DEFAULT 'chat',
  logged_at DATETIME NOT NULL COMMENT 'UTC',
  local_day DATE NOT NULL COMMENT 'owner local date, for daily counts',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bath_pet_day (pet_id, local_day, kind),
  CONSTRAINT fk_bath_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_walks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL COMMENT 'UTC',
  local_day DATE NOT NULL,
  minutes SMALLINT UNSIGNED NOT NULL,
  distance_m INT UNSIGNED NULL DEFAULT NULL,
  route_json MEDIUMTEXT NULL DEFAULT NULL COMMENT '[[lat,lng],…] simplified, ≤ 400 points',
  source ENUM('gps','chat','manual') NOT NULL DEFAULT 'chat',
  weather_text VARCHAR(160) NULL DEFAULT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_walks_pet_day (pet_id, local_day),
  CONSTRAINT fk_walks_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pets
  ADD COLUMN walk_goal_minutes SMALLINT UNSIGNED NULL DEFAULT NULL AFTER care_region;
