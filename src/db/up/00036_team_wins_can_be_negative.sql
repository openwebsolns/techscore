-- "wins" column for a team can be negative, and decimal
ALTER TABLE team 
  MODIFY COLUMN dt_wins DECIMAL(5,2) NULL DEFAULT NULL,
  MODIFY COLUMN dt_losses DECIMAL(5,2) NULL DEFAULT NULL
;
