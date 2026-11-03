-- SIPAW 024: every SIPO reply is logged as a "request" with a topic, a score and its token cost;
-- requests are grouped (KNN) into clusters shown on the NAKI graph; Admin test lab cases.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS si_request_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id INT UNSIGNED NULL DEFAULT NULL,
  domain VARCHAR(24) NOT NULL DEFAULT 'general',
  req_text VARCHAR(400) NOT NULL DEFAULT '',
  score DECIMAL(3,1) NULL DEFAULT NULL,
  issues VARCHAR(255) NOT NULL DEFAULT '',
  checks_json TEXT NULL,
  prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  cached_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  reasoning_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  rounds TINYINT UNSIGNED NOT NULL DEFAULT 1,
  tools_used VARCHAR(255) NOT NULL DEFAULT '',
  tools_offered TINYINT UNSIGNED NOT NULL DEFAULT 0,
  model VARCHAR(60) NOT NULL DEFAULT '',
  source ENUM('live','test') NOT NULL DEFAULT 'live',
  cluster_id INT UNSIGNED NULL DEFAULT NULL,
  marked TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_req_created (created_at),
  KEY idx_req_domain (domain, created_at),
  KEY idx_req_cluster (cluster_id),
  KEY idx_req_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS si_clusters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(120) NOT NULL,
  domain VARCHAR(24) NOT NULL DEFAULT 'general',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  avg_score DECIMAL(3,2) NULL DEFAULT NULL,
  avg_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  terms VARCHAR(255) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cluster_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS si_tests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain VARCHAR(24) NOT NULL DEFAULT 'general',
  ask VARCHAR(500) NOT NULL,
  must_any VARCHAR(500) NOT NULL DEFAULT '',
  must_not VARCHAR(500) NOT NULL DEFAULT '',
  tool VARCHAR(60) NOT NULL DEFAULT '',
  source ENUM('manual','bulk','issue') NOT NULL DEFAULT 'manual',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_pass TINYINT(1) NULL DEFAULT NULL,
  last_score DECIMAL(3,1) NULL DEFAULT NULL,
  last_reply VARCHAR(600) NULL DEFAULT NULL,
  last_tokens INT UNSIGNED NULL DEFAULT NULL,
  last_run_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tests_domain (domain, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
