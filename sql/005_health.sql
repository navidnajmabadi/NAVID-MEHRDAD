-- siPaw — Health Care tables (vaccines, meds, feeding, vet appointments)
-- Run once AFTER 001–003 (004_chat optional / independent).
-- Hostinger phpMyAdmin: Import into the same DB as config db.name.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS vaccination_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  given_on DATE NULL DEFAULT NULL,
  next_due DATE NULL DEFAULT NULL,
  notes VARCHAR(500) NULL DEFAULT NULL,
  reminded_due_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vax_pet (pet_id),
  KEY idx_vax_due (next_due),
  CONSTRAINT fk_vax_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medication_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  dose_note VARCHAR(255) NULL DEFAULT NULL,
  schedule_time TIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  next_remind_at TIMESTAMP NULL DEFAULT NULL,
  last_reminded_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_med_pet (pet_id),
  KEY idx_med_next (active, next_remind_at),
  CONSTRAINT fk_med_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feeding_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  label VARCHAR(80) NOT NULL,
  feed_time TIME NOT NULL,
  portion_note VARCHAR(255) NULL DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feed_pet (pet_id),
  CONSTRAINT fk_feed_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feeding_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  feeding_schedule_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NOT NULL,
  fed_on DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_day (feeding_schedule_id, fed_on),
  KEY idx_feed_log_pet (pet_id, fed_on),
  CONSTRAINT fk_feed_log_sched FOREIGN KEY (feeding_schedule_id) REFERENCES feeding_schedules (id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_log_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vet_appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  clinic_name VARCHAR(160) NULL DEFAULT NULL,
  appointment_at DATETIME NOT NULL,
  notes VARCHAR(500) NULL DEFAULT NULL,
  reminded_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vet_pet (pet_id),
  KEY idx_vet_when (appointment_at),
  CONSTRAINT fk_vet_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
