-- Add copy_admin checkbox to outbox table
ALTER TABLE outbox ADD COLUMN copy_admin tinyint NULL DEFAULT NULL;
