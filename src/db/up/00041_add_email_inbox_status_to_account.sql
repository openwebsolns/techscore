-- add `email_inbox_status` to account table to track
-- whether the account can receive e-mails
ALTER TABLE account ADD COLUMN email_inbox_status enum('receiving', 'bouncing') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'receiving';
