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