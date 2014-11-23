-- Add table to track email tokens, and transfer logic from account table

CREATE TABLE email_token (
  id varchar(64) NOT NULL,
  account int unsigned not null,
  email varchar(100) not null,
  deadline datetime not null,
  PRIMARY KEY (id),
  KEY account (account),
  CONSTRAINT `fk_email_token_account` FOREIGN KEY (account) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- transfer over
INSERT INTO email_token (
  SELECT recovery_token, id, email, recovery_deadline FROM account WHERE recovery_token IS NOT NULL
);


-- remove from account table
ALTER TABLE account
  DROP COLUMN recovery_token,
  DROP COLUMN recovery_deadline
;
