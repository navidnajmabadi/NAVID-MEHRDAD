-- =============================================================================
-- SIPAW 009 — SIPO brain, one chat per pet, remember-me login, proactive nudges
-- Prerequisites: 001–008. Run once in phpMyAdmin.
-- ALTERs may warn "Duplicate column" on re-run — ignore and continue.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Login: 60-day remember-me (selector + sha256(validator)), rotated on use
CREATE TABLE IF NOT EXISTS auth_remember_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  user_agent VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_remember_selector (selector),
  KEY idx_remember_owner (owner_id),
  KEY idx_remember_expires (expires_at),
  CONSTRAINT fk_remember_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login throttle (email+IP window)
CREATE TABLE IF NOT EXISTS auth_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_attempt_email (email_hash, created_at),
  KEY idx_attempt_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Owners: no human PII required (brief §4). Nickname + timezone + SIPO cooldown.
ALTER TABLE owners
  MODIFY COLUMN phone VARCHAR(40) NULL DEFAULT NULL,
  MODIFY COLUMN address TEXT NULL DEFAULT NULL,
  MODIFY COLUMN home_radius_km DECIMAL(5,2) NOT NULL DEFAULT 20.00;

ALTER TABLE owners
  ADD COLUMN nickname VARCHAR(60) NULL DEFAULT NULL AFTER name,
  ADD COLUMN timezone VARCHAR(64) NULL DEFAULT NULL AFTER locale,
  ADD COLUMN sipo_cooldown_until DATETIME NULL DEFAULT NULL AFTER timezone,
  ADD COLUMN current_pet_id INT UNSIGNED NULL DEFAULT NULL AFTER sipo_cooldown_until;

-- Pets: identity fields SIPO learns over time (all optional)
ALTER TABLE pets
  ADD COLUMN sex ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown' AFTER breed,
  ADD COLUMN birth_date DATE NULL DEFAULT NULL AFTER sex,
  ADD COLUMN weight_kg DECIMAL(5,2) NULL DEFAULT NULL AFTER birth_date,
  ADD COLUMN neutered TINYINT(1) NULL DEFAULT NULL AFTER weight_kg;

-- One SIPO chat per pet. pet_id is NULL only while SIPO onboards a new pet.
ALTER TABLE chat_threads
  MODIFY COLUMN pet_id INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE chat_threads
  ADD COLUMN onboarding TINYINT(1) NOT NULL DEFAULT 0 AFTER thread_kind,
  ADD COLUMN summary MEDIUMTEXT NULL DEFAULT NULL AFTER si_model,
  ADD COLUMN summary_upto_id INT UNSIGNED NULL DEFAULT NULL AFTER summary,
  ADD KEY idx_threads_owner_kind (owner_id, thread_kind, pet_id);

ALTER TABLE chat_messages
  MODIFY COLUMN body TEXT NOT NULL,
  ADD COLUMN meta_json TEXT NULL DEFAULT NULL AFTER token_count,
  ADD KEY idx_messages_thread_id (thread_id, id);

-- Long-term facts about a pet (one row per key; latest value wins)
CREATE TABLE IF NOT EXISTS sipo_memory (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NOT NULL,
  fact_key VARCHAR(80) NOT NULL,
  fact_value VARCHAR(500) NOT NULL,
  category ENUM('identity','health','food','routine','behavior','home','owner','other') NOT NULL DEFAULT 'other',
  source_message_id INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_memory_pet_key (pet_id, fact_key),
  KEY idx_memory_owner (owner_id),
  CONSTRAINT fk_memory_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proactive SIPO messages (one row per sent nudge; used for daily cap + spacing)
CREATE TABLE IF NOT EXISTS sipo_nudges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NOT NULL,
  thread_id INT UNSIGNED NOT NULL,
  message_id INT UNSIGNED NULL DEFAULT NULL,
  kind VARCHAR(40) NOT NULL COMMENT 'greeting|checkin|profile_gap|reminder',
  local_day DATE NOT NULL,
  pushed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_nudges_owner_day (owner_id, local_day),
  KEY idx_nudges_pet_created (pet_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When each owner usually talks to SIPO (local hour 0–23) → nudge timing
CREATE TABLE IF NOT EXISTS owner_activity_hours (
  owner_id INT UNSIGNED NOT NULL,
  local_hour TINYINT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (owner_id, local_hour),
  CONSTRAINT fk_activity_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rolling token window for Cool Down (one row per owner per hour bucket)
CREATE TABLE IF NOT EXISTS sipo_token_window (
  owner_id INT UNSIGNED NOT NULL,
  bucket_start DATETIME NOT NULL,
  tokens INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (owner_id, bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prompt versions: keep history for rollback (NAKI auto-apply, Navid 2026-10-28)
ALTER TABLE si_prompt_versions
  ADD COLUMN layer VARCHAR(40) NOT NULL DEFAULT 'core' AFTER version_label,
  ADD COLUMN created_by VARCHAR(40) NOT NULL DEFAULT 'human' AFTER notes,
  ADD COLUMN replaced_at DATETIME NULL DEFAULT NULL AFTER created_by;

-- Lost Mode: brief schedule is 0 / +1h / +5h within 20 km (code constants follow)
UPDATE owners SET home_radius_km = 20.00 WHERE home_radius_km = 25.00;

-- Pre-009 prompt rows were a 5-line stub; keep them as history, let the layered
-- default in lib/sipo/prompt.php run until NAKI publishes a new core version.
UPDATE si_prompt_versions SET layer = 'legacy', is_live = 0, replaced_at = UTC_TIMESTAMP() WHERE layer = 'core' AND system_prompt NOT LIKE '%# Scope (strict)%';
