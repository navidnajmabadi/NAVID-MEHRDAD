-- 020: first-time onboarding. NULL = the owner has not seen the welcome screen yet.
ALTER TABLE owners ADD COLUMN onboarded_at DATETIME NULL DEFAULT NULL;

-- Everyone who already has an account skips the welcome screen.
UPDATE owners SET onboarded_at = COALESCE(created_at, UTC_TIMESTAMP()) WHERE onboarded_at IS NULL;
