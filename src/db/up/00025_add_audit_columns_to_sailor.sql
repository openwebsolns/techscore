ALTER TABLE sailor
  ADD COLUMN  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN  `created_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  ADD COLUMN  `last_updated_on` timestamp NULL DEFAULT NULL,
  ADD COLUMN  `last_updated_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
;

UPDATE sailor SET created_by = 0;
