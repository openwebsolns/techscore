-- Use external_id instead of icsa_id
ALTER TABLE sailor CHANGE COLUMN external_id icsa_id int(10) unsigned DEFAULT NULL;
