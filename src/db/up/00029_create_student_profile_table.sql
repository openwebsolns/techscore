-- track student profile, as part of the membership database migration
CREATE TABLE student_profile (
  id INT UNSIGNED AUTO_INCREMENT,
  school varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  first_name mediumtext COLLATE utf8_unicode_ci NOT NULL,
  last_name mediumtext COLLATE utf8_unicode_ci NOT NULL,
  gender enum('M','F') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'M',
  owner int(10) unsigned DEFAULT NULL,
  eligibility_start datetime DEFAULT NULL,
  status varchar(30) DEFAULT NULL,
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by varchar(255) NULL,
  last_updated_on TIMESTAMP NULL DEFAULT NULL,
  last_updated_by varchar(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT `fk_student_profile_owner` FOREIGN KEY (owner) REFERENCES account(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
