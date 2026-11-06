-- SIPAW 029: 7-day trial per account (admin can change each one), cancel subscription, no HST (small supplier), business in Canada.
ALTER TABLE owners ADD COLUMN trial_ends_at DATETIME NULL DEFAULT NULL;
ALTER TABLE owners ADD COLUMN canceled_at DATETIME NULL DEFAULT NULL;
ALTER TABLE owners ADD COLUMN cancel_reason VARCHAR(80) NULL DEFAULT NULL;
-- Everyone who already has an account gets 7 days from the deploy, so nobody is locked out on day one.
UPDATE owners SET trial_ends_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY) WHERE trial_ends_at IS NULL;
-- No tax is charged (the business is under the small-supplier limit); the business is in Canada.
INSERT INTO app_settings (k, v) VALUES ('tax_pct', '0') ON DUPLICATE KEY UPDATE v = '0';
INSERT INTO app_settings (k, v) VALUES ('tax_countries', 'CA') ON DUPLICATE KEY UPDATE v = 'CA';
INSERT INTO app_settings (k, v) VALUES ('biz_country', 'Canada') ON DUPLICATE KEY UPDATE v = 'Canada';
INSERT IGNORE INTO app_settings (k, v) VALUES ('trial_days', '7');
INSERT IGNORE INTO app_settings (k, v) VALUES ('trial_enforced', '1');
