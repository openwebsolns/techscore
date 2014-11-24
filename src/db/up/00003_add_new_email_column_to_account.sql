-- add a column to store would-be new email for account
ALTER TABLE account ADD COLUMN new_email varchar(100) NULL DEFAULT NULL after email;
