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

-- fix missing finishes from regatta with id = 7 (Hatch Brown)
insert into finish (race, team, entered, penalty, earned, displace, score, explanation) values (85, 304, '2008-10-08 23:11:01', 'DNF', 18, 1, 19, ""), (100, 303, '2008-10-08 23:11:01', 'DNF', 18, 1, 19, ""), (120, 302, '2008-10-08 23:11:01', 'DNF', 18, 1, 19, ""), (125, 303, '2008-10-08 23:11:01', 'DNF', 18, 1, 19, "");

-- fix summaries --
update daily_summary set summary = replace(summary, "\\'", "'");
update daily_summary set summary = replace(summary, "\\\"", "\"");

-- fix seasons --
update season set end_date = '2009-01-19' where id = 'f08';
update season set end_date = '2010-01-19' where id = 'f09';
update season set start_date = '2009-01-20' where id = 's09';
update season set start_date = '2010-01-20' where id = 's10';

delete from season where id = 'm09';
delete from season where id = 'm10';
delete from season where id = 'm11';

delete from season where id = 'w08';
update dt_regatta set season = 's10' where season = 'w09';
delete from season where id = 'w09';

delete from dt_regatta where season is null;
alter table dt_regatta drop foreign key dt_regatta_ibfk_1, change column season season varchar(3) not null, add foreign key (season) references season(id) on delete cascade on update cascade;

alter table pub_update_request add column completion_time datetime default null;
update pub_update_request, pub_update_log set pub_update_request.completion_time = pub_update_log.attempt_time where pub_update_request.id = pub_update_log.request;
drop table pub_update_log;
