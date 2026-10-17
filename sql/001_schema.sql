-- RingRingPaw Phase 1 schema
-- Run once in Hostinger phpMyAdmin (or mysql CLI) against the RingRingPaw database.
-- Charset: utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS owners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  address TEXT NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_owners_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  public_id CHAR(16) NOT NULL,
  name VARCHAR(80) NOT NULL,
  type ENUM('dog', 'cat', 'rabbit', 'custom') NOT NULL,
  custom_type VARCHAR(80) DEFAULT NULL,
  breed VARCHAR(120) DEFAULT NULL,
  color VARCHAR(80) DEFAULT NULL,
  magic_word VARCHAR(120) DEFAULT NULL,
  identifying_marks TEXT DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  medical_vaccines TEXT DEFAULT NULL,
  medical_next_due DATE DEFAULT NULL,
  medical_pills TEXT DEFAULT NULL,
  medical_conditions TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pets_public_id (public_id),
  KEY idx_pets_owner (owner_id),
  CONSTRAINT fk_pets_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finder_ip VARCHAR(45) DEFAULT NULL,
  finder_ua VARCHAR(512) DEFAULT NULL,
  mode ENUM('none', 'one-time', 'live') NOT NULL DEFAULT 'none',
  lat DECIMAL(10, 7) DEFAULT NULL,
  lng DECIMAL(10, 7) DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  notified_owner TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_scans_pet (pet_id),
  KEY idx_scans_active (active, expires_at),
  CONSTRAINT fk_scans_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  scan_id INT UNSIGNED DEFAULT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_owner (owner_id, created_at),
  CONSTRAINT fk_notifications_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_scan FOREIGN KEY (scan_id) REFERENCES scans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_push_owner (owner_id),
  CONSTRAINT fk_push_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
