-- 023: where a notification goes when tapped (the bell list), kept next to the text.
ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL DEFAULT NULL;
