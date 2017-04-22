-- "wins" column for a team can be negative
ALTER TABLE team
  MODIFY COLUMN dt_wins MEDIUMINT(8) unsigned NULL DEFAULT NULL,
  MODIFY COLUMN dt_losses MEDIUMINT(8) unsigned NULL DEFAULT NULL
;
