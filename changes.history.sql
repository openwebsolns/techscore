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