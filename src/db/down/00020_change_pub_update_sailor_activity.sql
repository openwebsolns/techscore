ALTER TABLE pub_update_sailor CHANGE COLUMN activity activity enum('name','season','details','url','display') NOT NULL default 'name';
