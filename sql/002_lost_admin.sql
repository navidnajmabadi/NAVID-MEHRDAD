-- RingRingPaw — LOST HELP ME + Admin (run once AFTER 001_schema.sql)
-- Same database as config db.name (phpMyAdmin or mysql CLI).
-- If an ALTER fails with "Duplicate column", that column already exists — continue.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- —— Owners: geo + billing stubs ——
ALTER TABLE owners
  ADD COLUMN lat DECIMAL(10, 7) NULL AFTER address,
  ADD COLUMN lng DECIMAL(10, 7) NULL AFTER lat,
  ADD COLUMN geocoded_at TIMESTAMP NULL DEFAULT NULL AFTER lng,
  ADD COLUMN city VARCHAR(120) NULL AFTER geocoded_at,
  ADD COLUMN country VARCHAR(120) NULL AFTER city,
  ADD COLUMN wallet_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER country,
  ADD COLUMN subscription_status ENUM('none', 'trial', 'active', 'past_due', 'canceled') NOT NULL DEFAULT 'none' AFTER wallet_balance,
  ADD COLUMN referral_code VARCHAR(20) NULL AFTER subscription_status;

ALTER TABLE notifications
  ADD COLUMN lost_alert_id INT UNSIGNED NULL DEFAULT NULL AFTER scan_id;

CREATE TABLE IF NOT EXISTS geocode_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  address_hash CHAR(64) NOT NULL,
  address_raw TEXT NOT NULL,
  lat DECIMAL(10, 7) NOT NULL,
  lng DECIMAL(10, 7) NOT NULL,
  city VARCHAR(120) DEFAULT NULL,
  country VARCHAR(120) DEFAULT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'nominatim',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_geocode_hash (address_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lost_alerts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  status ENUM('open', 'found', 'expired') NOT NULL DEFAULT 'open',
  center_lat DECIMAL(10, 7) NOT NULL,
  center_lng DECIMAL(10, 7) NOT NULL,
  radius_km DECIMAL(6, 2) NOT NULL DEFAULT 25.00,
  pet_name VARCHAR(80) NOT NULL,
  pet_type VARCHAR(40) NOT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  identifying_marks TEXT DEFAULT NULL,
  magic_word VARCHAR(120) DEFAULT NULL,
  medical_notes TEXT DEFAULT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  found_at TIMESTAMP NULL DEFAULT NULL,
  ping_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_ping_at TIMESTAMP NULL DEFAULT NULL,
  next_ping_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lost_status_next (status, next_ping_at),
  KEY idx_lost_owner (owner_id),
  KEY idx_lost_pet (pet_id),
  CONSTRAINT fk_lost_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_lost_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lost_alert_recipients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lost_alert_id INT UNSIGNED NOT NULL,
  recipient_owner_id INT UNSIGNED NOT NULL,
  distance_km DECIMAL(8, 3) DEFAULT NULL,
  notified_at TIMESTAMP NULL DEFAULT NULL,
  last_ping_at TIMESTAMP NULL DEFAULT NULL,
  ping_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lost_recipient (lost_alert_id, recipient_owner_id),
  KEY idx_lost_recip_owner (recipient_owner_id),
  CONSTRAINT fk_lost_recip_alert FOREIGN KEY (lost_alert_id) REFERENCES lost_alerts (id) ON DELETE CASCADE,
  CONSTRAINT fk_lost_recip_owner FOREIGN KEY (recipient_owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  admin_note TEXT DEFAULT NULL,
  assigned_admin_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_tickets_status (status, created_at),
  KEY idx_tickets_owner (owner_id),
  CONSTRAINT fk_tickets_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_admin FOREIGN KEY (assigned_admin_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  discount_type ENUM('percent', 'fixed', 'free_month') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  max_redemptions INT UNSIGNED DEFAULT NULL,
  redemption_count INT UNSIGNED NOT NULL DEFAULT 0,
  valid_from TIMESTAMP NULL DEFAULT NULL,
  valid_until TIMESTAMP NULL DEFAULT NULL,
  status ENUM('active', 'disabled', 'exhausted') NOT NULL DEFAULT 'active',
  created_by_admin_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupon_code (code),
  CONSTRAINT fk_coupons_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_action_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) DEFAULT NULL,
  target_id INT UNSIGNED DEFAULT NULL,
  detail TEXT DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_logs_created (created_at),
  KEY idx_admin_logs_admin (admin_id),
  CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional indexes (ignore "Duplicate key name" if re-run)
ALTER TABLE owners ADD INDEX idx_owners_geo (status, lat, lng);
ALTER TABLE owners ADD INDEX idx_owners_created (created_at);
