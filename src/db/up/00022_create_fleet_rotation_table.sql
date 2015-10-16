-- track the arguments used when creating fleet rotations
CREATE TABLE fleet_rotation (
  id INT UNSIGNED AUTO_INCREMENT,
  regatta int(5) NOT NULL,
  division_order varchar(255) NULL,
  rotation_type enum('standard','swap','none') NOT NULL DEFAULT 'none',
  rotation_style enum('copy','navy','fran') NULL DEFAULT NULL,
  races_per_set INT UNSIGNED NULL DEFAULT NULL,
  sails_list text NOT NULL,
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by varchar(255) NULL,
  last_updated_on TIMESTAMP NULL DEFAULT NULL,
  last_updated_by varchar(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT `fk_fleet_rotation_regatta` FOREIGN KEY (regatta) REFERENCES regatta(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
