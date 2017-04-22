-- track RSS.D3 "discretionary" penalty to a team
CREATE TABLE reduced_wins_penalty (
  id INT UNSIGNED AUTO_INCREMENT,
  team INT(7) NOT NULL,
  race INT(7) NULL DEFAULT NULL,
  amount DECIMAL(4,2) NOT NULL,
  comments TEXT,
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by varchar(255) NULL,
  last_updated_on TIMESTAMP NULL DEFAULT NULL,
  last_updated_by varchar(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT `fk_reduced_wins_penalty_team` FOREIGN KEY (team) REFERENCES team(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reduced_wins_penalty_race` FOREIGN KEY (race) REFERENCES race(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
