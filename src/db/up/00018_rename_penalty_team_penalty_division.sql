-- rename penalty_team to penalty_division, to avoid confusion with team racing
ALTER TABLE penalty_team RENAME TO penalty_division,
  DROP FOREIGN KEY `penalty_team_ibfk_1`,
  ADD CONSTRAINT `fk_penalty_division_team` FOREIGN KEY (team) REFERENCES team(id)
    ON DELETE CASCADE ON UPDATE CASCADE;
