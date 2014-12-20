-- add optional sponsor field to both season and regatta tables
ALTER TABLE season
  ADD COLUMN sponsor tinyint(3) unsigned NULL DEFAULT NULL,
  ADD CONSTRAINT `fk_season_sponsor`
    FOREIGN KEY (sponsor)
    REFERENCES pub_sponsor(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

ALTER TABLE regatta
  ADD COLUMN sponsor tinyint(3) unsigned NULL DEFAULT NULL AFTER host_venue,
  ADD CONSTRAINT `fk_regatta_sponsor`
    FOREIGN KEY (sponsor)
    REFERENCES pub_sponsor(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
