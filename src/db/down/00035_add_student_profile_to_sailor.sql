-- Remove link to student_profile

ALTER TABLE sailor
  DROP FOREIGN KEY `fk_sailor_student_profile`,
  DROP COLUMN student_profile
;
