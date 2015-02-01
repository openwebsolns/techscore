-- url can change for a season
ALTER TABLE pub_update_sailor CHANGE COLUMN activity activity enum('name','season','details','url','display') NOT NULL DEFAULT 'name';
