-- Use auto increment column as primary key for account table
--  this requies dropping all known foreign keys

-- drop foreign keys
ALTER TABLE account_conference  DROP FOREIGN KEY `account_conference_ibfk_2`;
ALTER TABLE account_school      DROP FOREIGN KEY `account_school_ibfk_3`;
ALTER TABLE burgee              DROP FOREIGN KEY `burgee_ibfk_2`;
ALTER TABLE message             DROP FOREIGN KEY `message_ibfk_1`;
ALTER TABLE message             DROP FOREIGN KEY `message_ibfk_2`;
ALTER TABLE race                DROP FOREIGN KEY `race_ibfk_3`;
ALTER TABLE race_order          DROP FOREIGN KEY `race_order_ibfk_1`;
ALTER TABLE regatta             DROP FOREIGN KEY `regatta_ibfk_2`;
ALTER TABLE regatta_document    DROP FOREIGN KEY `regatta_document_ibfk_2`;
ALTER TABLE scorer              DROP FOREIGN KEY `scorer_ibfk_3`;

-- create the columns
ALTER TABLE account_conference CHANGE COLUMN account account_old varchar(40) NOT NULL;
ALTER TABLE account_school CHANGE COLUMN account account_old varchar(40) NOT NULL;
ALTER TABLE burgee CHANGE COLUMN updated_by updated_by_old varchar(40) DEFAULT NULL;
ALTER TABLE message CHANGE COLUMN account account_old varchar(40) NOT NULL;
ALTER TABLE message CHANGE COLUMN sender sender_old varchar(40) DEFAULT NULL;
ALTER TABLE race CHANGE COLUMN scored_by scored_by_old varchar(40) DEFAULT NULL;
ALTER TABLE race_order CHANGE COLUMN author author_old varchar(40) NOT NULL;
ALTER TABLE regatta CHANGE COLUMN creator creator_old varchar(40) DEFAULT NULL;
ALTER TABLE regatta_document CHANGE COLUMN author author_old varchar(40) DEFAULT NULL;
ALTER TABLE scorer CHANGE COLUMN account account_old varchar(40) NOT NULL;

ALTER TABLE account_conference ADD COLUMN account int unsigned NOT NULL AFTER account_old;
ALTER TABLE account_school ADD COLUMN account int unsigned NOT NULL AFTER account_old;
ALTER TABLE burgee ADD COLUMN updated_by int unsigned DEFAULT NULL AFTER updated_by_old;
ALTER TABLE message ADD COLUMN account int unsigned NOT NULL AFTER account_old;
ALTER TABLE message ADD COLUMN sender int unsigned NULL AFTER sender_old;
ALTER TABLE race ADD COLUMN scored_by int unsigned DEFAULT NULL AFTER scored_by_old;
ALTER TABLE race_order ADD COLUMN author int unsigned NOT NULL AFTER author_old;
ALTER TABLE regatta ADD COLUMN creator int unsigned DEFAULT NULL AFTER creator_old;
ALTER TABLE regatta_document ADD COLUMN author int unsigned DEFAULT NULL AFTER author_old;
ALTER TABLE scorer ADD COLUMN account int unsigned NOT NULL AFTER account_old;

-- add new key
ALTER TABLE account DROP PRIMARY KEY, CHANGE COLUMN id email varchar(100) NOT NULL, ADD COLUMN id int unsigned auto_increment primary key FIRST, ADD UNIQUE KEY (email);

-- update child tables
UPDATE account, account_conference SET account_conference.account = account.id where account_old = account.email;
UPDATE account, account_school SET account_school.account = account.id where account_old = account.email;
UPDATE account, burgee SET burgee.updated_by = account.id where updated_by_old = account.email;
UPDATE account, message SET message.account = account.id where account_old = account.email;
UPDATE account, message SET message.sender = account.id where sender_old = account.email;
UPDATE account, race SET race.scored_by = account.id where scored_by_old = account.email;
UPDATE account, race_order SET race_order.author = account.id where author_old = account.email;
UPDATE account, regatta SET regatta.creator = account.id where creator_old = account.email;
UPDATE account, regatta_document SET regatta_document.author = account.id where author_old = account.email;
UPDATE account, scorer SET scorer.account = account.id where account_old = account.email;

-- drop unique keys
ALTER TABLE account_conference DROP KEY `account`;
ALTER TABLE account_school DROP KEY `account`;
ALTER TABLE scorer DROP KEY `account_2`;

-- drop old columns
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
ALTER TABLE account_conference ADD CONSTRAINT `fk_account_conference_account` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE account_school ADD CONSTRAINT `fk_account_school_account` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE burgee ADD CONSTRAINT `fk_burgee_updated_by` FOREIGN KEY (updated_by) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE message ADD CONSTRAINT `fk_message_account` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE message ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (sender) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE race ADD CONSTRAINT `fk_race_scored_by` FOREIGN KEY (scored_by) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE race_order ADD CONSTRAINT `fk_race_order_author` FOREIGN KEY (author) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE regatta ADD CONSTRAINT `fk_regatta_creator` FOREIGN KEY (creator) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE regatta_document ADD CONSTRAINT `fk_regatta_document_author` FOREIGN KEY (author) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE scorer ADD CONSTRAINT `fk_scorer_account` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- recreate unique keys
ALTER TABLE account_conference ADD UNIQUE KEY (account, conference);
ALTER TABLE account_school ADD UNIQUE KEY (account, school);
ALTER TABLE scorer ADD UNIQUE KEY (account, regatta);
