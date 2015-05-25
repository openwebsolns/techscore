-- Increase limit to 50 characters
ALTER TABLE regatta CHANGE COLUMN name name varchar(50) NOT NULL, CHANGE COLUMN nick nick varchar(255) DEFAULT NULL;
