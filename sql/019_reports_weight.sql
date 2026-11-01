-- 019: weight history (for the weight trend and the weekly health report) and report preferences.
CREATE TABLE IF NOT EXISTS pet_weight_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  weight_kg DECIMAL(6,2) NOT NULL,
  logged_on DATE NOT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'sipo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_weight_pet_day (pet_id, logged_on),
  CONSTRAINT fk_weight_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pet_weight_logs (pet_id, weight_kg, logged_on, source)
  SELECT id, weight_kg, DATE(COALESCE(updated_at, created_at)), 'backfill' FROM pets WHERE weight_kg IS NOT NULL AND weight_kg > 0;

ALTER TABLE owners ADD COLUMN report_weekly TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE owners ADD COLUMN report_last_sent_at DATETIME NULL DEFAULT NULL;
