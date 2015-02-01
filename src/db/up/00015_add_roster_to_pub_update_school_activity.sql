-- roster can change for a school
ALTER TABLE pub_update_school CHANGE COLUMN activity activity enum('burgee','season','details','url','roster') NOT NULL DEFAULT 'burgee';
