-- Google sign-in: the Google account's stable id ("sub"), unique per owner. NULL = not linked.
ALTER TABLE owners ADD COLUMN google_sub VARCHAR(64) NULL DEFAULT NULL AFTER password_hash,
  ADD UNIQUE KEY uq_owners_google_sub (google_sub);
