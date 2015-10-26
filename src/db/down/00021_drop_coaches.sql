-- re-enable coaches
ALTER TABLE sailor CHANGE COLUMN `ROLE` `ROLE` ENUM('student', 'coach') NOT NULL DEFAULT 'student';
