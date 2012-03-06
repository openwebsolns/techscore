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