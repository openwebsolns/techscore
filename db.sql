SET FOREIGN_KEY_CHECKS=0;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `account` ( `first_name` varchar(30) NOT NULL, `last_name` varchar(30) NOT NULL, `username` varchar(40) NOT NULL, `school` varchar(10) NOT NULL, `role` enum('student','coach','staff') NOT NULL DEFAULT 'coach', `password` varchar(48) DEFAULT NULL, `status` enum('requested','pending','accepted','rejected','active','inactive') DEFAULT 'pending', `is_admin` tinyint(1) NOT NULL DEFAULT '0', PRIMARY KEY (`username`), KEY `school` (`school`), CONSTRAINT `account_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `boat` ( `id` int(2) NOT NULL AUTO_INCREMENT, `name` varchar(15) NOT NULL, `occupants` int(1) NOT NULL DEFAULT '2', PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `burgee` ( `school` varchar(10) NOT NULL, `filedata` mediumblob NOT NULL, `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_by` varchar(40) DEFAULT NULL, PRIMARY KEY (`school`), KEY `updated_by` (`updated_by`), CONSTRAINT `burgee_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `burgee_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `conference` ( `id` int(2) NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL, `nick` varchar(10) NOT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `daily_summary` ( `id` int(11) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `summary_date` date NOT NULL, `summary` text, PRIMARY KEY (`id`), UNIQUE KEY `regatta` (`regatta`,`summary_date`), CONSTRAINT `daily_summary_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `finish` ( `id` int(9) NOT NULL AUTO_INCREMENT, `race` int(7) NOT NULL, `team` int(7) NOT NULL, `entered` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `race` (`race`,`team`), KEY `team` (`team`), CONSTRAINT `finish_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `finish_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `handicap` ( `finish` int(9) NOT NULL, `type` enum('BKD','RDG','BYE') NOT NULL DEFAULT 'BKD', `amount` int(2) DEFAULT '-1' COMMENT 'Amount = -1 implies AVG', `comments` text, PRIMARY KEY (`finish`), CONSTRAINT `handicap_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `host` ( `account` varchar(40) NOT NULL, `regatta` int(5) NOT NULL, `principal` tinyint(1) DEFAULT '0', PRIMARY KEY (`account`,`regatta`), KEY `regatta` (`regatta`), CONSTRAINT `host_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `host_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `invite` ( `regatta` int(5) NOT NULL, `school` varchar(10) NOT NULL, `status` enum('accepted','denied') DEFAULT NULL, `num_teams` int(2) NOT NULL DEFAULT '1', PRIMARY KEY (`regatta`,`school`), KEY `school` (`school`), CONSTRAINT `invite_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `invite_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `message` ( `id` int(11) NOT NULL AUTO_INCREMENT, `account` varchar(40) NOT NULL, `created` datetime NOT NULL, `read_time` datetime DEFAULT NULL, `subject` varchar(100) DEFAULT '', `content` text, `active` tinyint(4) DEFAULT '1', PRIMARY KEY (`id`), KEY `account` (`account`), CONSTRAINT `message_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`username`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `observation` ( `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT, `race` int(7) NOT NULL, `observation` text NOT NULL, `observer` varchar(50) DEFAULT NULL, `noted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `race` (`race`), CONSTRAINT `observation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `penalty` ( `finish` int(9) NOT NULL, `type` enum('DSQ','RAF','OCS','DNF','DNS') NOT NULL DEFAULT 'DSQ', `comments` text, PRIMARY KEY (`finish`), CONSTRAINT `penalty_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `penalty_team` ( `team` int(7) NOT NULL, `division` enum('A','B','C','D') NOT NULL DEFAULT 'A', `type` enum('MRP','PFD','LOP','GDQ') NOT NULL DEFAULT 'GDQ', `comments` text, PRIMARY KEY (`team`,`division`), CONSTRAINT `penalty_team_ibfk_1` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `race` ( `id` int(7) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `division` enum('A','B','C','D') NOT NULL DEFAULT 'A', `boat` int(2) DEFAULT NULL, `wind_mph` double DEFAULT NULL, `wind_gust_mph` double DEFAULT NULL, `wind_dir` enum('N','NNW','NW','WNW','W','WSW','SW','SSW','S','SSE','SE','ESE','E','ENE','NE','NNE') DEFAULT NULL, `temp_f` double DEFAULT NULL, `scored_by` varchar(40) DEFAULT NULL, PRIMARY KEY (`id`), KEY `regatta` (`regatta`), KEY `boat` (`boat`), KEY `scored_by` (`scored_by`), CONSTRAINT `race_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `race_ibfk_2` FOREIGN KEY (`boat`) REFERENCES `boat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE, CONSTRAINT `race_ibfk_3` FOREIGN KEY (`scored_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 SET @saved_cs_client = @@character_set_client;
 SET character_set_client = utf8;
 /*!50001 CREATE TABLE IF NOT EXISTS `race_num` ( `id` int(7), `number` bigint(21) ) ENGINE=MyISAM */;
 SET character_set_client = @saved_cs_client;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `regatta` ( `id` int(5) NOT NULL AUTO_INCREMENT, `name` varchar(35) NOT NULL, `nick` varchar(20) DEFAULT NULL, `start_time` datetime DEFAULT NULL COMMENT 'Date and time when regatta started', `end_date` date DEFAULT NULL, `venue` int(4) DEFAULT NULL, `type` enum('conference','intersectional','championship','personal') NOT NULL DEFAULT 'conference', `finalized` datetime DEFAULT NULL, `scoring` enum('standard','combined') NOT NULL DEFAULT 'standard', PRIMARY KEY (`id`), KEY `venue` (`venue`), CONSTRAINT `regatta_ibfk_1` FOREIGN KEY (`venue`) REFERENCES `venue` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `report` ( `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `account` varchar(40) DEFAULT NULL, `name` varchar(30) NOT NULL, `nick` varchar(20) DEFAULT NULL, PRIMARY KEY (`id`), KEY `account` (`account`), KEY `regatta` (`regatta`), CONSTRAINT `report_ibfk_2` FOREIGN KEY (`account`) REFERENCES `account` (`username`), CONSTRAINT `report_ibfk_3` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `report_content` ( `id` varchar(20) NOT NULL DEFAULT '', `description` text, `popular` tinyint(4) DEFAULT NULL, `category` enum('General','Scores','Sailors','Races','Misc') NOT NULL DEFAULT 'General', PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `report_struct` ( `report` smallint(5) unsigned NOT NULL, `content` varchar(20) DEFAULT NULL, `placement` tinyint(3) DEFAULT NULL, UNIQUE KEY `report` (`report`,`content`), KEY `content` (`content`), CONSTRAINT `report_struct_ibfk_1` FOREIGN KEY (`report`) REFERENCES `report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `report_struct_ibfk_2` FOREIGN KEY (`content`) REFERENCES `report_content` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `representative` ( `team` int(7) NOT NULL, `sailor` mediumint(9) NOT NULL, UNIQUE KEY `team` (`team`,`sailor`), KEY `sailor` (`sailor`), CONSTRAINT `representative_ibfk_1` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `representative_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `rotation` ( `race` int(7) NOT NULL, `team` int(7) NOT NULL, `sail` varchar(8) NOT NULL, UNIQUE KEY `race` (`race`,`team`), UNIQUE KEY `race_sail` (`race`,`sail`), KEY `team` (`team`), CONSTRAINT `rotation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `rotation_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `rp` ( `id` int(11) NOT NULL AUTO_INCREMENT, `race` int(7) NOT NULL, `team` int(7) NOT NULL, `sailor` mediumint(9) DEFAULT NULL, `boat_role` enum('skipper','crew') NOT NULL DEFAULT 'skipper', PRIMARY KEY (`id`), KEY `race` (`race`), KEY `team` (`team`), KEY `sailor` (`sailor`), CONSTRAINT `rp_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `rp_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `rp_ibfk_3` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`), CONSTRAINT `rp_ibfk_4` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `rp_form` ( `regatta` int(11) NOT NULL, `filedata` mediumblob NOT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`regatta`), CONSTRAINT `rp_form_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `rp_log` ( `id` int(11) NOT NULL AUTO_INCREMENT, `regatta` int(11) NOT NULL, `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `regatta` (`regatta`), CONSTRAINT `rp_log_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `sailor` ( `id` mediumint(9) NOT NULL AUTO_INCREMENT, `icsa_id` mediumint(9) DEFAULT NULL, `school` varchar(10) NOT NULL, `last_name` text NOT NULL, `first_name` text NOT NULL, `year` char(4) DEFAULT NULL, `role` enum('student','coach') NOT NULL DEFAULT 'student', PRIMARY KEY (`id`), KEY `school` (`school`), CONSTRAINT `sailor_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `sailor_update` ( `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `school` ( `id` varchar(10) NOT NULL, `name` varchar(50) NOT NULL, `nick_name` varchar(20) DEFAULT NULL, `conference` int(2) NOT NULL, `city` varchar(30) DEFAULT NULL, `state` varchar(30) DEFAULT NULL, `burgee` text, PRIMARY KEY (`id`), KEY `conference` (`conference`), CONSTRAINT `school_ibfk_1` FOREIGN KEY (`conference`) REFERENCES `conference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `score` ( `finish` int(9) NOT NULL, `place` text NOT NULL, `score` int(3) NOT NULL, `explanation` text, UNIQUE KEY `finish` (`finish`), CONSTRAINT `score_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `score_update` ( `regatta` int(5) NOT NULL, `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `regatta` (`regatta`), CONSTRAINT `score_update_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `subscriber` ( `id` int(3) NOT NULL AUTO_INCREMENT, `name` text, `address` text NOT NULL COMMENT 'Where to notify subscriber', `notify_address` text, `contact_email` varchar(50) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `subscription` ( `regatta` int(5) NOT NULL, `subscriber` int(3) NOT NULL, `foreign_id` varchar(20) DEFAULT NULL, PRIMARY KEY (`regatta`,`subscriber`), KEY `subscriber` (`subscriber`), CONSTRAINT `subscription_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `subscription_ibfk_2` FOREIGN KEY (`subscriber`) REFERENCES `subscriber` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `team` ( `id` int(7) NOT NULL AUTO_INCREMENT, `regatta` int(5) NOT NULL, `school` varchar(10) DEFAULT NULL, `name` varchar(20) NOT NULL, `old_id` int(2) DEFAULT NULL, PRIMARY KEY (`id`), KEY `regatta` (`regatta`), KEY `school` (`school`), CONSTRAINT `team_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `team_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE SET NULL ON UPDATE CASCADE ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `team_name_prefs` ( `school` varchar(10) DEFAULT NULL, `name` varchar(20) DEFAULT NULL, `rank` int(5) DEFAULT NULL, KEY `school` (`school`), CONSTRAINT `team_name_prefs_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `temp_regatta` ( `regatta` int(5) NOT NULL, `original` int(5) NOT NULL, `expires` datetime NOT NULL, KEY `regatta` (`regatta`), KEY `original` (`original`), CONSTRAINT `temp_regatta_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`), CONSTRAINT `temp_regatta_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `temp_regatta_ibfk_3` FOREIGN KEY (`original`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!40101 SET @saved_cs_client = @@character_set_client */;
 /*!40101 SET character_set_client = utf8 */;
 CREATE TABLE IF NOT EXISTS `venue` ( `id` int(4) NOT NULL AUTO_INCREMENT, `name` varchar(40) NOT NULL, `address` varchar(40) DEFAULT NULL, `city` varchar(20) DEFAULT NULL, `state` varchar(2) DEFAULT NULL, `zipcode` char(5) DEFAULT NULL, `weather_station_id` varchar(30) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 /*!50001 DROP TABLE IF EXISTS `race_num`*/;
 /*!50001 SET @saved_cs_client = @@character_set_client */;
 /*!50001 SET @saved_cs_results = @@character_set_results */;
 /*!50001 SET @saved_col_connection = @@collation_connection */;
 /*!50001 SET character_set_client = latin1 */;
 /*!50001 SET character_set_results = latin1 */;
 /*!50001 SET collation_connection = latin1_swedish_ci */;
 /*!50001 CREATE ALGORITHM=UNDEFINED lib/ log/ migrate/ www/ /*!50013 DEFINER=`dayan`@`localhost` SQL SECURITY DEFINER lib/ log/ migrate/ www/ /*!50001 VIEW `race_num` AS (select `r1`.`id` AS `id`,count(0) AS `number` from (`race` `r1` left join `race` `r2` on(((`r1`.`regatta` = `r2`.`regatta`) and (`r1`.`division` = `r2`.`division`)))) where (`r2`.`id` <= `r1`.`id`) group by `r1`.`id`) */;
 /*!50001 SET character_set_client = @saved_cs_client */;
 /*!50001 SET character_set_results = @saved_cs_results */;
 /*!50001 SET collation_connection = @saved_col_connection */;
 SET FOREIGN_KEY_CHECKS=1;

