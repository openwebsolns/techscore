-- add RP as possible column for pub_update_sailor
ALTER TABLE pub_update_sailor CHANGE COLUMN activity activity enum('name','season','details','url','display','rp') NOT NULL default 'name';
