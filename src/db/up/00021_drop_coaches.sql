-- drop coaches
DELETE FROM sailor WHERE `ROLE` = 'coach';
ALTER TABLE sailor CHANGE COLUMN `ROLE` `ROLE` VARCHAR(40) NOT NULL;
UPDATE sailor SET `ROLE` = 'student';
