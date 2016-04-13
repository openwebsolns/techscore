-- Add column to track when sailor EULA has been "read"
ALTER TABLE account
  ADD COLUMN sailor_eula_read_on datetime DEFAULT NULL after message
;
