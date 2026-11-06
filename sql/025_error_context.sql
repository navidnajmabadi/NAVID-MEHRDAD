-- SIPAW 025: errors keep who/what/where: owner, browser and the stack trace, so one pasted error is enough to fix it.
ALTER TABLE app_errors ADD COLUMN owner_id INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE app_errors ADD COLUMN trace TEXT NULL;
ALTER TABLE app_errors ADD COLUMN user_agent VARCHAR(200) NULL DEFAULT NULL;
