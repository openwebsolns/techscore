-- Limited view of the database for scorers
-- To use this file: replace the following variables:
--
--   {db}   = database name
--   {user} = username
--   {host} = host
--

-- create view public_regatta as (select id, name, nick, start_time, end_date, venue, type, finalized, scoring, participant, host_venue, sponsor, creator, dt_num_divisions, dt_num_races, dt_hosts, dt_confs, dt_boats, dt_singlehanded, dt_season, dt_status from regatta where private is null and inactive is null);

-- create view active_sailor as (select id, external_id, school, last_name, first_name, year, gender, ROLE, regatta_added, sync_log, created_on, created_by, last_updated_on, last_updated_by from sailor where active is not null);

-- create view active_school as (select id, name, nick_name, url, conference, city, state, burgee, burgee_small, burgee_square, sync_log, created_on, created_by, last_updated_on, last_updated_by from school where inactive is null);

-- create view active_regatta_type as (select id, title, description, rank, mail_lists, tweet_summary from type where inactive is null);

--
-- Grant statements
--
GRANT SELECT (id, team, sailor, added_on) ON {db}.attendee TO {user}@{host};
GRANT SELECT (id, name, min_crews, max_crews) ON {db}.boat TO {user}@{host};
GRANT SELECT (id, name) ON {db}.conference TO {user}@{host};
GRANT SELECT (id, regatta, summary_date, summary) ON {db}.daily_summary TO {user}@{host};
GRANT SELECT (id, team_division, sailor, boat_role, race_nums, rank, explanation) ON {db}.dt_rp TO {user}@{host};
GRANT SELECT (id, team, division, rank, explanation, penalty, comments, score, wins, losses, ties) ON {db}.dt_team_division TO
GRANT SELECT (id, race, team, entered, earned, score, explanation) ON {db}.finish TO {user}@{host};
GRANT SELECT (id, finish, type, amount, displace, comments) ON {db}.finish_modifier TO {user}@{host};
GRANT SELECT (id, regatta, division_order, rotation_type, rotation_style, races_per_set, sails_list) ON {db}.fleet_rotation TO
GRANT SELECT (id, regatta, school) ON {db}.host_school TO {user}@{host};
GRANT SELECT (id, race, observation, observer, noted_at) ON {db}.observation TO {user}@{host};
GRANT SELECT (id, team, division, type, comments) ON {db}.penalty_division TO {user}@{host};
GRANT SELECT (id, regatta, division, number, scored_day, boat, tr_team1, tr_team2, tr_ignore1, tr_ignore2) ON {db}.race TO {user}@{host};
GRANT SELECT (id, name, nick, start_time, end_date, venue, type, finalized, scoring, participant, host_venue, dt_num_divisions, dt_num_races, dt_hosts, dt_confs, dt_boats, dt_singlehanded, dt_season, dt_status) ON {db}.public_regatta TO {user}@{host};
GRANT SELECT (id, team, name) ON {db}.representative TO {user}@{host};
GRANT SELECT (id, race, team, sail, color) ON {db}.rotation TO {user}@{host};
GRANT SELECT (id, regatta, title, relative_order, round_group, num_teams, num_boats, race_order, rotation, rotation_frequency, boat, sailoff_for_round) ON {db}.round TO {user}@{host};
GRANT SELECT (id) ON {db}.round_group TO {user}@{host};
GRANT SELECT (id, round, team, seed, original_round) ON {db}.round_seed TO {user}@{host};
GRANT SELECT (id, master, slave, num_teams) ON {db}.round_slave TO {user}@{host};
GRANT SELECT (id, round, team1, team2, boat) ON {db}.round_template TO {user}@{host};
GRANT SELECT (id, race, team, boat_role, attendee) ON {db}.rp TO {user}@{host};
GRANT SELECT (id, external_id, school, last_name, first_name, year, gender) ON {db}.active_sailor TO {user}@{host};
GRANT SELECT (id, name, nick_name, conference, city, state) ON {db}.active_school TO {user}@{host};
GRANT SELECT (id, url, season, start_date, end_date) ON {db}.season TO {user}@{host};
GRANT SELECT (id, regatta, school, name, lock_rank, rank_group, dt_rank, dt_explanation, dt_score, dt_wins, dt_losses, dt_ties) ON {db}.team TO {user}@{host};
GRANT SELECT (id, title, description, rank) ON {db}.active_regatta_type TO {user}@{host};
GRANT SELECT (id, name, address, city, state, zipcode, weather_station_id) ON {db}.venue TO {user}@{host};

--
-- Dump database
--
SELECT slave, id, master, num_teams FROM {db}.round_slave;
SELECT noted_at, id, race, observation, observer FROM {db}.observation;
SELECT sailor, boat_role, rank, explanation, id, race_nums, team_division FROM {db}.dt_rp;
SELECT sailor, added_on, team, id FROM {db}.attendee;
SELECT sail, color, team, race, id FROM {db}.rotation;
SELECT id, city, name, conference, state, nick_name FROM {db}.active_school;
SELECT title, description, rank, id FROM {db}.active_regatta_type;
SELECT id, name, team FROM {db}.representative;
SELECT min_crews, id, name, max_crews FROM {db}.boat;
SELECT seed, id, team, original_round, round FROM {db}.round_seed;
SELECT boat, id, team2, round, team1 FROM {db}.round_template;
SELECT summary, id, summary_date, regatta FROM {db}.daily_summary;
SELECT division, team, type, comments, id FROM {db}.penalty_division;
SELECT name, id FROM {db}.conference;
SELECT season, id, start_date, end_date, url FROM {db}.season;
SELECT dt_ties, dt_explanation, dt_score, dt_losses, rank_group, dt_rank, name, id, school, lock_rank, dt_wins, regatta FROM {db}.team;
SELECT race, boat_role, team, attendee, id FROM {db}.rp;
SELECT school, id, regatta FROM {db}.host_school;
SELECT dt_status, dt_boats, end_date, dt_hosts, venue, dt_num_races, host_venue, dt_confs, scoring, id, type, dt_num_divisions, dt_season, finalized, name, dt_singlehanded, participant, nick, start_time FROM {db}.public_regatta;
SELECT tr_ignore2, boat, tr_ignore1, number, tr_team2, division, tr_team1, scored_day, regatta, id FROM {db}.race;
SELECT race, entered, explanation, earned, score, id, team FROM {db}.finish;
SELECT sails_list, division_order, regatta, races_per_set, rotation_type, id, rotation_style FROM {db}.fleet_rotation;
SELECT race_order, boat, round_group, num_teams, title, id, regatta, relative_order, rotation, num_boats, rotation_frequency, sailoff_for_round FROM {db}.round;
SELECT finish, amount, type, comments, id, displace FROM {db}.finish_modifier;
SELECT losses, comments, ties, explanation, score, division, team, id, penalty, rank, wins FROM {db}.dt_team_division;
SELECT id FROM {db}.round_group;
SELECT zipcode, address, city, id, weather_station_id, state, name FROM {db}.venue;
SELECT school, external_id, first_name, id, year, gender, last_name FROM {db}.active_sailor;
