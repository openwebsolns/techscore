-- add `email_inbox_status` to account table to track
-- whether the account can receive e-mails
ALTER TABLE account DROP COLUMN email_inbox_status;
