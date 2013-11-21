create table season (id int primary key auto_increment, season enum ('fall', 'winter', 'spring', 'summer') default 'fall', start_date date not null, end_date date not null) engine=innodb;

-- Changes related to public API
drop table score_update;
CREATE TABLE `pub_update_season` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` varchar(3) NOT NULL,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `season` (`season`)
) ENGINE=InnoDB;
CREATE TABLE `pub_update_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(11) NOT NULL,
  `activity` enum('rotation','score') NOT NULL DEFAULT 'score',
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
CREATE TABLE `pub_update_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request` int(11) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `return_code` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `request` (`request`),
  CONSTRAINT `pub_update_log_ibfk_1` FOREIGN KEY (`request`) REFERENCES `pub_update_request` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

alter table pub_update_log add column return_mess varchar(255) default "";

-- great for public regatta synchronization
alter table pub_update_request change column activity activity enum('rotation', 'score', 'rp', 'finalized') not null default 'score';
alter table dt_score change column explanation explanation text default null;
alter table dt_regatta change column season season varchar(3) not null;
alter table finish add column `place` text default NULL,
  add column `score` int(3) default NULL,
  add column `explanation` text default null;
update finish, score set finish.place = score.place, finish.score = score.score, finish.explanation = score.explanation where finish.id = score.finish;
drop table score;
alter table dt_regatta add column status varchar(10) default null;
-- redo the conferences table --
alter table school drop foreign key school_ibfk_1;
alter table conference drop primary key, change column id id varchar(8) not null, add primary key (id), add column old_id int;
update conference set old_id = id;
update conference set id = nick;
alter table school change column conference conference varchar(8) not null;
update school, conference set school.conference = conference.id where school.conference = conference.old_id;
alter table school add foreign key (conference) references conference(id) on delete cascade on update cascade;
 alter table conference drop column old_id, drop column nick;

-- make races better to avoid joins --
alter table race drop column wind_mph, drop column wind_gust_mph, drop column wind_dir, drop column temp_f;
alter table race add column number tinyint unsigned not null after division;
update race, race_num set race.number = race_num.number where race.id = race_num.id;
alter table race add unique key (regatta, division, number);

-- fix issues with rp --
alter table rp drop foreign key rp_ibfk_3;
alter table rotation drop key race_sail;

-- meld the finish, penalty, and handicap tables --
alter table finish add column penalty enum('DSQ', 'RAF', 'OCS', 'DNF', 'DNS', 'BKD', 'RDG', 'BYE') default null after entered, add column amount tinyint not null default 0 comment "Non-positive for assigned, otherwise as appropriate for the penalty" after penalty, add column comments text default null after amount;
update finish, handicap set finish.penalty = handicap.type, finish.amount = handicap.amount, finish.comments = handicap.comments where finish.id = handicap.finish;
update finish, penalty set finish.penalty = penalty.type, finish.amount = -1, finish.comments = penalty.comments where finish.id = penalty.finish;
alter table finish drop column place;
drop table penalty; drop table handicap;
alter table finish add column displace bit default null after amount;

-- add an earned amount field to finish (rather: score) for average finishes
-- so that they are not penalized more than they need to be (agh!)
alter table finish add column earned tinyint unsigned default null comment "Minimum that an average score can earn." after amount;

-- make dt_regatta independent from rest of schema
alter table dt_regatta drop foreign key dt_regatta_ibfk_1;
alter table dt_regatta change column id id int not null;

-- improve public site, again
drop table dt_score;
alter table pub_update_request change column activity activity enum('rotation', 'score', 'rp', 'details', 'summary') default 'score' comment "What changed and needs to be updated?";
drop table dt_rp;

-- sailor api
alter table sailor add column gender enum('M','F') not null default 'M';
alter table regatta add column participant enum('women', 'coed') not null default 'coed';
alter table dt_regatta add column participant enum('women', 'coed') not null default 'coed';

-- removes pesky backslashes from names. Make sure to identify the affected ones before doing this
update regatta set name = replace(name, '\\', '');

-- to further complicate myself, keep track of team ranks within divisions --
CREATE TABLE `dt_team_division` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team` int(11) NOT NULL,
  `division` enum('A','B','C','D') DEFAULT NULL,
  `rank` tinyint(3) unsigned NOT NULL,
  `explanation` tinytext,
  `penalty` enum('MRP','PFD','LOP','GDQ') DEFAULT NULL,
  `comments` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team` (`team`,`division`),
  CONSTRAINT `dt_team_division_ibfk_3` FOREIGN KEY (`team`) REFERENCES `dt_team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Rank teams within divisions, and account for possible penalt'

-- temporary sailors --
alter table sailor add column regatta_added int default null comment "For temp sailors, regatta when it was added.";
alter table sailor add foreign key (regatta_added) references regatta(id) on delete set null on update cascade;

-- host schools --
CREATE TABLE `host_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(11) NOT NULL,
  `school` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `regatta` (`regatta`,`school`),
  KEY `school` (`school`),
  CONSTRAINT `host_school_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `host_school_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
insert into host_school (school, regatta) (select distinct account.school, host.regatta from host inner join account on (host.account = account.username) where host.principal > 0);
CREATE TABLE `account_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account` varchar(40) NOT NULL,
  `school` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`,`school`),
  KEY `school` (`school`),
  CONSTRAINT `account_school_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `account_school_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- track regatta creators --
alter table regatta add column creator varchar(40) default null;
update regatta, host set regatta.creator = host.account where regatta.id = host.regatta and host.principal > 0;
select count(*) from regatta where creator = "";

-- official regatta types --
alter table regatta change column type type enum('conference', 'intersectional', 'championship', 'personal', 'two-conference', 'conference-championship', 'promotional') default 'conference';
alter table dt_regatta change column type type enum('conference', 'intersectional', 'championship', 'personal', 'two-conference', 'conference-championship', 'promotional') default 'conference';

-- data RPs --
create table dt_rp (id int primary key auto_increment, team_division int not null, sailor int not null, boat_role enum('skipper', 'crew') not null default 'skipper', race_nums text not null);
alter table dt_rp add foreign key (team_division) references dt_team_division(id) on delete cascade on update cascade;
insert into dt_rp (team_division, sailor, boat_role, race_nums) (select dt_team_division.id, rp.sailor, rp.boat_role, group_concat(race.number order by number separator ',') as race_nums from rp inner join race on (rp.race = race.id) inner join dt_team_division on (dt_team_division.team = rp.team) group by dt_team_division.id, rp.sailor, rp.boat_role);
truncate table dt_rp;
insert into dt_rp (team_division, sailor, boat_role, race_nums) (select dt_team_division.id, rp.sailor, rp.boat_role, group_concat(race.number order by number separator ',') as race_nums from rp inner join race on (rp.race = race.id) inner join dt_team_division on dt_team_division.team = rp.team where dt_team_division.division = race.division group by dt_team_division.id, rp.sailor, rp.boat_role);

-- provide optional argument to update requests --
alter table pub_update_request add column argument varchar(10) default null comment "Optional activity describer";

-- active sailors --
alter table sailor add column active tinyint default null;

-- rename account ID field from username to id --
alter table account_school drop foreign key account_school_ibfk_1;
alter table burgee drop foreign key burgee_ibfk_2;
alter table host drop foreign key host_ibfk_1;
alter table message drop foreign key message_ibfk_1;
alter table race drop foreign key race_ibfk_3;

alter table account drop primary key;
alter table account change column username id varchar(40) not null primary key;

alter table account_school add foreign key (account) references account(id) on delete cascade on update cascade;
alter table burgee add foreign key (updated_by) references account(id) on delete set null on update cascade;
alter table host add foreign key (account) references account(id) on delete cascade on update cascade;
alter table message add foreign key (account) references account(id) on delete cascade on update cascade;
alter table race add foreign key (scored_by) references account(id) on delete set null on update cascade;

alter table message change column created created timestamp default current_timestamp;

-- outbox: for sending message in a more Enterprise-y way --
CREATE TABLE `outbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` varchar(40) NOT NULL,
  `queue_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recipients` enum('all','conferences','roles') NOT NULL DEFAULT 'all',
  `arguments` varchar(100) DEFAULT NULL COMMENT 'Comma-delimited arguments pertaining to recipients',
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `copy_sender` tinyint(4) DEFAULT NULL,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender` (`sender`)
) ENGINE=innodb;

-- Migrate burgee --
alter table burgee drop foreign key burgee_ibfk_1, drop primary key;
alter table burgee add column id int primary key auto_increment first;
alter table burgee add foreign key (school) references school(id) on delete cascade on update cascade;

update school set burgee = null;
alter table school change column burgee burgee int default null, add foreign key (burgee) references burgee(id) on delete set null on update cascade;

alter table account change column is_admin admin tinyint not null default 0;

-- Fix host table: add ID PK --
alter table host add key (account);
alter table host drop primary key;
alter table host add column id int auto_increment primary key first;
alter table host add unique key (account, regatta);

-- Team name prefs --
alter table team_name_prefs add column id int auto_increment primary key;

-- Rotation (sail) --
alter table rotation add column id int auto_increment primary key first;

-- Team Penalty --
alter table penalty_team drop primary key, add column id int auto_increment primary key first, add unique key (team, division);

-- Scorers vs. hosts --
alter table host rename to scorer;

-- Representatives --
alter table representative add column id int auto_increment primary key first;

-- RP Form --
alter table rp_form drop primary key, drop foreign key rp_form_ibfk_1, change column regatta id int auto_increment primary key, add foreign key (id) references regatta(id) on delete cascade on update cascade;

-- Regatta creator --
update regatta set creator = null where creator not in (select id from account);
alter table regatta add foreign key (creator) references account(id) on delete set null on update cascade;

-- Season IDs in a meaningful way --
alter table season change column id id varchar(3) not null;
update season set id = concat('s', substr(year(start_date), 3, 2)) where season = 'spring';
update season set id = concat('f', substr(year(start_date), 3, 2)) where season = 'fall';
update season set id = concat('w', substr(year(start_date), 3, 2)) where season = 'winter';
update season set id = concat('m', substr(year(start_date), 3, 2)) where season = 'summer';
alter table dt_regatta change column season season varchar(3) default null;
update dt_regatta set season = null where season not in (select id from season);
alter table dt_regatta add foreign key (season) references season(id) on delete set null on update cascade;
select id, name, season from dt_regatta where season is null;

-- tweak finish table --
alter table finish change column amount amount tinyint default null comment 'Non-positive for assigned, otherwise as appropriate for the penalty';
update finish set amount = null where amount = 0;
alter table finish change column displace displace tinyint default null;
update finish set displace = 1 where displace is not null;

-- some team racing functionality --
create table tr_race_teams (id int primary key, team1 int not null, team2 int not null) engine=innodb default charset=utf8;
alter table tr_race_teams add foreign key (id) references race(id) on delete cascade on update cascade, add foreign key (team1) references team(id)on delete cascade on update cascade, add foreign key (team2) references team(id) on delete cascade on update cascade;
alter table rp add column boat_number tinyint default null comment "Applicable to team racing";
alter table regatta change column scoring scoring enum('standard', 'combined', 'team') not null default 'standard';

-- team racing regattas as composition of smaller combined-scoring regattas--
alter table rp drop column boat_number;
drop table tr_race_teams;
create table tr_race_teams (id int primary key, number tinyint(3) unsigned not null, team1 int not null, team2 int not null) engine=innodb default charset=utf8;
alter table tr_race_teams add foreign key (team1) references team(id) on delete cascade on update cascade, add foreign key (team2) references team(id) on delete cascade on update cascade;
alter table tr_race_teams add column regatta int(5) not null after id, add foreign key (regatta) references regatta(id) on delete cascade on update cascade;
alter table tr_race_teams drop primary key, change column id id int not null primary key auto_increment;

-- fixes for outbox --
alter table message add column inactive tinyint default null after active;
update message set inactive = 1 where active = null or active = 0;
alter table message drop column active;
alter table outbox change column recipients recipients enum('all', 'conferences', 'roles', 'users') not null default 'all';

-- pub_update_request should contain foreign key to regatta. This
-- replaces previous thinking which imposed the rule.
delete from pub_update_request where regatta not in (select id from regatta);
alter table pub_update_request add foreign key (regatta) references regatta(id) on delete cascade on update cascade;

-- schools, like sailors, can be inactivated --
alter table school add column inactive datetime default null;

-- dt_rp fixings... some assembly may be required --
update dt_rp, sailor set dt_rp.sailor = sailor.id where dt_rp.sailor = sailor.icsa_id;
delete from dt_rp where sailor not in (select id from sailor);
alter table dt_rp change column sailor sailor mediumint(9) not null;
alter table dt_rp add foreign key (sailor) references sailor(id) on delete cascade on update cascade;

-- team races require the logical grouping of races into rounds. This
-- concept can still apply to standard/combined scoring, but in that
-- case there is only one giant round.
alter table race add column round int default null after number;
-- for non-team racing, separate each "day" of racing into
-- rounds. Thus, on Saturdays, all the races belong to the same round,
-- etc. At the time of round-based scoring (see ScoresGridDialog), add
-- up the result of all the head-to-head *within* each round
update race, regatta, (select race, min(entered) as time from finish group by race) as finish set race.round = abs(to_days(finish.time) - to_days(regatta.start_time)) + 1 where race.regatta = regatta.id and race.id = finish.race;
update race set round = 1 where round is null;
update race set round = null where id not in (select race from finish) and regatta not in (select id from regatta where scoring = 'team');

delete from race where regatta in (select id from regatta where finalized is not null) and id not in (select race from finish);

-- use null-byte separator for dt_rp --
update dt_rp set race_nums = replace(race_nums, ",", "\0");

-- allow for new hashed passwords --
alter table account change password password varchar(128) null;

-- reattach burgees --
update school, burgee set school.burgee = burgee.id where school.id = burgee.school;

update dt_regatta set status = 'ready' where status = 'coming';

-- account: admin should be null when not used
alter table account change column admin admin tinyint default null;
update account set admin = null where admin = 0;

-- fix summaries --
update daily_summary set summary = replace(summary, "\\'", "'");
update daily_summary set summary = replace(summary, "\\\"", "\"");

delete from dt_regatta where season is null;
alter table dt_regatta drop foreign key dt_regatta_ibfk_1, change column season season varchar(3) not null, add foreign key (season) references season(id) on delete cascade on update cascade;

-- use one table for updates --
alter table pub_update_request add column completion_time datetime default null;
update pub_update_request, pub_update_log set pub_update_request.completion_time = pub_update_log.attempt_time where pub_update_request.id = pub_update_log.request;
drop table pub_update_log;

-- dt_team names can be sailor names for signlehanded events
alter table dt_team change column name name text not null;

-- regatta nick name can be null --
alter table regatta change column nick nick varchar(40) default null;

-- create an update queue for schools
create table pub_update_school (id int primary key auto_increment, school varchar(10) not null, activity enum('burgee') not null default 'burgee', request_time timestamp not null default current_timestamp, completion_time datetime default null) engine=innodb default charset=latin1;
alter table pub_update_school add foreign key (school) references school(id) on delete cascade on update cascade;

-- change dt_regatta to contain only the extra information for a regatta --
delete from dt_regatta where id not in (select id from regatta);
alter table dt_regatta add foreign key (id) references regatta(id) on delete cascade on update cascade;
alter table dt_regatta drop column venue;
alter table dt_regatta drop column name, drop column nick, drop column start_time, drop column end_date, drop column type, drop column finalized, drop column scoring, drop column participant;
-- regatta hosts are arrays
update dt_regatta set hosts = replace(hosts, ',', '\0');
update dt_regatta set confs = replace(confs, ',', '\0');
update dt_regatta set boats = replace(boats, ',', '\0');

-- update school request can refer to season
alter table pub_update_school change column activity activity enum('burgee', 'season') not null default 'burgee', add column season varchar(3) default null after activity, add foreign key (season) references season(id) on delete cascade on update cascade;

-- personal is not a regatta type, but a condition
alter table regatta add column private tinyint default null after participant;
update regatta set private = 1 where type = 'personal';
alter table regatta change column type type enum('conference','intersectional','championship','two-conference','conference-championship','promotional') not null default 'conference';
update regatta set type = 'conference' where type = '';

-- use utf8 for first_name, last_name of account table --
alter table account change column first_name first_name varchar(30) character set utf8 not null;
alter table account change column last_name last_name varchar(30) character set utf8 not null;

-- turn regatta type into separate table --
create table type (id varchar(30) not null primary key, title varchar(40) not null, description text default null, rank tinyint not null default 1 comment "Smaller means more important", inactive tinyint default null) engine=innodb charset=utf8;
insert into type values ('championship', "National Championship", null, 1, null), ('intersectional', "Intersectional", null, 3, null), ('conference-championship', "Conference Championship", null, 2, null), ('two-conference', "Two-Conference", null, 4, null), ('conference', "In-Conference", null, 5, null), ('promotional', "Promotional", null, 6, null);
alter table regatta change column type type_old enum('conference','intersectional','championship','two-conference','conference-championship','promotional') not null default 'conference', add column type varchar(30) character set utf8 not null after type_old;
update regatta set type = type_old;
update regatta set type = 'conference' where type is null;
alter table regatta add foreign key (type) references type(id) on delete cascade on update cascade, drop column type_old;

-- track the overall score (including penalties) in the dt_team_division
--   table, for ease of data reporting
alter table dt_team_division add column score int not null comment "Includes penalties";
alter table dt_team add column score int not null comment "Includes penalties";
alter table dt_rp add column rank tinyint(3) unsigned comment "In races sailed", add column explanation tinytext default null comment "Rank explanation";

-- add finalization as a specific regatta update request
alter table pub_update_request change column activity activity enum('rotation','score','rp','details','summary', 'finalized') not null default 'score';

-- merge dt_regatta into regatta table
alter table regatta add column dt_num_divisions tinyint unsigned default null, add column dt_num_races tinyint unsigned default null, add column dt_hosts varchar(255) default null, add column dt_confs varchar(255) default null, add column dt_boats varchar(255) default null, add column dt_singlehanded tinyint unsigned default null, add column dt_season varchar(3) default null, add column dt_status varchar(40) default null, add foreign key (dt_season) references season(id) on delete set null on update cascade;
update regatta, dt_regatta set  dt_num_divisions = num_divisions, dt_num_races = num_races, dt_hosts = hosts, dt_confs = confs, dt_boats = boats, dt_singlehanded = singlehanded, dt_season = season, dt_status = status where regatta.id = dt_regatta.id;

-- merge dt_team into team table
alter table team add column dt_rank tinyint unsigned default null, add column dt_explanation varchar(100) default null, add column dt_score int default null;
update team, dt_team set dt_rank = rank, dt_explanation = rank_explanation, dt_score = score where team.id = dt_team.id;

alter table dt_team_division drop foreign key dt_team_division_ibfk_3, add foreign key (team) references team(id) on delete cascade on update cascade;

drop table dt_team;
drop table dt_regatta;

-- need to track URL changes --
alter table pub_update_request change column activity activity enum('rotation','score','rp','details','summary','finalized', 'url') not null default 'score', change column argument argument varchar(100) default null;
alter table pub_update_request change column activity activity enum('rotation','score','rp','details','summary','finalized', 'url', 'season') not null default 'score';

-- delete (inactivate) regattas
alter table regatta add column inactive datetime default null comment "Deleted regattas, to be removed by the system." after private;

-- team racing rounds --
alter table race change column round scored_day int default null comment "Regatta day originally scored.";
create table round (id int primary key auto_increment, title varchar(60) not null, scoring tinyint default null) engine=innodb default charset = utf8;
insert into round (title) (select distinct concat(regatta, ":Round ", scored_day) from race where scored_day is not null and regatta in (select id from regatta where scoring = "team"));

alter table race add column round int default null after boat;
update race, round set race.round = round.id where concat(race.regatta, ":Round ", race.scored_day) = round.title;
alter table race add foreign key (round) references round(id) on delete cascade on update cascade;

-- clean up races in team-scoring regattas
delete from race where regatta in (select id from regatta where scoring = 'team') and number not in (select number from tr_race_teams);
delete from round where id not in (select round from race);

-- Store the team racing team pairs with the race object iself
-- this gets rid of the extra tr_race_teams table which can
-- create orphaned data.
--
-- A disadvantage of this method is that all divisions for the
-- same race numbers SHOULD have the same team pairings. A plus
-- side of this change is that foreign key cascading may retain
-- some sanity
alter table race add column tr_team1 int default null, add column tr_team2 int default null, add foreign key (tr_team1) references team(id) on delete cascade on update cascade, add foreign key (tr_team2) references team(id) on delete cascade on update cascade;
update race, tr_race_teams set race.tr_team1 = tr_race_teams.team1, race.tr_team2 = tr_race_teams.team2 where (race.number, race.regatta) = (tr_race_teams.number, tr_race_teams.regatta);
drop table tr_race_teams;

-- in order to make race numbers editable, drop unique key
alter table race drop key regatta_2;

-- add order to round objects
alter table round add column relative_order tinyint not null default 1;
update round set relative_order = 1 where relative_order is null;

-- delete DNS from team racing regattas
delete from finish where penalty = "DNS" and comments = "Did not participate" and race in (select id from race where regatta in (select id from regatta where scoring = "team"));

-- some races do not "count" towards record in team racing
alter table race add column tr_ignore tinyint default null comment "Ignore race for team win-loss record";

-- multiple penalties per finish --
create table finish_modifier (id int primary key auto_increment, finish int not null, type enum('DSQ','RAF','OCS','DNF','DNS','BKD','RDG','BYE') DEFAULT NULL, amount tinyint(4) default null, displace tinyint(4) default null, comments text default null) engine=innodb default charset = utf8;
alter table finish_modifier add foreign key (finish) references finish(id) on delete cascade on update cascade;
insert into finish_modifier (finish, type, amount, displace, comments) (select id, penalty, amount, displace, comments from finish where penalty is not null);
alter table finish drop column penalty, drop column amount, drop column displace, drop column comments;

-- allow for rank attribute
alter table pub_update_request change column activity activity enum('rotation','score','rp','details','summary','finalized','url','season', 'rank') NOT NULL DEFAULT 'score';

-- track the number of wins/losses/ties for team racing teams
alter table team add column dt_wins mediumint unsigned default null, add column dt_losses mediumint unsigned default null, add column dt_ties mediumint unsigned default null;

-- text entries
create table text_entry (id varchar(40) not null primary key, plain text default null, html text default null) engine=innodb default charset =utf8;

replace into text_entry (id, plain, html) values ('welcome', "This is the home for real-time results of College Sailing regattas. This site includes scores and participation records for all fleet-racing events within ICSA. An archive of {a:/seasons/,all previous seasons} is also available.

To follow a specific school, use our {a:/schools/,listing of schools} organized by ICSA Conference. Each school's participation is summarized by season.

For more information about college sailing, ICSA, the teams, and our sponsors, please visit the {a:http://www.collegesailing.org,ICSA site}.", '<div><p>This is the home for real-time results of College Sailing regattas. This site includes scores and participation records for all fleet-racing events within ICSA. An archive of <a href="/seasons/">all previous seasons</a> is also available.</p><p>To follow a specific school, use our <a href="/schools/">listing of schools</a> organized by ICSA Conference. Each school&#039;s participation is summarized by season.</p><p>For more information about college sailing, ICSA, the teams, and our sponsors, please visit the <a href="{a:http://www.collegesailing.org">ICSA</a> site}.</p></div>');

-- add minimum and maximum crew values per boat'
alter table boat add column min_crews tinyint not null default 1, add column max_crews tinyint not null;
update boat set min_crews = (occupants - 1), max_crews = (occupants - 1);
alter table boat drop column occupants;

-- track the number of wins/losses/ties for team racing team_divisions
alter table dt_team_division add column wins mediumint unsigned default null, add column losses mediumint unsigned default null, add column ties mediumint unsigned default null;
alter table dt_team_division change column score score mediumint unsigned default null comment "Team races have no score.";

-- use pub_update_season to request updates to season summary pages
alter table pub_update_season change column update_time request_time timestamp not null default current_timestamp, add column activity enum('regatta', 'details') not null default 'regatta' after season, add column completion_time datetime default null;
delete from pub_update_season;

-- allow multiple rounds per race
create table race_round (id int not null primary key auto_increment, race int(7) not null, round int(11) not null) engine=innodb;
alter table race_round add foreign key (race) references race(id) on delete cascade on update cascade, add foreign key (round)references round(id) on delete cascade on update cascade;
insert into race_round (race, round) (select id, round from race where round is not null);
alter table race drop foreign key race_ibfk_4, drop column round;

-- re-associate main round with race
alter table race add column round int default null, add foreign key race_ibfk_4 (round) references round(id) on delete cascade on update cascade;
update race, (select race, rnd.id, min(rnd.num) from race_round inner join (select id, id + relative_order as num from round) as rnd on rnd.id = race_round.round group by race) as first_round set race.round = first_round.id where race.id = first_round.race;
delete from race_round where (race, round) in (select id, round from race);

-- for simplicity, associate each round with its regatta on its own merit
alter table round add column regatta int(5) not null after id;
update race, round set round.regatta = race.regatta where race.round = round.id;
delete from round where regatta not in (select id from regatta);
alter table round add foreign key (regatta) references regatta(id) on delete cascade on update cascade;

-- representative information need not be database-backed
alter table representative add column name varchar(255) not null;
update representative, sailor set representative.name = concat(sailor.first_name, " ", sailor.last_name) where representative.sailor = sailor.id;
alter table representative drop foreign key representative_ibfk_1, drop column sailor;

-- program needs to track which team rounds are "grouped" together in their race order.
-- create a dummy table whose primary key indicates a group
create table round_group (id int primary key auto_increment) engine=innodb;
alter table round add column round_group int default null, add foreign key (round_group) references round_group(id) on delete set null on update cascade;

-- ignore race for one team, but not the other
alter table race change column tr_ignore tr_ignore1 tinyint default null, add column tr_ignore2 tinyint default null;
update race set tr_ignore2 = tr_ignore1;

-- for uniformity, all races (regardless of division) should have the proper tr_ignore flag set
create temporary table race_A as (select id, regatta, number, tr_ignore1, tr_ignore2 from race where division = "A");
update race, race_A set race.tr_ignore1 = race_A.tr_ignore1, race.tr_ignore2 = race_A.tr_ignore2 where (race.regatta,race.number) = (race_A.regatta,race_A.number) and race.division != "A";
drop temporary table race_A;

-- lock teams rank
alter table team add column lock_rank tinyint default null after name;

-- store team race order schemes
create table race_order (id varchar(12) not null primary key, template text not null, author varchar(40) not null) engine=innodb default charset = latin1;
alter table race_order add foreign key (author) references account(id) on delete cascade on update cascade;

-- fix bug with empty sailors in RP --
delete from rp where sailor is null;

-- associate color with sail (optional)
alter table rotation add column color varchar(7) default null comment "Hex code, including hash";

-- remember team rotations --
create table regatta_rotation (id int primary key auto_increment, regatta int(5) not null, rotation text not null, name varchar(50) not null) engine=innodb;
alter table regatta_rotation add foreign key (regatta) references regatta(id) on delete cascade on update cascade;

-- allow for rank groups, ways to box teams into certain ranks for team racing
alter table team add column rank_group tinyint unsigned default null after lock_rank;

-- lock all ranks for finalized regattas
update team set lock_rank = 1 where regatta in (select id from regatta where scoring = "team" and finalized is not null);

-- store the "carry over" intention for each round as a master-slave relation
create table round_slave (id int primary key auto_increment, master int not null, slave int not null) engine=innodb;
alter table round_slave add foreign key (master) references round(id) on delete cascade on update cascade, add foreign key (slave) references round(id) on delete cascade on update cascade;
-- reverse populate master-slave relationship
insert into round_slave (master, slave) (select distinct race.round, race_round.round from race inner join race_round on race.id = race_round.race);

-- more flexible templates (name and description)
alter table race_order add column num_divisions tinyint unsigned not null after id, add column num_teams tinyint unsigned not null after num_divisions, add column num_boats tinyint unsigned not null after num_teams, drop primary key;
alter table race_order change column id old_id varchar(12) not null, add column id int primary key auto_increment first;
alter table race_order add column name varchar(50) not null after id, add column description text default null after name;
update race_order set num_divisions = substring_index(old_id, '-', 1);
update race_order set num_teams = substring_index(substring_index(old_id, '-', 2), '-', -1);
update race_order set num_boats = substring_index(substring_index(old_id, '-', 3), '-', -1);
update race_order set description = "Rotate frequently." where substring_index(old_id, '-', -1) = "1";
update race_order set name = "Standard";
alter table race_order drop column old_id;

-- remember aa reports
create table aa_report (id varchar(40) primary key, regattas text not null comment "Array of regatta IDs",  sailors text not null comment "Array of sailor IDs", last_updated timestamp not null default current_timestamp, author varchar(40) default null) engine=innodb;
alter table aa_report add column type enum('coed', 'women', 'all') not null default 'coed' after id, add column role enum('skipper', 'crew') not null default 'skipper' after type, add column seasons text not null comment "List of seasons IDs" after role, add column conferences text not null comment "List of conference IDs" after seasons, add column min_regattas tinyint unsigned not null default 2 after conferences;

-- create permission subsystem
create table permission (id varchar(40) not null primary key, title tinytext not null, description text default null) engine=innodb;
create table role (id mediumint not null primary key auto_increment, title tinytext not null, description text default null) engine=innodb;
create table role_permission (id int primary key auto_increment, role mediumint not null, permission varchar(40) not null) engine=innodb;
alter table role_permission add foreign key (role) references role(id) on delete cascade on update cascade, add foreign key (permission) references permission(id) on delete cascade on update cascade;
alter table account add column ts_role mediumint default null, add foreign key (ts_role) references role(id) on delete set null on update cascade;

-- add individual permission entries
insert into permission values
('edit_seasons', "Edit seasons", "Edit the start and end time of each season, and create new ones."),
('edit_unregistered_sailors', "Manage unregistered sailors", "Merge list of unregistered sailors for a given school."),
('edit_school_logo', "Edit school burgee", "Upload a new image to use as the school burgee."),
('edit_team_names', "Edit team names", "Manage the list of mascots to be used for the teams from a particular school."),
('edit_aa_report', "Edit All-America reports", "Create, save, and edit All-America reports."),
('download_aa_report', "Download saved All-America reports", "Download (but not create) All-America reports."),
('use_head_to_head_report', "Use Head-to-Head report", "Allow access to the Head-to-Head report."),
('use_team_record_report', "Use \"Team Record\" report", "Allow access to use the report of team participation."),
('send_message', "Broadcast e-mail message", "Can send e-mail on behalf of administration."),
('edit_venues', "Edit list of venues", null),
('edit_boats', "Edit list of boats", null),
('edit_regatta_types', "Edit list of regatta types", "Edit the different regatta types available, their display name, and their order of importance."),
('edit_tr_templates', "Manage race-order templates for team racing", "Edit, create, and delete available race-order templates for team racing."),
('edit_users', "Edit existing users", "Approve/reject pending accounts, and edit existing users. Will receive notification e-mail with each newly-requested account."),
('edit_announcements', "Edit scoring announcements", "Edit text to be used on the log-in page for the program."),
('edit_welcome', "Edit public welcome message", "Edit text to be used on the \"Welcome\" port of the public site."),
('create_regatta', "Create a new regatta", null),
('edit_regatta', "Score regattas", "Ability to edit and score existing regattas. Access to regatta is regulated by schools associated with account."),
('delete_regatta', "Delete regatta", "Delete a regatta from the system, to which the account has access."),
('finalize_regatta', "Finalize regatta", "Finalize a regatta to which the account has access.");

-- add roles
insert into role values (null, 'scorer_editor', "General regatta scorer and school editor");

insert into role_permission (role, permission) values
(1, 'edit_unregistered_sailors'),
(1, 'edit_school_logo'),
(1, 'edit_team_names'),
(1, 'download_aa_report'),
(1, 'use_head_to_head_report'),
(1, 'create_regatta'),
(1, 'edit_regatta'),
(1, 'delete_regatta'),
(1, 'finalize_regatta');

update account set ts_role = 1;

-- track the number of teams in master-slave round relationship
alter table round_slave add column num_teams tinyint unsigned not null;
update round_slave, (select round, count(distinct tr_team1, tr_team2) as num_teams from race where round in (select master from round_slave) and id in (select race from race_round) group by round) as t1 set round_slave.num_teams = t1.num_teams where round_slave.master = t1.round;

-- setting table: for "sticky" (cached) information
create table setting (id varchar(100) not null primary key, value text default null) engine=innodb;

-- track the daily summary e-mails
alter table daily_summary add column mail_sent tinyint default null;

-- track mailing lists per type
alter table type add column mail_lists text default null after rank;

-- keep cache of URLs whcih exist for a given regatta
create table pub_regatta_url (id int not null primary key auto_increment, regatta int(5) not null, url varchar(255) not null) engine=innodb;
alter table pub_regatta_url add foreign key (regatta) references regatta(id) on delete cascade on update cascade;
alter table pub_regatta_url change column url url text not null;

-- track public files in the database
create table pub_file (id varchar(32) not null primary key, filetype varchar(10) default null, filename varchar(100) not null, filedata mediumblob not null) engine=innodb;
alter table pub_file change column filetype filetype varchar(40) not null;
alter table pub_file drop column filename;
create table pub_update_file (id int not null primary key auto_increment, file varchar(32) not null, request_time timestamp not null default current_timestamp, completion_time datetime default null) engine=innodb;
alter table pub_update_file add column activity varchar(32) default null after file;

-- track width/height of burgees
alter table burgee add column width tinyint unsigned default null after filedata, add column height tinyint unsigned default null after width;

-- keep copy of small burgee
alter table school add column burgee_small int default null after burgee, add foreign key (burgee_small) references burgee(id) on delete set null on update cascade;

-- ad doption to send e-mail to users from school
alter table outbox change column recipients recipients enum('all','conferences','roles','users','schools') not null default 'all';

-- add team activity
alter table pub_update_request change column activity activity enum('rotation','score','rp','details','summary','finalized','url','season','rank','team') not null default 'score';

-- keep copy of square burgee (for twitter)
alter table school add column burgee_square int default null after burgee_small, add foreign key (burgee_square) references burgee(id) on delete set null on update cascade;

-- track the width/height of public images
alter table pub_file add column width mediumint unsigned default null, add column height mediumint unsigned default null;

-- save session information in the database
CREATE TABLE `websession` (
  `id` varchar(32) NOT NULL,
  `created` datetime NOT NULL,
  `last_modified` datetime NOT NULL,
  `expires` datetime default null,
  `sessiondata` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- tweak list of sponsors from admin site
create table pub_sponsor (id tinyint unsigned not null primary key auto_increment, name varchar(50) not null, url varchar(255) default null, logo varchar(32) default null) engine=innodb default charset = utf8;
alter table pub_sponsor add foreign key (logo) references pub_file(id) on delete set null on update cascade;
alter table pub_sponsor add column relative_order tinyint unsigned not null default 1;

-- use pub_update_season to request updates for front page and 404 resources
alter table pub_update_season change column activity activity enum('regatta','details','front','404','school404') not null default 'regatta';

-- record message sender for smarter replies
alter table message add column sender varchar(40) default null after id;
alter table message add foreign key (sender) references account(id) on delete set null on update cascade;

-- track mailing list per conference
alter table conference add column mail_lists text default null;

-- send messages based on regatta status
alter table outbox change column recipients recipients enum('all','conferences','roles','users','schools','status') not null default 'all';

-- have we tweeted after a daily summary
alter table daily_summary add column tweet_sent tinyint default null;

-- which regatta types should tweet daily summaries?
alter table type add column tweet_summary tinyint default null after mail_lists;

-- notice board feature: allow documents to be associated with regattas
create table regatta_document (id int not null primary key auto_increment, regatta int(5) not null, name varchar(100) not null, description text default null, url varchar(120) not null, category enum('notice', 'protest') not null default 'notice', relative_order tinyint unsigned not null default 0, filetype varchar(50) not null, filedata mediumblob not null, author varchar(40) character set latin1 default null, last_updated timestamp not null default current_timestamp) engine=innodb default charset = utf8;
alter table regatta_document add foreign key (regatta) references regatta(id) on delete cascade on update cascade, add foreign key (author) references account(id) on delete set null on update cascade;

-- add new update type: document
alter table pub_update_request change column activity activity enum('rotation','score','rp','details','summary','finalized','url','season','rank','team','document') not null default 'score';
