-- 022: one-tap daily care checks (water, teeth) so SIPO can learn each pet's usual times.
CREATE TABLE IF NOT EXISTS pet_daily_checks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL COMMENT 'water|teeth',
  logged_at DATETIME NOT NULL COMMENT 'UTC',
  local_day DATE NOT NULL,
  source VARCHAR(12) NOT NULL DEFAULT 'button',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_check_pet_day (pet_id, local_day, kind),
  CONSTRAINT fk_check_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
