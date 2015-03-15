-- Instead of an RP entry existing on a sailor, RP entries are registered
-- on attendee objects
ALTER TABLE rp
  ADD COLUMN attendee INT UNSIGNED DEFAULT NULL,
  ADD KEY attendee (attendee),
  ADD CONSTRAINT `fk_rp_attendee` FOREIGN KEY (attendee) REFERENCES attendee(id)
      ON DELETE SET NULL ON UPDATE CASCADE
;

-- before starting, delete any sailors from rp with no boat role
DELETE FROM rp WHERE boat_role = '';

INSERT INTO attendee (regatta, school, sailor)
  (SELECT DISTINCT team.regatta, team.school, rp.sailor
   FROM rp INNER JOIN team ON (rp.team = team.id)
   WHERE rp.sailor IS NOT NULL
  )
;

UPDATE rp, team, attendee 
 SET rp.attendee = attendee.id
 WHERE rp.team = team.id
   AND rp.sailor IS NOT NULL
   AND team.school = attendee.school
   AND team.regatta = attendee.regatta
   AND rp.sailor = attendee.sailor
;

ALTER TABLE rp DROP FOREIGN KEY `rp_ibfk_4`, DROP COLUMN sailor;

