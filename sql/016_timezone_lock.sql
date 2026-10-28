-- QA v3 B37: a device in another time zone (a test robot in Amsterdam) moved the owner's clock.
-- timezone_auto = 1 follows the phone (default, as before); 0 keeps the zone chosen in Settings.
ALTER TABLE owners ADD COLUMN timezone_auto TINYINT(1) NOT NULL DEFAULT 1 AFTER timezone;
