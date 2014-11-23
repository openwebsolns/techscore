
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `_schema_`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_schema_` (
  `id` varchar(100) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `downgrade` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aa_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aa_report` (
  `id` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `type` enum('coed','women','all') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'coed',
  `role` enum('skipper','crew') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'skipper',
  `seasons` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'List of seasons IDs',
  `conferences` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'List of conference IDs',
  `min_regattas` tinyint(3) unsigned NOT NULL DEFAULT '2',
  `regattas` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Array of regatta IDs',
  `sailors` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Array of sailor IDs',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `role` enum('student','coach','staff') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'coach',
  `password` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` enum('requested','pending','accepted','rejected','active','inactive') COLLATE utf8_unicode_ci DEFAULT 'pending',
  `admin` tinyint(4) DEFAULT NULL,
  `ts_role` mediumint(9) DEFAULT NULL,
  `message` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `ts_role` (`ts_role`),
  CONSTRAINT `account_ibfk_2` FOREIGN KEY (`ts_role`) REFERENCES `role` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_conference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_conference` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account` int(10) unsigned NOT NULL,
  `conference` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`,`conference`),
  KEY `conference` (`conference`),
  CONSTRAINT `account_conference_ibfk_1` FOREIGN KEY (`conference`) REFERENCES `conference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_account_conference_account` FOREIGN KEY (`account`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_school`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account` int(10) unsigned NOT NULL,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`,`school`),
  KEY `school` (`school`),
  CONSTRAINT `account_school_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_account_school_account` FOREIGN KEY (`account`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `boat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `boat` (
  `id` int(2) NOT NULL AUTO_INCREMENT,
  `name` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `min_crews` tinyint(4) NOT NULL DEFAULT '1',
  `max_crews` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `burgee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `burgee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `filedata` mediumblob NOT NULL,
  `width` tinyint(3) unsigned DEFAULT NULL,
  `height` tinyint(3) unsigned DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `school` (`school`),
  KEY `fk_burgee_updated_by` (`updated_by`),
  CONSTRAINT `burgee_ibfk_3` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_burgee_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `account` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conference` (
  `id` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `mail_lists` mediumtext COLLATE utf8_unicode_ci,
  `url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(5) NOT NULL,
  `summary_date` date NOT NULL,
  `summary` mediumtext COLLATE utf8_unicode_ci,
  `mail_sent` tinyint(4) DEFAULT NULL,
  `tweet_sent` tinyint(4) DEFAULT NULL,
  `rp_mail_sent` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `regatta` (`regatta`,`summary_date`),
  CONSTRAINT `daily_summary_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dt_rp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dt_rp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_division` int(11) NOT NULL,
  `sailor` mediumint(9) NOT NULL,
  `boat_role` enum('skipper','crew') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'skipper',
  `race_nums` text COLLATE utf8_unicode_ci NOT NULL,
  `rank` tinyint(3) unsigned DEFAULT NULL COMMENT 'In races sailed',
  `explanation` tinytext COLLATE utf8_unicode_ci COMMENT 'Rank explanation',
  PRIMARY KEY (`id`),
  KEY `team_division` (`team_division`),
  KEY `sailor` (`sailor`),
  CONSTRAINT `dt_rp_ibfk_1` FOREIGN KEY (`team_division`) REFERENCES `dt_team_division` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dt_rp_ibfk_2` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dt_team_division`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dt_team_division` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team` int(11) NOT NULL,
  `division` enum('A','B','C','D') COLLATE utf8_unicode_ci DEFAULT NULL,
  `rank` tinyint(3) unsigned NOT NULL,
  `explanation` text COLLATE utf8_unicode_ci,
  `penalty` enum('MRP','PFD','LOP','GDQ') COLLATE utf8_unicode_ci DEFAULT NULL,
  `comments` mediumtext COLLATE utf8_unicode_ci,
  `score` mediumint(8) unsigned DEFAULT NULL COMMENT 'Team races have no score.',
  `wins` mediumint(8) unsigned DEFAULT NULL,
  `losses` mediumint(8) unsigned DEFAULT NULL,
  `ties` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team` (`team`,`division`),
  CONSTRAINT `dt_team_division_ibfk_4` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Rank teams within divisions, and account for possible penalt';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_token` (
  `id` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `account` int(10) unsigned NOT NULL,
  `email` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `deadline` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  CONSTRAINT `fk_email_token_account` FOREIGN KEY (`account`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `finish`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finish` (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `race` int(7) NOT NULL,
  `team` int(7) NOT NULL,
  `entered` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `earned` tinyint(3) unsigned DEFAULT NULL COMMENT 'Minimum that an average score can earn.',
  `score` int(3) DEFAULT NULL,
  `explanation` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `race` (`race`,`team`),
  KEY `team` (`team`),
  CONSTRAINT `finish_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `finish_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `finish_modifier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finish_modifier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `finish` int(11) NOT NULL,
  `type` enum('DSQ','RAF','OCS','DNF','DNS','BKD','RDG','BYE') COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` tinyint(4) DEFAULT NULL,
  `displace` tinyint(4) DEFAULT NULL,
  `comments` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `finish` (`finish`),
  CONSTRAINT `finish_modifier_ibfk_1` FOREIGN KEY (`finish`) REFERENCES `finish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `host_school`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `host_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(11) NOT NULL,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `regatta` (`regatta`,`school`),
  KEY `school` (`school`),
  CONSTRAINT `host_school_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `host_school_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merge_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merge_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `error` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merge_regatta_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merge_regatta_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merge_sailor_log` int(10) unsigned NOT NULL,
  `regatta` int(5) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `merge_sailor_log` (`merge_sailor_log`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `merge_regatta_log_ibfk_1` FOREIGN KEY (`merge_sailor_log`) REFERENCES `merge_sailor_log` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `merge_regatta_log_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `merge_sailor_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merge_sailor_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merge_log` int(10) unsigned NOT NULL,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` text COLLATE utf8_unicode_ci NOT NULL,
  `first_name` text COLLATE utf8_unicode_ci NOT NULL,
  `year` char(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `gender` enum('M','F') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'M',
  `regatta_added` int(11) DEFAULT NULL,
  `registered_sailor` mediumint(9) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `merge_log` (`merge_log`),
  KEY `school` (`school`),
  KEY `registered_sailor` (`registered_sailor`),
  KEY `regatta_added` (`regatta_added`),
  CONSTRAINT `merge_sailor_log_ibfk_1` FOREIGN KEY (`merge_log`) REFERENCES `merge_log` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `merge_sailor_log_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `merge_sailor_log_ibfk_3` FOREIGN KEY (`registered_sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `merge_sailor_log_ibfk_4` FOREIGN KEY (`regatta_added`) REFERENCES `regatta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` int(10) unsigned DEFAULT NULL,
  `account` int(10) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_time` datetime DEFAULT NULL,
  `subject` varchar(100) COLLATE utf8_unicode_ci DEFAULT '',
  `content` mediumtext COLLATE utf8_unicode_ci,
  `inactive` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_message_account` (`account`),
  KEY `fk_message_sender` (`sender`),
  CONSTRAINT `fk_message_account` FOREIGN KEY (`account`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender`) REFERENCES `account` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `observation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `observation` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `race` int(7) NOT NULL,
  `observation` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `observer` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `noted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `race` (`race`),
  CONSTRAINT `observation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `outbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `queue_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recipients` enum('all','conferences','roles','users','schools','status') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'all',
  `arguments` text COLLATE utf8_unicode_ci,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `copy_sender` tinyint(4) DEFAULT NULL,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender` (`sender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penalty_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `penalty_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team` int(7) NOT NULL,
  `division` enum('A','B','C','D') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'A',
  `type` enum('MRP','PFD','LOP','GDQ') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'GDQ',
  `comments` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team` (`team`,`division`),
  CONSTRAINT `penalty_team_ibfk_1` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission` (
  `id` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `title` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_file` (
  `id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `filetype` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `filedata` mediumblob NOT NULL,
  `width` mediumint(8) unsigned DEFAULT NULL,
  `height` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_regatta_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_regatta_url` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(5) NOT NULL,
  `url` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `pub_regatta_url_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_sponsor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_sponsor` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `logo` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `relative_order` tinyint(3) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `logo` (`logo`),
  CONSTRAINT `pub_sponsor_ibfk_1` FOREIGN KEY (`logo`) REFERENCES `pub_file` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_update_conference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_update_conference` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conference` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `activity` enum('details','season','display','url') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'details',
  `season` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `argument` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conference` (`conference`),
  KEY `season` (`season`),
  CONSTRAINT `pub_update_conference_ibfk_1` FOREIGN KEY (`conference`) REFERENCES `conference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pub_update_conference_ibfk_2` FOREIGN KEY (`season`) REFERENCES `season` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_update_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_update_file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `activity` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_update_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_update_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(11) NOT NULL,
  `activity` enum('rotation','score','rp','details','summary','finalized','url','season','rank','team','document') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'score',
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `argument` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `pub_update_request_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_update_school`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_update_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `activity` enum('burgee','season','details','url') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'burgee',
  `season` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `argument` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `school` (`school`),
  KEY `season` (`season`),
  CONSTRAINT `pub_update_school_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pub_update_school_ibfk_2` FOREIGN KEY (`season`) REFERENCES `season` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pub_update_season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pub_update_season` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season` varchar(3) COLLATE utf8_unicode_ci NOT NULL,
  `activity` enum('regatta','details','front','404','school404') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'regatta',
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `season` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `race`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `race` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `regatta` int(5) NOT NULL,
  `division` enum('A','B','C','D') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'A',
  `number` tinyint(3) unsigned NOT NULL,
  `scored_day` int(11) DEFAULT NULL COMMENT 'Regatta day originally scored.',
  `boat` int(2) DEFAULT NULL,
  `scored_by` int(10) unsigned DEFAULT NULL,
  `tr_team1` int(11) DEFAULT NULL,
  `tr_team2` int(11) DEFAULT NULL,
  `tr_ignore1` tinyint(4) DEFAULT NULL,
  `round` int(11) DEFAULT NULL,
  `tr_ignore2` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  KEY `boat` (`boat`),
  KEY `tr_team1` (`tr_team1`),
  KEY `tr_team2` (`tr_team2`),
  KEY `race_ibfk_4` (`round`),
  KEY `fk_race_scored_by` (`scored_by`),
  CONSTRAINT `fk_race_scored_by` FOREIGN KEY (`scored_by`) REFERENCES `account` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_2` FOREIGN KEY (`boat`) REFERENCES `boat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_5` FOREIGN KEY (`tr_team1`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_6` FOREIGN KEY (`tr_team2`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `race_ibfk_7` FOREIGN KEY (`round`) REFERENCES `round` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `race_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `race_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` mediumtext COLLATE utf8_unicode_ci,
  `num_divisions` tinyint(3) unsigned NOT NULL,
  `num_teams` tinyint(3) unsigned NOT NULL,
  `master_teams` mediumtext COLLATE utf8_unicode_ci,
  `num_boats` tinyint(3) unsigned NOT NULL,
  `frequency` enum('frequent','infrequent','none') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'frequent',
  `template` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `author` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_race_order_author` (`author`),
  CONSTRAINT `fk_race_order_author` FOREIGN KEY (`author`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `regatta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `regatta` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(35) COLLATE utf8_unicode_ci NOT NULL,
  `nick` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `start_time` datetime DEFAULT NULL COMMENT 'Date and time when regatta started',
  `end_date` date DEFAULT NULL,
  `venue` int(4) DEFAULT NULL,
  `type` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `finalized` datetime DEFAULT NULL,
  `scoring` enum('standard','combined','team') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'standard',
  `participant` enum('women','coed') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'coed',
  `host_venue` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `private` tinyint(4) DEFAULT NULL,
  `inactive` datetime DEFAULT NULL COMMENT 'Deleted regattas, to be removed by the system.',
  `creator` int(10) unsigned DEFAULT NULL,
  `dt_num_divisions` tinyint(3) unsigned DEFAULT NULL,
  `dt_num_races` tinyint(3) unsigned DEFAULT NULL,
  `dt_hosts` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt_confs` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt_boats` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt_singlehanded` tinyint(3) unsigned DEFAULT NULL,
  `dt_season` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt_status` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venue` (`venue`),
  KEY `type` (`type`),
  KEY `dt_season` (`dt_season`),
  KEY `fk_regatta_creator` (`creator`),
  CONSTRAINT `fk_regatta_creator` FOREIGN KEY (`creator`) REFERENCES `account` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `regatta_ibfk_1` FOREIGN KEY (`venue`) REFERENCES `venue` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `regatta_ibfk_3` FOREIGN KEY (`type`) REFERENCES `type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `regatta_ibfk_4` FOREIGN KEY (`dt_season`) REFERENCES `season` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `regatta_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `regatta_document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(5) NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `url` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `category` enum('notice','protest','course_format') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'notice',
  `relative_order` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `filetype` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `filedata` mediumblob NOT NULL,
  `author` int(10) unsigned DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `width` mediumint(8) unsigned DEFAULT NULL,
  `height` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  KEY `fk_regatta_document_author` (`author`),
  CONSTRAINT `fk_regatta_document_author` FOREIGN KEY (`author`) REFERENCES `account` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `regatta_document_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `regatta_document_race`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `regatta_document_race` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document` int(11) NOT NULL,
  `race` int(7) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `document` (`document`),
  KEY `race` (`race`),
  CONSTRAINT `regatta_document_race_ibfk_1` FOREIGN KEY (`document`) REFERENCES `regatta_document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `regatta_document_race_ibfk_2` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `representative`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `representative` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team` int(7) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team` (`team`),
  CONSTRAINT `representative_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `title` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `has_all` tinyint(4) DEFAULT NULL,
  `is_default` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` mediumint(9) NOT NULL,
  `permission` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role` (`role`),
  KEY `permission` (`permission`),
  CONSTRAINT `role_permission_ibfk_1` FOREIGN KEY (`role`) REFERENCES `role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `role_permission_ibfk_2` FOREIGN KEY (`permission`) REFERENCES `permission` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rotation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rotation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race` int(7) NOT NULL,
  `team` int(7) NOT NULL,
  `sail` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Hex code, including hash',
  PRIMARY KEY (`id`),
  UNIQUE KEY `race` (`race`,`team`),
  KEY `team` (`team`),
  CONSTRAINT `rotation_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rotation_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `round`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `round` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(5) NOT NULL,
  `title` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `relative_order` tinyint(4) NOT NULL DEFAULT '1',
  `round_group` int(11) DEFAULT NULL,
  `num_teams` tinyint(3) unsigned NOT NULL,
  `num_boats` tinyint(3) unsigned NOT NULL,
  `race_order` text COLLATE utf8_unicode_ci,
  `rotation` text COLLATE utf8_unicode_ci,
  `rotation_frequency` enum('frequent','infrequent','none') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'frequent',
  `boat` int(2) DEFAULT NULL,
  `sailoff_for_round` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  KEY `round_group` (`round_group`),
  KEY `boat` (`boat`),
  KEY `sailoff_for_round` (`sailoff_for_round`),
  CONSTRAINT `round_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `round_ibfk_2` FOREIGN KEY (`round_group`) REFERENCES `round_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `round_ibfk_3` FOREIGN KEY (`boat`) REFERENCES `boat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `round_ibfk_4` FOREIGN KEY (`sailoff_for_round`) REFERENCES `round` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `round_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `round_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `round_seed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `round_seed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `round` int(11) NOT NULL,
  `team` int(7) NOT NULL,
  `seed` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `original_round` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `round` (`round`),
  KEY `team` (`team`),
  KEY `original_round` (`original_round`),
  CONSTRAINT `round_seed_ibfk_1` FOREIGN KEY (`round`) REFERENCES `round` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `round_seed_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `round_seed_ibfk_3` FOREIGN KEY (`original_round`) REFERENCES `round` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `round_slave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `round_slave` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master` int(11) NOT NULL,
  `slave` int(11) NOT NULL,
  `num_teams` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `master` (`master`),
  KEY `slave` (`slave`),
  CONSTRAINT `round_slave_ibfk_1` FOREIGN KEY (`master`) REFERENCES `round` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `round_slave_ibfk_2` FOREIGN KEY (`slave`) REFERENCES `round` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `round_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `round_template` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `round` int(11) NOT NULL,
  `team1` tinyint(3) unsigned NOT NULL,
  `team2` tinyint(3) unsigned NOT NULL,
  `boat` int(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `boat` (`boat`),
  CONSTRAINT `round_template_ibfk_1` FOREIGN KEY (`boat`) REFERENCES `boat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `race` int(7) NOT NULL,
  `team` int(7) NOT NULL,
  `sailor` mediumint(9) DEFAULT NULL,
  `boat_role` enum('skipper','crew') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'skipper',
  PRIMARY KEY (`id`),
  KEY `race` (`race`),
  KEY `team` (`team`),
  KEY `sailor` (`sailor`),
  CONSTRAINT `rp_ibfk_1` FOREIGN KEY (`race`) REFERENCES `race` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rp_ibfk_2` FOREIGN KEY (`team`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rp_ibfk_4` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rp_form`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rp_form` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filedata` mediumblob NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `rp_form_ibfk_2` FOREIGN KEY (`id`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rp_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rp_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regatta` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `rp_log_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sailor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sailor` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `icsa_id` int(10) unsigned DEFAULT NULL,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `first_name` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `year` char(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `role` enum('student','coach') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'student',
  `gender` enum('M','F') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'M',
  `regatta_added` int(11) DEFAULT NULL COMMENT 'For temp sailors, regatta when it was added.',
  `active` tinyint(4) DEFAULT NULL,
  `sync_log` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `school` (`school`),
  KEY `regatta_added` (`regatta_added`),
  KEY `sailor_sync_log1` (`sync_log`),
  CONSTRAINT `sailor_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sailor_ibfk_2` FOREIGN KEY (`regatta_added`) REFERENCES `regatta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sailor_ibfk_3` FOREIGN KEY (`sync_log`) REFERENCES `sync_log` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sailor_season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sailor_season` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sailor` mediumint(9) NOT NULL,
  `season` varchar(3) COLLATE utf8_unicode_ci NOT NULL,
  `activated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sailor` (`sailor`),
  KEY `season` (`season`),
  CONSTRAINT `sailor_season_ibfk_1` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sailor_season_ibfk_2` FOREIGN KEY (`season`) REFERENCES `season` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sailor_update`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sailor_update` (
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school` (
  `id` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `nick_name` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `url` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `conference` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `city` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `state` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `burgee` int(11) DEFAULT NULL,
  `burgee_small` int(11) DEFAULT NULL,
  `burgee_square` int(11) DEFAULT NULL,
  `inactive` datetime DEFAULT NULL,
  `sync_log` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conference` (`conference`),
  KEY `burgee` (`burgee`),
  KEY `burgee_small` (`burgee_small`),
  KEY `burgee_square` (`burgee_square`),
  KEY `school_sync_log1` (`sync_log`),
  CONSTRAINT `school_ibfk_1` FOREIGN KEY (`conference`) REFERENCES `conference` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `school_ibfk_2` FOREIGN KEY (`burgee`) REFERENCES `burgee` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `school_ibfk_3` FOREIGN KEY (`burgee_small`) REFERENCES `burgee` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `school_ibfk_4` FOREIGN KEY (`burgee_square`) REFERENCES `burgee` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `school_ibfk_5` FOREIGN KEY (`sync_log`) REFERENCES `sync_log` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_season` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `season` varchar(3) COLLATE utf8_unicode_ci NOT NULL,
  `activated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `school` (`school`),
  KEY `season` (`season`),
  CONSTRAINT `school_season_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `school_season_ibfk_2` FOREIGN KEY (`season`) REFERENCES `season` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scorer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scorer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account` int(10) unsigned NOT NULL,
  `regatta` int(5) NOT NULL,
  `principal` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`,`regatta`),
  KEY `regatta` (`regatta`),
  CONSTRAINT `fk_scorer_account` FOREIGN KEY (`account`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `scorer_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `season` (
  `id` varchar(3) COLLATE utf8_unicode_ci NOT NULL,
  `season` enum('fall','winter','spring','summer') COLLATE utf8_unicode_ci DEFAULT 'fall',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting` (
  `id` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sync_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `updated` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `error` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `regatta` int(5) NOT NULL,
  `school` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `lock_rank` tinyint(4) DEFAULT NULL,
  `rank_group` tinyint(3) unsigned DEFAULT NULL,
  `old_id` int(2) DEFAULT NULL,
  `dt_rank` tinyint(3) unsigned DEFAULT NULL,
  `dt_explanation` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dt_score` int(11) DEFAULT NULL,
  `dt_wins` mediumint(8) unsigned DEFAULT NULL,
  `dt_losses` mediumint(8) unsigned DEFAULT NULL,
  `dt_ties` mediumint(8) unsigned DEFAULT NULL,
  `dt_complete_rp` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regatta` (`regatta`),
  KEY `school` (`school`),
  CONSTRAINT `team_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `team_ibfk_2` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_name_prefs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_name_prefs` (
  `school` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rank` int(5) DEFAULT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `school` (`school`),
  CONSTRAINT `team_name_prefs_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_regatta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_regatta` (
  `regatta` int(5) NOT NULL,
  `original` int(5) NOT NULL,
  `expires` datetime NOT NULL,
  KEY `regatta` (`regatta`),
  KEY `original` (`original`),
  CONSTRAINT `temp_regatta_ibfk_1` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`),
  CONSTRAINT `temp_regatta_ibfk_2` FOREIGN KEY (`regatta`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `temp_regatta_ibfk_3` FOREIGN KEY (`original`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_sailor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_sailor` (
  `icsa_id` int(10) unsigned NOT NULL,
  `first_name` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `last_name` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `year` char(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `school` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `school_name` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `conference` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`icsa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `text_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `text_entry` (
  `id` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `plain` text COLLATE utf8_unicode_ci,
  `html` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `type` (
  `id` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `rank` tinyint(4) NOT NULL DEFAULT '1' COMMENT 'Smaller means more important',
  `mail_lists` text COLLATE utf8_unicode_ci,
  `tweet_summary` tinyint(4) DEFAULT NULL,
  `inactive` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `venue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `venue` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `address` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `city` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `state` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `zipcode` char(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `weather_station_id` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `websession`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `websession` (
  `id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `created` datetime NOT NULL,
  `last_modified` datetime NOT NULL,
  `expires` datetime DEFAULT NULL,
  `sessiondata` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

