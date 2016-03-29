-- Create a DB version of the access logs, with POST/GET data.
CREATE TABLE websession_log (
  id INT UNSIGNED AUTO_INCREMENT,
  websession varchar(32) NOT NULL,
  `method` enum('GET', 'POST', 'HEAD') NOT NULL DEFAULT 'GET',
  url mediumtext NOT NULL,
  user_agent mediumtext DEFAULT NULL,
  http_referer mediumtext DEFAULT NULL,
  post mediumtext DEFAULT NULL,
  response_code varchar(3) NOT NULL DEFAULT '200',
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by varchar(255) NULL,
  last_updated_on TIMESTAMP NULL DEFAULT NULL,
  last_updated_by varchar(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
