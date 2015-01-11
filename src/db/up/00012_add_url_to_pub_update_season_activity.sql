-- url can change for a season
ALTER TABLE pub_update_season CHANGE COLUMN activity activity enum('regatta','details','front','404','school404','url') NOT NULL DEFAULT 'regatta';
