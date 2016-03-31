-- keep every contact version
CREATE TABLE student_profile_contact (
  id INT UNSIGNED AUTO_INCREMENT,
  student_profile INT UNSIGNED NOT NULL,
  contact_type enum('home', 'school') NOT NULL DEFAULT 'school',
  email varchar(255) NOT NULL,
  address_1 mediumtext NOT NULL,
  address_2 mediumtext DEFAULT NULL,
  city tinytext NOT NULL,
  state varchar(50) NOT NULL,
  postal_code varchar(15) NOT NULL,
  telephone varchar(20) DEFAULT NULL,
  secondary_telephone varchar(20) DEFAULT NULL,
  current_until datetime DEFAULT NULL,
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by varchar(255) NULL,
  last_updated_on TIMESTAMP NULL DEFAULT NULL,
  last_updated_by varchar(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT `fk_student_profile_contact_student_profile` FOREIGN KEY (student_profile) REFERENCES student_profile(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
