-- remove sponsor column from season and regatta
ALTER TABLE season
  DROP FOREIGN KEY `fk_season_sponsor`,
  DROP COLUMN sponsor;

ALTER TABLE regatta
  DROP FOREIGN KEY `fk_regatta_sponsor`,
  DROP COLUMN sponsor;
