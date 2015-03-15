-- Just sailor, and not attendees, in RP.
ALTER TABLE rp
  ADD COLUMN sailor MEDIUMINT(9) DEFAULT NULL,
  ADD KEY sailor (sailor),
  ADD CONSTRAINT `rp_ibfk_4` FOREIGN KEY (sailor) REFERENCES sailor(id)
      ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE rp, attendee
  SET rp.sailor = attendee.sailor
  WHERE rp.attendee = attendee.id
;

DELETE FROM attendee;

ALTER TABLE rp DROP FOREIGN KEY `fk_rp_attendee`, DROP COLUMN attendee;
