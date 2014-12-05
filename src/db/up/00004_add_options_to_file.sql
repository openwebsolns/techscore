-- add a column to encapsulate options to pub_file
ALTER TABLE pub_file ADD COLUMN options text NULL DEFAULT NULL;
