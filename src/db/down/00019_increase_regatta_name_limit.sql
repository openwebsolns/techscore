-- Increase limit to 50 characters
UPDATE regatta SET name = substring(name, 0, 40), nick = substring(nick, 0, 40);
ALTER TABLE regatta CHANGE COLUMN name name varchar(50) NOT NULL, CHANGE COLUMN nick nick varchar(255) DEFAULT NULL;
