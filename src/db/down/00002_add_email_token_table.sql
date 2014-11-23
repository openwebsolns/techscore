-- Remove token table, and transfer logic back to account table

ALTER TABLE account
  ADD COLUMN recovery_token varchar(64) NULL DEFAULT NULL,
  ADD COLUMN recovery_deadline datetime NULL DEFAULT NULL
;

-- transfer
UPDATE account, email_token SET
    account.recovery_token = email_token.id,
    account.recovery_deadline = email_token.deadline
  WHERE account.id = email_token.account
;

-- drop token table
DROP TABLE email_token;
