-- Sailors have URLs too, for public sailor profile
-- Also add a new update table
ALTER TABLE sailor ADD COLUMN url varchar(255) NULL DEFAULT NULL AFTER gender;

CREATE TABLE `pub_update_sailor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sailor` mediumint(9) NOT NULL,
  `activity` enum('name','season','details','url') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'name',
  `season` mediumint(8) unsigned DEFAULT NULL,
  `argument` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completion_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sailor` (`sailor`),
  KEY `fk_pub_update_sailor_season` (`season`),
  CONSTRAINT `fk_pub_update_sailor_season` FOREIGN KEY (`season`) REFERENCES `season` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pub_update_sailor_sailor` FOREIGN KEY (`sailor`) REFERENCES `sailor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
