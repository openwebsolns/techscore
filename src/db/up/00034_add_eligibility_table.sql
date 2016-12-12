-- track student-season eligibility record
CREATE TABLE eligibility (
  id INT UNSIGNED AUTO_INCREMENT,
  student_profile INT UNSIGNED NOT NULL,
  season MEDIUMINT(8) UNSIGNED NOT NULL,
  reason text DEFAULT NULL,
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by varchar(255) NULL,
  last_updated_on TIMESTAMP NULL DEFAULT NULL,
  last_updated_by varchar(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_eligibility_season (student_profile, season),
  CONSTRAINT `fk_eligibility_student_profile` FOREIGN KEY (student_profile) REFERENCES student_profile(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_eligibility_season` FOREIGN KEY (season) REFERENCES season(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
