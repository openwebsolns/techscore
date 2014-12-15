-- track answers provided to each question
CREATE TABLE answer (
  id INT UNSIGNED AUTO_INCREMENT,
  question INT UNSIGNED NOT NULL,
  answered_by INT UNSIGNED NOT NULL,
  answered_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  answer text NOT NULL,
  publishable TINYINT NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY question (question),
  KEY answered_by (answered_by),
  CONSTRAINT `fk_answer_question` FOREIGN KEY (question) REFERENCES question(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_answer_answered_by` FOREIGN KEY (answered_by) REFERENCES account(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
