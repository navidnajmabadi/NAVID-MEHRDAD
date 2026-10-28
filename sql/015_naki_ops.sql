-- =============================================================================
-- SIPAW 015 — NAKI Red Team (scores, prompt proposals), app errors, uptime heartbeats
-- Prerequisites: 001–014. Applied automatically on deploy (scripts/migrate.php).
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE si_brain_scores
  MODIFY COLUMN run_id INT UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN case_id INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN source ENUM('live','exam') NOT NULL DEFAULT 'live' AFTER run_id,
  ADD COLUMN thread_id INT UNSIGNED NULL DEFAULT NULL AFTER message_id,
  ADD COLUMN prompt_version_id INT UNSIGNED NULL DEFAULT NULL AFTER thread_id,
  ADD COLUMN issues_json TEXT NULL DEFAULT NULL AFTER notes,
  ADD COLUMN signal_thanks TINYINT(1) NOT NULL DEFAULT 0 AFTER issues_json,
  ADD COLUMN signal_frustration TINYINT(1) NOT NULL DEFAULT 0 AFTER signal_thanks,
  ADD UNIQUE KEY uq_brain_score_message (message_id, source);

-- NAKI's prompt changes: proposed → applied (live) → kept / rolled back
CREATE TABLE IF NOT EXISTS naki_proposals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  base_version_id INT UNSIGNED NULL DEFAULT NULL,
  new_version_id INT UNSIGNED NULL DEFAULT NULL,
  status ENUM('proposed','applied','kept','rolled_back','rejected') NOT NULL DEFAULT 'proposed',
  trigger_source ENUM('schedule','admin') NOT NULL DEFAULT 'schedule',
  change_notes TEXT NULL DEFAULT NULL,
  issues_json TEXT NULL DEFAULT NULL,
  exam_before DECIMAL(5,2) NULL DEFAULT NULL,
  exam_after DECIMAL(5,2) NULL DEFAULT NULL,
  live_before DECIMAL(5,2) NULL DEFAULT NULL,
  live_after DECIMAL(5,2) NULL DEFAULT NULL,
  decided_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proposals_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uncaught errors in the app (Site health → error log), grouped by fingerprint
CREATE TABLE IF NOT EXISTS app_errors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fingerprint CHAR(40) NOT NULL,
  level VARCHAR(20) NOT NULL,
  message VARCHAR(500) NOT NULL,
  file VARCHAR(255) NULL DEFAULT NULL,
  line INT UNSIGNED NULL DEFAULT NULL,
  url VARCHAR(255) NULL DEFAULT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  first_seen DATETIME NOT NULL,
  last_seen DATETIME NOT NULL,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_app_errors_fp (fingerprint),
  KEY idx_app_errors_last (resolved, last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row each time a cron runs (gaps = the site or cron was down)
CREATE TABLE IF NOT EXISTS site_heartbeats (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source VARCHAR(40) NOT NULL,
  ok TINYINT(1) NOT NULL DEFAULT 1,
  db_ms SMALLINT UNSIGNED NULL DEFAULT NULL,
  mem_kb INT UNSIGNED NULL DEFAULT NULL,
  load1 DECIMAL(6,2) NULL DEFAULT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_heartbeats_time (source, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
