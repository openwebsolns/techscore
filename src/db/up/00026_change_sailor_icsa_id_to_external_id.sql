-- Use external_id instead of icsa_id
ALTER TABLE sailor CHANGE COLUMN icsa_id external_id int(10) unsigned DEFAULT NULL;
