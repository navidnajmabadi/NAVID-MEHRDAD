-- =============================================================================
-- siPaw 007 — T03–T15 additive deltas (home area, wellness, SI secrets, seeds)
-- Prerequisites: 001–006 applied. Safe-ish re-run: CREATE IF NOT EXISTS;
-- ALTERs may warn Duplicate column — ignore and continue.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- T04 home area (Finder / Places context)
ALTER TABLE owners
  ADD COLUMN home_lat DECIMAL(10,7) NULL DEFAULT NULL AFTER lng,
  ADD COLUMN home_lng DECIMAL(10,7) NULL DEFAULT NULL AFTER home_lat,
  ADD COLUMN home_radius_km DECIMAL(5,2) NOT NULL DEFAULT 25.00 AFTER home_lng,
  ADD COLUMN home_area_label VARCHAR(160) NULL DEFAULT NULL AFTER home_radius_km;

-- T06c SI Brain ciphertext store
CREATE TABLE IF NOT EXISTS si_message_secrets (
  message_id INT UNSIGNED NOT NULL,
  ciphertext MEDIUMBLOB NOT NULL,
  iv VARBINARY(16) NOT NULL,
  tag VARBINARY(16) NOT NULL,
  key_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sha256_hex CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id),
  CONSTRAINT fk_si_secret_msg FOREIGN KEY (message_id) REFERENCES chat_messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- T06b wellness / allergy
CREATE TABLE IF NOT EXISTS wellness_symptom_defs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  symptom_key VARCHAR(40) NOT NULL,
  label_en VARCHAR(80) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_symptom_key (symptom_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wellness_daily_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  overall_note VARCHAR(500) NULL,
  photo_path VARCHAR(255) NULL,
  triggers_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wellness_pet_day (pet_id, log_date),
  KEY idx_wellness_owner_day (owner_id, log_date),
  CONSTRAINT fk_wellness_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_wellness_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wellness_daily_scores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  daily_log_id INT UNSIGNED NOT NULL,
  symptom_key VARCHAR(40) NOT NULL,
  severity TINYINT UNSIGNED NOT NULL,
  body_area VARCHAR(80) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_log_symptom (daily_log_id, symptom_key),
  CONSTRAINT fk_wellness_scores_log FOREIGN KEY (daily_log_id) REFERENCES wellness_daily_logs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_examinations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  exam_type ENUM('blood', 'urine', 'imaging', 'physical', 'allergy_panel', 'other') NOT NULL,
  title VARCHAR(160) NOT NULL,
  clinic_name VARCHAR(160) NULL,
  examined_on DATE NOT NULL,
  summary TEXT NULL,
  file_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exam_pet_date (pet_id, examined_on),
  CONSTRAINT fk_exam_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_lab_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  examination_id INT UNSIGNED NOT NULL,
  analyte_key VARCHAR(80) NOT NULL,
  analyte_label VARCHAR(120) NOT NULL,
  value_num DECIMAL(12,4) NULL,
  value_text VARCHAR(120) NULL,
  unit VARCHAR(40) NULL,
  ref_low DECIMAL(12,4) NULL,
  ref_high DECIMAL(12,4) NULL,
  flag ENUM('low','normal','high','unknown') NOT NULL DEFAULT 'unknown',
  PRIMARY KEY (id),
  KEY idx_lab_exam (examination_id),
  KEY idx_lab_analyte (analyte_key),
  CONSTRAINT fk_lab_exam FOREIGN KEY (examination_id) REFERENCES health_examinations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO wellness_symptom_defs (symptom_key, label_en, sort_order) VALUES
  ('itchy', 'Itchy', 10),
  ('paw_licking', 'Paw licking', 20),
  ('ear', 'Ears', 30),
  ('redness', 'Redness', 40),
  ('gi', 'GI / stool', 50),
  ('weight', 'Weight', 60),
  ('eyes', 'Eyes', 70),
  ('sneezing', 'Sneezing', 80),
  ('energy', 'Energy', 90),
  ('hotspot', 'Hotspot', 100),
  ('other', 'Other', 110);

-- T11 seed templates (re-run may duplicate — prune duplicates in Admin if needed)
INSERT INTO routine_templates
  (species_key, pack_key, title, description, interval_days, merge_group, source_urls, last_verified_at, is_active, sort_order)
VALUES
  ('dog', 'vaccines', 'Core vaccine reminder',
   'Annual core vaccine check with your vet. Confirm schedule for your region.',
   365, 'vaccines', '["https://www.avma.org/"]', CURDATE(), 1, 10),
  ('cat', 'vaccines', 'Core vaccine reminder',
   'Annual core vaccine check with your vet. Confirm schedule for your region.',
   365, 'vaccines', '["https://www.avma.org/"]', CURDATE(), 1, 10),
  ('dog', 'parasite', 'Flea / tick prevention',
   'Monthly parasite prevention. Follow product label and vet advice — SI does not dose.',
   30, 'parasite', '["https://www.fda.gov/animal-veterinary"]', CURDATE(), 1, 20),
  ('cat', 'parasite', 'Flea prevention',
   'Monthly flea prevention. Follow product label and vet advice — SI does not dose.',
   30, 'parasite', '["https://www.fda.gov/animal-veterinary"]', CURDATE(), 1, 20),
  ('other', 'care', 'Dental check reminder',
   'Twice-yearly dental awareness check. Ask your vet about professional cleaning.',
   180, 'care', '["https://www.avma.org/"]', CURDATE(), 1, 30);
