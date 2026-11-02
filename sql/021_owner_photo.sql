-- 021: owner profile photo (Settings).
ALTER TABLE owners ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL;
