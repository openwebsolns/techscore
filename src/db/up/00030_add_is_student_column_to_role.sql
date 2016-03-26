-- we need to know which role belongs to students
ALTER TABLE `role` ADD COLUMN is_student tinyint(4) DEFAULT NULL;
