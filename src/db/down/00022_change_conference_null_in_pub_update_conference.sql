DELETE FROM pub_update_conference
  WHERE conference IS NULL
  ;
ALTER TABLE pub_update_conference
  DROP FOREIGN KEY `fk_pub_update_conference_conference`,
  CHANGE COLUMN conference conference VARCHAR(8) NOT NULL,
  ADD FOREIGN KEY `pub_update_conference_ibfk_1` (conference) REFERENCES conference(id) ON DELETE CASCADE ON UPDATE CASCADE
  ;
