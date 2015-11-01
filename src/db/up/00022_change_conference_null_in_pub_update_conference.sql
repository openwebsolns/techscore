ALTER TABLE pub_update_conference
  DROP FOREIGN KEY `pub_update_conference_ibfk_1`,
  CHANGE COLUMN conference conference VARCHAR(8) NULL,
  ADD FOREIGN KEY `fk_pub_update_conference_conference` (conference) REFERENCES conference(id) ON DELETE SET NULL ON UPDATE CASCADE
  ;
