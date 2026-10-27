-- =============================================================================
-- SIPAW 010 — Care routines + follow-ups, vet treatments, labs, food, vet share link
-- Prerequisites: 001–009. Run once in phpMyAdmin.
-- ALTERs may warn "Duplicate column" on re-run — ignore and continue.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Reminders that chase an answer (day before → due day → "did you go?")
ALTER TABLE care_reminders
  ADD COLUMN kind VARCHAR(40) NOT NULL DEFAULT 'custom' AFTER pet_routine_id,
  ADD COLUMN routine_key VARCHAR(60) NULL DEFAULT NULL AFTER kind,
  ADD COLUMN followup_stage TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER push_consent_asked_at,
  ADD COLUMN last_followup_at DATETIME NULL DEFAULT NULL AFTER followup_stage,
  ADD COLUMN outcome ENUM('done','rescheduled','skipped','vet_booked') NULL DEFAULT NULL AFTER last_followup_at,
  ADD COLUMN outcome_note VARCHAR(255) NULL DEFAULT NULL AFTER outcome,
  ADD KEY idx_care_reminders_followup (status, followup_stage, due_at);

-- Pets: what the vet said they should eat, and how active they are
ALTER TABLE pets
  ADD COLUMN vet_kcal_per_day INT UNSIGNED NULL DEFAULT NULL AFTER neutered,
  ADD COLUMN activity ENUM('low','normal','high') NOT NULL DEFAULT 'normal' AFTER vet_kcal_per_day,
  ADD COLUMN lifestyle ENUM('indoor','outdoor','mixed') NULL DEFAULT NULL AFTER activity,
  ADD COLUMN care_region VARCHAR(20) NULL DEFAULT NULL AFTER lifestyle;

-- Pets under vet treatment (Navid 2026-10-28)
CREATE TABLE IF NOT EXISTS vet_treatments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  condition_name VARCHAR(160) NOT NULL,
  vet_name VARCHAR(160) NULL DEFAULT NULL,
  started_on DATE NOT NULL,
  planned_end_on DATE NULL DEFAULT NULL,
  status ENUM('active','finished','paused') NOT NULL DEFAULT 'active',
  instructions TEXT NULL DEFAULT NULL COMMENT 'Vet instructions as the owner reported them, verbatim',
  finished_on DATE NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_treatments_pet (pet_id, status),
  CONSTRAINT fk_treatments_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS treatment_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  treatment_id INT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  trend ENUM('better','same','worse') NOT NULL,
  med_given TINYINT(1) NULL DEFAULT NULL,
  note VARCHAR(500) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_treatment_day (treatment_id, log_date),
  CONSTRAINT fk_tlogs_treatment FOREIGN KEY (treatment_id) REFERENCES vet_treatments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE medication_schedules
  ADD COLUMN treatment_id INT UNSIGNED NULL DEFAULT NULL AFTER pet_id,
  ADD COLUMN kind ENUM('medicine','supplement') NOT NULL DEFAULT 'medicine' AFTER treatment_id,
  ADD COLUMN ends_on DATE NULL DEFAULT NULL AFTER schedule_time,
  ADD KEY idx_meds_treatment (treatment_id);

ALTER TABLE vet_appointments
  ADD COLUMN treatment_id INT UNSIGNED NULL DEFAULT NULL AFTER pet_id;

-- Lab reports: where the numbers came from (the file itself is deleted)
ALTER TABLE health_examinations
  ADD COLUMN source ENUM('manual','upload','chat') NOT NULL DEFAULT 'manual' AFTER file_path,
  ADD COLUMN species_note VARCHAR(120) NULL DEFAULT NULL AFTER source;

-- Foods this pet eats (from label photos or typed in)
CREATE TABLE IF NOT EXISTS pet_foods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  food_kind ENUM('dry','wet','treat','raw','homemade','other') NOT NULL DEFAULT 'dry',
  kcal_per_kg DECIMAL(8,1) NULL DEFAULT NULL,
  kcal_per_cup DECIMAL(7,1) NULL DEFAULT NULL,
  grams_per_cup DECIMAL(6,1) NULL DEFAULT NULL,
  package_grams INT UNSIGNED NULL DEFAULT NULL,
  opened_on DATE NULL DEFAULT NULL,
  remaining_grams INT UNSIGNED NULL DEFAULT NULL,
  remaining_set_on DATE NULL DEFAULT NULL,
  daily_grams DECIMAL(7,1) NULL DEFAULT NULL,
  supply_warned_for DATE NULL DEFAULT NULL COMMENT 'run-out date we already warned about',
  warnings_json TEXT NULL DEFAULT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_foods_pet (pet_id, is_current),
  CONSTRAINT fk_foods_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meals: what, how much, liked? (schedule optional — chat logs have none)
ALTER TABLE feeding_logs
  MODIFY COLUMN feeding_schedule_id INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN food_id INT UNSIGNED NULL DEFAULT NULL AFTER pet_id,
  ADD COLUMN grams DECIMAL(7,1) NULL DEFAULT NULL AFTER fed_on,
  ADD COLUMN kcal DECIMAL(7,1) NULL DEFAULT NULL AFTER grams,
  ADD COLUMN liked ENUM('loved','ok','refused') NULL DEFAULT NULL AFTER kcal,
  ADD COLUMN note VARCHAR(255) NULL DEFAULT NULL AFTER liked,
  ADD COLUMN logged_at DATETIME NULL DEFAULT NULL AFTER note,
  ADD KEY idx_feeding_logs_pet_day (pet_id, fed_on);

-- Read-only link for the vet (whole health dashboard)
CREATE TABLE IF NOT EXISTS health_share_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL DEFAULT NULL,
  last_viewed_at DATETIME NULL DEFAULT NULL,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share_token (token_hash),
  KEY idx_share_pet (pet_id),
  CONSTRAINT fk_share_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
