-- An attendee is a sailor who is present at a regatta. It *should* be
-- the case that all participants (sailors in the RP form) are also
-- attendees. On the other hand, attendees that are not participants
-- go by a special name: "reserve".
CREATE TABLE attendee (
  id INT UNSIGNED AUTO_INCREMENT,
  regatta INT(5) NOT NULL,
  school VARCHAR(10) NOT NULL,
  sailor MEDIUMINT(9) NOT NULL,
  added_by INT(10) UNSIGNED NULL DEFAULT NULL,
  added_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_regatta_sailor (regatta, sailor),
  KEY regatta (regatta),
  KEY school (school),
  KEY sailor (sailor),
  KEY added_by (added_by),
  CONSTRAINT `fk_attendee_regatta` FOREIGN KEY (regatta) REFERENCES regatta(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendee_school` FOREIGN KEY (school) REFERENCES school(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendee_sailor` FOREIGN KEY (sailor) REFERENCES sailor(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendee_added_by` FOREIGN KEY (added_by) REFERENCES account(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
