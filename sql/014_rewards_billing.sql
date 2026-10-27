-- =============================================================================
-- SIPAW 014 — Paw coins, perfect weeks, referrals, Square billing
-- Prerequisites: 001–013. Applied automatically on deploy (scripts/migrate.php).
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Coin ledger dedupe: one reward per (owner, reason, thing)
ALTER TABLE sip_ledger
  ADD COLUMN local_day DATE NULL DEFAULT NULL AFTER related_id,
  ADD KEY idx_ledger_owner_type_day (owner_id, entry_type, local_day),
  ADD KEY idx_ledger_related (owner_id, entry_type, related_type, related_id);

-- One row per owner per week (Mon–Sun, owner's local time)
CREATE TABLE IF NOT EXISTS reward_weeks (
  owner_id INT UNSIGNED NOT NULL,
  week_start DATE NOT NULL,
  perfect TINYINT(1) NOT NULL DEFAULT 0,
  active_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
  due_items SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  done_items SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  spent_order_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'billing order that used this week''s 5%',
  evaluated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (owner_id, week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checkout orders (Square Payment Links). Plus is bought in months; discounts are applied per order.
CREATE TABLE IF NOT EXISTS billing_orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  plan_code VARCHAR(40) NOT NULL COMMENT 'plus_monthly | plus_yearly | free_month',
  months TINYINT UNSIGNED NOT NULL,
  list_cents INT UNSIGNED NOT NULL,
  discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  discount_json TEXT NULL DEFAULT NULL,
  provider ENUM('square','free_month','manual') NOT NULL DEFAULT 'square',
  provider_link_id VARCHAR(80) NULL DEFAULT NULL,
  provider_order_id VARCHAR(80) NULL DEFAULT NULL,
  provider_payment_id VARCHAR(80) NULL DEFAULT NULL,
  checkout_url VARCHAR(500) NULL DEFAULT NULL,
  status ENUM('pending','paid','failed','canceled') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_owner (owner_id, status),
  KEY idx_orders_provider (provider_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Square webhook events (idempotency + audit)
CREATE TABLE IF NOT EXISTS billing_events (
  event_id VARCHAR(80) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE subscriptions
  MODIFY COLUMN provider ENUM('stripe','manual','promo','square','free_month') NOT NULL DEFAULT 'square';

ALTER TABLE referrals
  ADD UNIQUE KEY uq_referred_once (referred_owner_id);

ALTER TABLE owners
  ADD UNIQUE KEY uq_owner_referral_code (referral_code);
