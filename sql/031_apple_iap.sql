-- SIPAW 031: Plus bought inside the iPhone app with Apple In-App Purchase. One row per verified Apple transaction.
CREATE TABLE IF NOT EXISTS apple_purchases (
  transaction_id VARCHAR(40) NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  original_transaction_id VARCHAR(40) NOT NULL,
  product_id VARCHAR(120) NOT NULL,
  environment VARCHAR(12) NOT NULL DEFAULT 'Production',
  purchased_at DATETIME NULL DEFAULT NULL,
  expires_at DATETIME NULL DEFAULT NULL,
  revoked_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (transaction_id),
  KEY idx_apple_owner (owner_id),
  KEY idx_apple_orig (original_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE owners ADD COLUMN apple_original_tx VARCHAR(40) NULL DEFAULT NULL;
