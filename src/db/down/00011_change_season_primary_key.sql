-- Use "url" as primary key for season objects

-- 1. drop foreign keys
ALTER TABLE pub_update_conference DROP FOREIGN KEY `fk_pub_update_conference_season`;
ALTER TABLE pub_update_school     DROP FOREIGN KEY `fk_pub_update_school_season`;
ALTER TABLE regatta               DROP FOREIGN KEY `fk_regatta_dt_season`;
ALTER TABLE sailor_season         DROP FOREIGN KEY `fk_sailor_season_season`;
ALTER TABLE school_season         DROP FOREIGN KEY `fk_school_season_season`;

-- 2. create temporary join columns
ALTER TABLE pub_update_conference CHANGE COLUMN season season_old mediumint unsigned NULL DEFAULT NULL;
ALTER TABLE pub_update_school CHANGE COLUMN season season_old mediumint unsigned NULL DEFAULT NULL;
ALTER TABLE regatta CHANGE COLUMN dt_season dt_season_old mediumint unsigned NULL DEFAULT NULL;
ALTER TABLE sailor_season CHANGE COLUMN season season_old mediumint unsigned NOT NULL;
ALTER TABLE school_season CHANGE COLUMN season season_old mediumint unsigned NOT NULL;

-- 3. create new columns
ALTER TABLE pub_update_conference ADD COLUMN season varchar(3) NULL DEFAULT NULL after season_old;
ALTER TABLE pub_update_school ADD COLUMN season varchar(3) NULL DEFAULT NULL after season_old;
ALTER TABLE regatta ADD COLUMN dt_season varchar(3) NULL DEFAULT NULL after dt_season_old;
ALTER TABLE sailor_season ADD COLUMN season varchar(3) NOT NULL after season_old;
ALTER TABLE school_season ADD COLUMN season varchar(3) NOT NULL after season_old;

-- 4. migrate primary key
ALTER TABLE season DROP PRIMARY KEY,
      DROP KEY `uniq_season_url`,
      CHANGE COLUMN id id_old mediumint unsigned,
      CHANGE COLUMN url id varchar(3) NOT NULL PRIMARY KEY;

-- 5. update dependent tables
UPDATE season, pub_update_conference SET pub_update_conference.season = season.id WHERE pub_update_conference.season_old = season.id_old;
UPDATE season, pub_update_school SET pub_update_school.season = season.id WHERE pub_update_school.season_old = season.id_old;
UPDATE season, regatta SET regatta.dt_season = season.id WHERE regatta.dt_season_old = season.id_old;
UPDATE season, sailor_season SET sailor_season.season = season.id WHERE sailor_season.season_old = season.id_old;
UPDATE season, school_season SET school_season.season = season.id WHERE school_season.season_old = season.id_old;

-- 6. drop old columns
ALTER TABLE pub_update_conference DROP COLUMN season_old;
ALTER TABLE pub_update_school DROP COLUMN season_old;
ALTER TABLE regatta DROP COLUMN dt_season_old;
ALTER TABLE sailor_season DROP COLUMN season_old;
ALTER TABLE school_season DROP COLUMN season_old;

-- 7. add new foreign keys
ALTER TABLE pub_update_conference ADD CONSTRAINT `pub_update_conference_ibfk_2` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE pub_update_school ADD CONSTRAINT `pub_update_school_ibfk_2` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE regatta ADD CONSTRAINT `regatta_ibfk_4` FOREIGN KEY (dt_season) REFERENCES season(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE sailor_season ADD CONSTRAINT `sailor_season_ibfk_2` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE school_season ADD CONSTRAINT `school_season_ibfk_2` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE;


-- 8. cleanup
ALTER TABLE season DROP COLUMN id_old;
