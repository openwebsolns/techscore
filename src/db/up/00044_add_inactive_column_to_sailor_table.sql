-- add `inactive` to sailor table to track when the sailor was "deleted"
-- use "inactive" to match column name used for regatta table
ALTER TABLE sailor ADD COLUMN `inactive` datetime DEFAULT NULL COMMENT 'Deleted sailor, to be removed by the system.';
