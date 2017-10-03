-- This is a one-way data door: populate sailor_season based on actual
-- attendance to a regatta, and not just based on explicit selection.
-- This is a necessary data procedure to assert that RP forms only show
-- the sailors that were active *for that season*.

CREATE TEMPORARY TABLE actual_sailor_season AS (
  SELECT DISTINCT attendee.sailor, regatta.dt_season
  FROM attendee
  INNER JOIN rp ON attendee.id = rp.attendee
  INNER JOIN race ON race.id = rp.race
  INNER JOIN regatta ON regatta.id = race.regatta
  WHERE rp.attendee IS NOT NULL
  AND regatta.private IS NULL
);

INSERT INTO sailor_season (sailor, season) (
  SELECT * FROM actual_sailor_season
  WHERE (sailor, dt_season) NOT IN (
    SELECT sailor, season FROM sailor_season
  )
);

DROP TABLE actual_sailor_season;
