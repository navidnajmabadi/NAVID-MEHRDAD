-- SIPAW 026: the chosen look (Friendly or Clean) is saved on the account, so every device shows it.
ALTER TABLE owners ADD COLUMN ui_skin VARCHAR(12) NULL DEFAULT NULL;
