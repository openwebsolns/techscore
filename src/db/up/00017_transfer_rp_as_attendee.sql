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

INSERT INTO attendee (team, sailor)
  (SELECT DISTINCT rp.team, rp.sailor
   FROM rp
   WHERE rp.sailor IS NOT NULL
  )
;

UPDATE rp, team, attendee 
 SET rp.attendee = attendee.id
 WHERE rp.team = attendee.team
   AND rp.sailor IS NOT NULL
   AND rp.sailor = attendee.sailor
;

ALTER TABLE rp DROP FOREIGN KEY `rp_ibfk_4`, DROP COLUMN sailor;

