-- use account's e-mail address as primary key

-- cleanup data
DELETE FROM account where length(email) > 40;

-- drop foreign keys
ALTER TABLE account_conference  DROP FOREIGN KEY `fk_account_conference_account`;
ALTER TABLE account_school      DROP FOREIGN KEY `fk_account_school_account`;
ALTER TABLE burgee              DROP FOREIGN KEY `fk_burgee_updated_by`;
ALTER TABLE message             DROP FOREIGN KEY `fk_message_account`;
ALTER TABLE message             DROP FOREIGN KEY `fk_message_sender`;
ALTER TABLE race                DROP FOREIGN KEY `fk_race_scored_by`;
ALTER TABLE race_order          DROP FOREIGN KEY `fk_race_order_author`;
ALTER TABLE regatta             DROP FOREIGN KEY `fk_regatta_creator`;
ALTER TABLE regatta_document    DROP FOREIGN KEY `fk_regatta_document_author`;
ALTER TABLE scorer              DROP FOREIGN KEY `fk_scorer_account`;


-- create the columns
ALTER TABLE account_conference CHANGE COLUMN account account_old int unsigned NOT NULL;
ALTER TABLE account_school CHANGE COLUMN account account_old int unsigned NOT NULL;
ALTER TABLE burgee CHANGE COLUMN updated_by updated_by_old int unsigned DEFAULT NULL;
ALTER TABLE message CHANGE COLUMN account account_old int unsigned NOT NULL;
ALTER TABLE message CHANGE COLUMN sender sender_old int unsigned DEFAULT NULL;
ALTER TABLE race CHANGE COLUMN scored_by scored_by_old int unsigned DEFAULT NULL;
ALTER TABLE race_order CHANGE COLUMN author author_old int unsigned NOT NULL;
ALTER TABLE regatta CHANGE COLUMN creator creator_old int unsigned DEFAULT NULL;
ALTER TABLE regatta_document CHANGE COLUMN author author_old int unsigned DEFAULT NULL;
ALTER TABLE scorer CHANGE COLUMN account account_old int unsigned NOT NULL;

ALTER TABLE account_conference ADD COLUMN account varchar(40) NOT NULL AFTER account_old;
ALTER TABLE account_school ADD COLUMN account varchar(40) NOT NULL AFTER account_old;
ALTER TABLE burgee ADD COLUMN updated_by varchar(40) DEFAULT NULL AFTER updated_by_old;
ALTER TABLE message ADD COLUMN account varchar(40) NOT NULL AFTER account_old;
ALTER TABLE message ADD COLUMN sender varchar(40) NULL AFTER sender_old;
ALTER TABLE race ADD COLUMN scored_by varchar(40) DEFAULT NULL AFTER scored_by_old;
ALTER TABLE race_order ADD COLUMN author varchar(40) NOT NULL AFTER author_old;
ALTER TABLE regatta ADD COLUMN creator varchar(40) DEFAULT NULL AFTER creator_old;
ALTER TABLE regatta_document ADD COLUMN author varchar(40) DEFAULT NULL AFTER author_old;
ALTER TABLE scorer ADD COLUMN account varchar(40) NOT NULL AFTER account_old;


-- add new key
ALTER TABLE account DROP PRIMARY KEY, DROP KEY `email`, CHANGE COLUMN id id_old int unsigned not null, CHANGE COLUMN email id varchar(40) NOT NULL PRIMARY KEY;

-- update child tables
UPDATE account, account_conference SET account_conference.account = account.id where account_old = account.id_old;
UPDATE account, account_school SET account_school.account = account.id where account_old = account.id_old;
UPDATE account, burgee SET burgee.updated_by = account.id where updated_by_old = account.id_old;
UPDATE account, message SET message.account = account.id where account_old = account.id_old;
UPDATE account, message SET message.sender = account.id where sender_old = account.id_old;
UPDATE account, race SET race.scored_by = account.id where scored_by_old = account.id_old;
UPDATE account, race_order SET race_order.author = account.id where author_old = account.id_old;
UPDATE account, regatta SET regatta.creator = account.id where creator_old = account.id_old;
UPDATE account, regatta_document SET regatta_document.author = account.id where author_old = account.id_old;
UPDATE account, scorer SET scorer.account = account.id where account_old = account.id_old;

-- drop unique keys
ALTER TABLE account_conference DROP KEY `account`;
ALTER TABLE account_school DROP KEY `account`;
ALTER TABLE scorer DROP KEY `account`;


-- drop old columns
ALTER TABLE account DROP COLUMN id_old;
ALTER TABLE account_conference DROP COLUMN account_old;
ALTER TABLE account_school DROP COLUMN account_old;
ALTER TABLE burgee DROP COLUMN updated_by_old;
ALTER TABLE message DROP COLUMN account_old;
ALTER TABLE message DROP COLUMN sender_old;
ALTER TABLE race DROP COLUMN scored_by_old;
ALTER TABLE race_order DROP COLUMN author_old;
ALTER TABLE regatta DROP COLUMN creator_old;
ALTER TABLE regatta_document DROP COLUMN author_old;
ALTER TABLE scorer DROP COLUMN account_old;


-- add new foreign keys
ALTER TABLE account_conference ADD CONSTRAINT `account_conference_ibfk_2` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE account_school ADD CONSTRAINT `account_school_ibfk_3` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE burgee ADD CONSTRAINT `burgee_ibfk_2` FOREIGN KEY (updated_by) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE message ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE message ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (sender) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE race ADD CONSTRAINT `race_ibfk_3` FOREIGN KEY (scored_by) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE race_order ADD CONSTRAINT `race_order_ibfk_1` FOREIGN KEY (author) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE regatta ADD CONSTRAINT `regatta_ibfk_2` FOREIGN KEY (creator) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE regatta_document ADD CONSTRAINT `regatta_document_ibfk_2` FOREIGN KEY (author) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE scorer ADD CONSTRAINT `scorer_ibfk_3` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;


-- recreate unique keys
ALTER TABLE account_conference ADD UNIQUE KEY `account` (account, conference);
ALTER TABLE account_school ADD UNIQUE KEY `account` (account, school);
ALTER TABLE scorer ADD UNIQUE KEY `account_2` (account, regatta);
