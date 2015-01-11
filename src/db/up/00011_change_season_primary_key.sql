-- Use regular auto increment ID for season objects

-- 1. drop foreign keys
ALTER TABLE pub_update_conference DROP FOREIGN KEY `pub_update_conference_ibfk_2`;
ALTER TABLE pub_update_school     DROP FOREIGN KEY `pub_update_school_ibfk_2`;
ALTER TABLE regatta               DROP FOREIGN KEY `regatta_ibfk_4`;
ALTER TABLE sailor_season         DROP FOREIGN KEY `sailor_season_ibfk_2`;
ALTER TABLE school_season         DROP FOREIGN KEY `school_season_ibfk_2`;

-- 2. create temporary join columns
ALTER TABLE pub_update_conference CHANGE COLUMN season season_old varchar(3) NULL DEFAULT NULL;
ALTER TABLE pub_update_school CHANGE COLUMN season season_old varchar(3) NULL DEFAULT NULL;
ALTER TABLE regatta CHANGE COLUMN dt_season dt_season_old varchar(3) NULL DEFAULT NULL;
ALTER TABLE sailor_season CHANGE COLUMN season season_old varchar(3) NOT NULL;
ALTER TABLE school_season CHANGE COLUMN season season_old varchar(3) NOT NULL;

-- 3. create new columns
ALTER TABLE pub_update_conference ADD COLUMN season mediumint unsigned NULL DEFAULT NULL after season_old;
ALTER TABLE pub_update_school ADD COLUMN season mediumint unsigned NULL DEFAULT NULL after season_old;
ALTER TABLE regatta ADD COLUMN dt_season mediumint unsigned NULL DEFAULT NULL after dt_season_old;
ALTER TABLE sailor_season ADD COLUMN season mediumint unsigned NOT NULL after season_old;
ALTER TABLE school_season ADD COLUMN season mediumint unsigned NOT NULL after season_old;

-- 4. migrate primary key
ALTER TABLE season DROP PRIMARY KEY, CHANGE COLUMN id url varchar(3) NOT NULL, ADD COLUMN id mediumint unsigned auto_increment PRIMARY KEY FIRST, ADD CONSTRAINT `uniq_season_url` UNIQUE KEY (url);

-- 5. update dependent tables
UPDATE season, pub_update_conference SET pub_update_conference.season = season.id WHERE pub_update_conference.season_old = season.url;
UPDATE season, pub_update_school SET pub_update_school.season = season.id WHERE pub_update_school.season_old = season.url;
UPDATE season, regatta SET regatta.dt_season = season.id WHERE regatta.dt_season_old = season.url;
UPDATE season, sailor_season SET sailor_season.season = season.id WHERE sailor_season.season_old = season.url;
UPDATE season, school_season SET school_season.season = season.id WHERE school_season.season_old = season.url;

-- 6. drop old columns
ALTER TABLE pub_update_conference DROP COLUMN season_old;
ALTER TABLE pub_update_school DROP COLUMN season_old;
ALTER TABLE regatta DROP COLUMN dt_season_old;
ALTER TABLE sailor_season DROP COLUMN season_old;
ALTER TABLE school_season DROP COLUMN season_old;

-- 7. add new foreign keys
ALTER TABLE pub_update_conference ADD CONSTRAINT `fk_pub_update_conference_season` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE pub_update_school ADD CONSTRAINT `fk_pub_update_school_season` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE regatta ADD CONSTRAINT `fk_regatta_dt_season` FOREIGN KEY (dt_season) REFERENCES season(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE sailor_season ADD CONSTRAINT `fk_sailor_season_season` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE school_season ADD CONSTRAINT `fk_school_season_season` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
