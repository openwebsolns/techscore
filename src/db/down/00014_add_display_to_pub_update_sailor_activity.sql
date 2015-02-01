-- url can change for a season
DELETE FROM pub_update_sailor WHERE activity = 'display';
ALTER TABLE pub_update_sailor CHANGE COLUMN activity activity enum('name','season','details','url') NOT NULL DEFAULT 'name';
