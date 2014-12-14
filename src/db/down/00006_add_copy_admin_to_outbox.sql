-- Drop copy_admin checkbox from outbox table
ALTER TABLE outbox DROP COLUMN copy_admin;
