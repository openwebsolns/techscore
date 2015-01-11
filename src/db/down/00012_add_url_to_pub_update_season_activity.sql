-- remove url from season's activity
ALTER TABLE pub_update_season CHANGE COLUMN activity activity enum('regatta','details','front','404','school404') NOT NULL DEFAULT 'regatta';
