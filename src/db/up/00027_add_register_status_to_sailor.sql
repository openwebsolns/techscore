-- Add a new field to track registration status explicitly.
ALTER TABLE sailor
  ADD COLUMN register_status ENUM ('registered', 'unregistered', 'requested')
  NOT NULL DEFAULT 'requested' AFTER id;

UPDATE sailor SET register_status = 'registered' WHERE external_id IS NOT NULL;
UPDATE sailor SET register_status = 'unregistered' WHERE external_id IS NULL;
