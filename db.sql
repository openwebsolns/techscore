SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `account` (
  `first_name` varchar(30) NOT NULL,
  `last_name` varchar(30) NOT NULL,
  `username` varchar(40) NOT NULL,
  `school` varchar(10) NOT NULL,
  `role` enum('student','coach','staff') NOT NULL default 'coach',
  `password` varchar(48) default NULL,
  `status` enum('pending','active','inactive') NOT NULL default 'pending',
  `is_admin` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`username`),
  KEY `school` (`school`),
  CONSTRAINT `account_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `boat` (
  `id` int(2) NOT NULL auto_increment,
  `name` varchar(15) NOT NULL,
  `occupants` int(1) NOT NULL default '2',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `burgee` (
  `school` varchar(10) NOT NULL,
  `filedata` mediumblob NOT NULL,
  `last_updated` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_by` varchar(40) default NULL,
  PRIMARY KEY  (`school`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `burgee_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `burgee_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `conference` (
  `id` int(2) NOT NULL auto_increment,
  `name` varchar(60) NOT NULL,
  `nick` varchar(10) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `daily_summary` (
  `id` int(11) NOT NULL auto_increment,
  `regatta` int(5) NOT NULL,
  `summary_date` date NOT NULL,
  `summary` text,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `regatta` (`regatta`,`summary_date`),
  CONSTRAINT `daily_summary_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=181 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `finish` (
  `id` int(9) NOT NULL auto_increment,
  `race` int(7) NOT NULL,
  `team` int(7) NOT NULL,
  `entered` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `race` (`race`,`team`),
  KEY `team` (`team`),
  CONSTRAINT `finish_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `finish_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35786 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `handicap` (
  `finish` int(9) NOT NULL,
  `type` enum('BKD','RDG','BYE') NOT NULL default 'BKD',
  `amount` int(2) default '-1' COMMENT 'Amount = -1 implies AVG',
  `comments` text,
  PRIMARY KEY  (`finish`),
  CONSTRAINT `handicap_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `host` (
  `account` varchar(40) NOT NULL,
  `regatta` int(5) NOT NULL,
  `principal` tinyint(1) default '0',
  PRIMARY KEY  (`account`,`regatta`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `host_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `host_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `invite` (
  `regatta` int(5) NOT NULL,
  `school` varchar(10) NOT NULL,
  `status` enum('accepted','denied') default NULL,
  `num_teams` int(2) NOT NULL default '1',
  PRIMARY KEY  (`regatta`,`school`),
  KEY `school` (`school`),
  CONSTRAINT `invite_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invite_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `message` (
  `id` int(11) NOT NULL auto_increment,
  `account` varchar(40) NOT NULL,
  `created` datetime NOT NULL,
  `read_time` datetime default NULL,
  `subject` varchar(100) default '',
  `content` text,
  `active` tinyint(4) default '1',
  PRIMARY KEY  (`id`),
  KEY `account` (`account`),
  CONSTRAINT `message_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `observation` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `race` int(7) NOT NULL,
  `observation` text NOT NULL,
  `observer` varchar(50) default NULL,
  `noted_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `race` (`race`),
  CONSTRAINT `observation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `penalty` (
  `finish` int(9) NOT NULL,
  `type` enum('DSQ','RAF','OCS','DNF','DNS') NOT NULL default 'DSQ',
  `comments` text,
  PRIMARY KEY  (`finish`),
  CONSTRAINT `penalty_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `penalty_team` (
  `team` int(7) NOT NULL,
  `division` enum('A','B','C','D') NOT NULL default 'A',
  `type` enum('MRP','PFD','LOP','GDQ') NOT NULL default 'GDQ',
  `comments` text,
  PRIMARY KEY  (`team`,`division`),
  CONSTRAINT `penalty_team_ibfk_1` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `race` (
  `id` int(7) NOT NULL auto_increment,
  `regatta` int(5) NOT NULL,
  `division` enum('A','B','C','D') NOT NULL default 'A',
  `boat` int(2) default NULL,
  `wind_mph` double default NULL,
  `wind_gust_mph` double default NULL,
  `wind_dir` enum('N','NNW','NW','WNW','W','WSW','SW','SSW','S','SSE','SE','ESE','E','ENE','NE','NNE') default NULL,
  `temp_f` double default NULL,
  `scored_by` varchar(40) default NULL,
  PRIMARY KEY  (`id`),
  KEY `regatta` (`regatta`),
  KEY `boat` (`boat`),
  KEY `scored_by` (`scored_by`),
  CONSTRAINT `race_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_2` FOREIGN KEY (`boat`) REFERENCES `boat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_3` FOREIGN KEY (`scored_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8288 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
/*!50001 CREATE TABLE IF NOT EXISTS `race_num` (
  `id` int(7),
  `number` bigint(21)
) */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `regatta` (
  `id` int(5) NOT NULL auto_increment,
  `name` varchar(35) NOT NULL,
  `nick` varchar(20) default NULL,
  `start_time` datetime default NULL COMMENT 'Date and time when regatta started',
  `end_date` date default NULL,
  `venue` int(4) default NULL,
  `type` enum('conference','intersectional','championship','personal') NOT NULL default 'conference',
  `finalized` datetime default NULL,
  `scoring` enum('standard','combined') NOT NULL default 'standard',
  PRIMARY KEY  (`id`),
  KEY `venue` (`venue`),
  CONSTRAINT `regatta_ibfk_1` FOREIGN KEY (`venue`) REFERENCES `venue` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `report` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `regatta` int(5) NOT NULL,
  `account` varchar(40) default NULL,
  `name` varchar(30) NOT NULL,
  `nick` varchar(20) default NULL,
  PRIMARY KEY  (`id`),
  KEY `account` (`account`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `report_ibfk_2` FOREIGN KEY (`account`) REFERENCES `account` (`username`),
  CONSTRAINT `report_ibfk_3` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `report_content` (
  `id` varchar(20) NOT NULL default '',
  `description` text,
  `popular` tinyint(4) default NULL,
  `category` enum('General','Scores','Sailors','Races','Misc') NOT NULL default 'General',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `report_struct` (
  `report` smallint(5) unsigned NOT NULL,
  `content` varchar(20) default NULL,
  `placement` tinyint(3) default NULL,
  UNIQUE KEY `report` (`report`,`content`),
  KEY `content` (`content`),
  CONSTRAINT `report_struct_ibfk_1` FOREIGN KEY (`report`) REFERENCES `report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `report_struct_ibfk_2` FOREIGN KEY (`content`) REFERENCES `report_content` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `representative` (
  `team` int(7) NOT NULL,
  `sailor` mediumint(9) NOT NULL,
  UNIQUE KEY `team` (`team`,`sailor`),
  KEY `sailor` (`sailor`),
  CONSTRAINT `representative_ibfk_1` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `representative_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `rotation` (
  `race` int(7) NOT NULL,
  `team` int(7) NOT NULL,
  `sail` varchar(8) NOT NULL,
  UNIQUE KEY `race` (`race`,`team`),
  UNIQUE KEY `race_sail` (`race`,`sail`),
  KEY `team` (`team`),
  CONSTRAINT `rotation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rotation_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `rp` (
  `id` int(11) NOT NULL auto_increment,
  `race` int(7) NOT NULL,
  `team` int(7) NOT NULL,
  `sailor` mediumint(9) default NULL,
  `boat_role` enum('skipper','crew') NOT NULL default 'skipper',
  PRIMARY KEY  (`id`),
  KEY `race` (`race`),
  KEY `team` (`team`),
  KEY `sailor` (`sailor`),
  CONSTRAINT `rp_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rp_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rp_ibfk_3` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`),
  CONSTRAINT `rp_ibfk_4` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46748 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `rp_form` (
  `regatta` int(11) NOT NULL,
  `filedata` mediumblob NOT NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`regatta`),
  CONSTRAINT `rp_form_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `rp_log` (
  `id` int(11) NOT NULL auto_increment,
  `regatta` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `rp_log_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `sailor` (
  `id` mediumint(9) NOT NULL auto_increment,
  `icsa_id` mediumint(9) default NULL,
  `school` varchar(10) NOT NULL,
  `last_name` text NOT NULL,
  `first_name` text NOT NULL,
  `year` char(4) default NULL,
  `role` enum('student','coach') NOT NULL default 'student',
  PRIMARY KEY  (`id`),
  KEY `school` (`school`),
  CONSTRAINT `sailor_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6294 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `sailor_update` (
  `last_updated` timestamp NOT NULL default CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `school` (
  `id` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `nick_name` varchar(20) default NULL,
  `conference` int(2) NOT NULL,
  `city` varchar(30) default NULL,
  `state` varchar(30) default NULL,
  `burgee` text,
  PRIMARY KEY  (`id`),
  KEY `conference` (`conference`),
  CONSTRAINT `school_ibfk_1` FOREIGN KEY (`conference`) REFERENCES `conference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `score` (
  `finish` int(9) NOT NULL,
  `place` text NOT NULL,
  `score` int(3) NOT NULL,
  `explanation` text,
  UNIQUE KEY `finish` (`finish`),
  CONSTRAINT `score_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
/*!50001 CREATE TABLE IF NOT EXISTS `score_boat` (
  `regatta` int(5),
  `boat` int(2),
  `sail` varchar(8),
  `score` decimal(32,0)
) */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `score_update` (
  `regatta` int(5) NOT NULL,
  `last_update` timestamp NOT NULL default CURRENT_TIMESTAMP,
  UNIQUE KEY `regatta` (`regatta`),
  CONSTRAINT `score_update_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `subscriber` (
  `id` int(3) NOT NULL auto_increment,
  `name` text,
  `address` text NOT NULL COMMENT 'Where to notify subscriber',
  `notify_address` text,
  `contact_email` varchar(50) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `subscription` (
  `regatta` int(5) NOT NULL,
  `subscriber` int(3) NOT NULL,
  `foreign_id` varchar(20) default NULL,
  PRIMARY KEY  (`regatta`,`subscriber`),
  KEY `subscriber` (`subscriber`),
  CONSTRAINT `subscription_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `subscription_ibfk_2` FOREIGN KEY (`subscriber`) REFERENCES `subscriber` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `team` (
  `id` int(7) NOT NULL auto_increment,
  `regatta` int(5) NOT NULL,
  `school` varchar(10) default NULL,
  `name` varchar(20) NOT NULL,
  `old_id` int(2) default NULL,
  PRIMARY KEY  (`id`),
  KEY `regatta` (`regatta`),
  KEY `school` (`school`),
  CONSTRAINT `team_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `team_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2806 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `team_name_prefs` (
  `school` varchar(10) default NULL,
  `name` varchar(20) default NULL,
  `rank` int(5) default NULL,
  KEY `school` (`school`),
  CONSTRAINT `team_name_prefs_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `temp_regatta` (
  `regatta` int(5) NOT NULL,
  `original` int(5) NOT NULL,
  `expires` datetime NOT NULL,
  KEY `regatta` (`regatta`),
  KEY `original` (`original`),
  CONSTRAINT `temp_regatta_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`),
  CONSTRAINT `temp_regatta_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `temp_regatta_ibfk_3` FOREIGN KEY (`original`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE IF NOT EXISTS `venue` (
  `id` int(4) NOT NULL auto_increment,
  `name` varchar(40) NOT NULL,
  `address` varchar(40) default NULL,
  `city` varchar(20) default NULL,
  `state` varchar(2) default NULL,
  `zipcode` char(5) default NULL,
  `weather_station_id` varchar(30) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
/*!50001 DROP TABLE `race_num`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `race_num` AS (select `race`.`id` AS `id`,count(`r2`.`id`) AS `number` from (`race` left join `race` `r2` on(((`race`.`regatta` = `r2`.`regatta`) and (`race`.`division` = `r2`.`division`)))) where (`r2`.`id` <= `race`.`id`) group by `race`.`regatta`,`race`.`id`) */;
/*!50001 DROP TABLE `score_boat`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `score_boat` AS (select `race`.`regatta` AS `regatta`,`race`.`boat` AS `boat`,`rotation`.`sail` AS `sail`,sum(`score`.`score`) AS `score` from (((`score` join `finish` on((`score`.`finish` = `finish`.`id`))) join `race` on((`finish`.`race` = `race`.`id`))) join `rotation` on(((`finish`.`race`,`finish`.`team`) = (`rotation`.`race`,`rotation`.`team`)))) group by `race`.`regatta`,`race`.`boat`,`rotation`.`sail`) */;
