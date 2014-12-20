-- Allow regatta-level sponsor by associating a file with a
-- pub_sponsor's new field regatta_logo
ALTER TABLE pub_sponsor
  DROP FOREIGN KEY `pub_sponsor_ibfk_1`,
  ADD CONSTRAINT `fk_pub_sponsor_logo`
    FOREIGN KEY (logo)
    REFERENCES pub_file(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  ADD COLUMN regatta_logo
    varchar(32) NULL DEFAULT NULL
    AFTER logo,
  ADD CONSTRAINT `fk_pub_sponsor_regatta_logo`
    FOREIGN KEY (regatta_logo)
    REFERENCES pub_file(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
