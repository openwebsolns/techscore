-- Add link to student_profile in sailor table to track
-- ownership

ALTER TABLE sailor
  ADD COLUMN student_profile INT(10) UNSIGNED NULL DEFAULT NULL AFTER url,
  ADD FOREIGN KEY `fk_sailor_student_profile` (student_profile) REFERENCES student_profile(id) ON DELETE SET NULL ON UPDATE CASCADE
;
