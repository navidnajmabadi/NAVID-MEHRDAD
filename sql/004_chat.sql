-- siPaw — finder ↔ owner chat (run once AFTER 001–003)
-- Hostinger phpMyAdmin: select the RingRingPaw DB, Import this file (or paste + Go).
-- Idempotent: CREATE TABLE IF NOT EXISTS.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS chat_threads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pet_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  scan_id INT UNSIGNED NULL DEFAULT NULL,
  finder_token CHAR(64) NOT NULL,
  finder_name VARCHAR(80) NULL DEFAULT NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat_scan (scan_id),
  KEY idx_chat_owner_last (owner_id, last_message_at),
  KEY idx_chat_finder (finder_token),
  KEY idx_chat_pet (pet_id),
  CONSTRAINT fk_chat_threads_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_threads_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_threads_scan FOREIGN KEY (scan_id) REFERENCES scans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id INT UNSIGNED NOT NULL,
  sender_role ENUM('owner', 'finder') NOT NULL,
  body VARCHAR(2000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chat_messages_thread (thread_id, id),
  CONSTRAINT fk_chat_messages_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
