-- rename penalty_team to penalty_division, to avoid confusion with team racing
ALTER TABLE penalty_division RENAME TO penalty_team,
  DROP FOREIGN KEY `fk_penalty_division_team`,
  ADD CONSTRAINT `penalty_team_ibfk_1` FOREIGN KEY (team) REFERENCES team(id)
    ON DELETE CASCADE ON UPDATE CASCADE;
