-- Store questions asked by end users through Questions feature
--  this will allow us to reference said question as well as
--  build an answer bank, for historical purposes.

CREATE TABLE question (
  id int unsigned auto_increment,
  asker int unsigned not null,
  subject varchar(255) not null,
  question text not null,
  referer text null default null,
  asked_on timestamp not null default current_timestamp,
  PRIMARY KEY (id),
  KEY asker (asker),
  CONSTRAINT `fk_question_asker` FOREIGN KEY (asker) REFERENCES account(id) on DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
