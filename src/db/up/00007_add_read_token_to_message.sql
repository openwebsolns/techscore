-- This token is used to track the copy of the message that was e-mailed
-- to the user in the message. This makes it possible to check if the user
-- has read the message, by seeing if a request with this secret token
-- is made
ALTER TABLE message ADD COLUMN read_token varchar(40) NULL DEFAULT NULL;

