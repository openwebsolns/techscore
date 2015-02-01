-- roster can change for a school
DELETE FROM pub_update_school WHERE activity = 'roster';
ALTER TABLE pub_update_school CHANGE COLUMN activity activity enum('burgee','season','details','url') NOT NULL DEFAULT 'burgee';
