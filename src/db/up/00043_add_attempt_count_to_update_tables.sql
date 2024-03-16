-- Add column attempt_count to all update tables
ALTER TABLE pub_update_conference ADD COLUMN `attempt_count` SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE pub_update_file ADD COLUMN `attempt_count` SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE pub_update_request ADD COLUMN `attempt_count` SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE pub_update_sailor ADD COLUMN `attempt_count` SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE pub_update_school ADD COLUMN `attempt_count` SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE pub_update_season ADD COLUMN `attempt_count` SMALLINT NOT NULL DEFAULT 0;
