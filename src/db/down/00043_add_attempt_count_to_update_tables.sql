-- Add column attempt_count to all update tables
ALTER TABLE pub_update_conference DROP COLUMN `attempt_count`;
ALTER TABLE pub_update_file DROP COLUMN `attempt_count`;
ALTER TABLE pub_update_request DROP COLUMN `attempt_count`;
ALTER TABLE pub_update_sailor DROP COLUMN `attempt_count`;
ALTER TABLE pub_update_school DROP COLUMN `attempt_count`;
ALTER TABLE pub_update_season DROP COLUMN `attempt_count`;
