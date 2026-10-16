-- Nakilab database schema (MySQL / MariaDB).
-- The site creates these tables automatically; this file is for reference or for running
-- by hand in hPanel → Databases → phpMyAdmin → u233722910_nakilab → SQL.

CREATE TABLE IF NOT EXISTS submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL,                 -- UTC
  type VARCHAR(16) NOT NULL,                    -- have | need | invest
  name VARCHAR(120) NOT NULL,
  email VARCHAR(200) NOT NULL,
  details MEDIUMTEXT NOT NULL,                  -- JSON with the form fields
  ip_hash CHAR(64) NOT NULL,                    -- salted hash, used only for rate limiting
  status VARCHAR(16) NOT NULL DEFAULT 'new',    -- new | reviewing | accepted | declined
  KEY idx_created (created_at),
  KEY idx_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(200) NOT NULL,
  excerpt VARCHAR(400) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,                     -- Markdown
  tags VARCHAR(300) NOT NULL DEFAULT '',        -- comma-separated
  author VARCHAR(80) NOT NULL DEFAULT 'Nakilab',
  status VARCHAR(16) NOT NULL DEFAULT 'draft',  -- draft | scheduled | published | archived
  publish_at DATETIME NULL,                     -- UTC
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT '',  -- which tool created it
  UNIQUE KEY uniq_slug (slug),
  KEY idx_live (status, publish_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Handy queries
-- New submissions:   SELECT id, created_at, type, name, email, details FROM submissions WHERE status = 'new' ORDER BY id DESC;
-- Drafts to review:  SELECT id, title, created_at FROM posts WHERE status = 'draft' ORDER BY id DESC;
-- Publish a draft:   UPDATE posts SET status = 'published', publish_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = 1;
