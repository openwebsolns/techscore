-- remove regatta_logo column
ALTER TABLE pub_sponsor
  DROP FOREIGN KEY `fk_pub_sponsor_regatta_logo`,
  DROP COLUMN regatta_logo,
  DROP FOREIGN KEY `fk_pub_sponsor_logo`,
  ADD CONSTRAINT `pub_sponsor_ibfk_1`
    FOREIGN KEY (logo)
    REFERENCES pub_file(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
