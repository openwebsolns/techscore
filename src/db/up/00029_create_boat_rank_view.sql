CREATE VIEW boat_rank AS (
  SELECT boat.id, name, min_crews, max_crews, count(race.id) as num_races
  FROM boat, race
  WHERE boat.id = race.boat
  GROUP by boat
);
