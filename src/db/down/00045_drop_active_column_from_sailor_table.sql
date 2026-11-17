-- Re-insert active column
ALTER TABLE `sailor` ADD COLUMN `active` tinyint(4) DEFAULT NULL;

-- Attempt to refill active column based on sailor_season entries
-- See also 00038_add_sailor_season_entries_based_on_attendance.sql
UPDATE `sailor` SET `active` = 1 WHERE `id` IN (
  SELECT `sailor`
  FROM `sailor_season`
  WHERE `season` IN (
    SELECT `id`
    FROM `season`
    WHERE `start_date` <= NOW() and `end_date` >= NOW()
  )
);
