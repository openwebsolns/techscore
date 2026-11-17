-- drop `inactive` column from sailor table; any entry that has a non-null
-- value would have been removed by the system, so remove it manually
DELETE FROM sailor where `inactive` IS NOT NULL;
ALTER TABLE sailor DROP COLUMN `inactive`;
