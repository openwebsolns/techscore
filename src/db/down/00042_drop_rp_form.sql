-- re-add rp_form table, used for caching RP PDF files
CREATE TABLE `rp_form` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filedata` mediumblob NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `rp_form_ibfk_2` FOREIGN KEY (`id`) REFERENCES `regatta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
