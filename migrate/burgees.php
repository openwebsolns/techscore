<?php
/**
 * Move the burgees from school to a different table
 *
 * @author Dayan Paez
 * @version 2010-07-14
 */

$USER = "root";
$HOST = "localhost";
$DB   = "ts2";

echo "Password: ";
$PASS = substr(`stty -echo; head -n1`, 0, -1);
`stty echo`;

$con = new MySQLi($HOST, $USER, $PASS, $DB);
$q = 'CREATE TABLE if not exists `burgee` (
  `school` varchar(10) NOT NULL,
  `filedata` mediumblob NOT NULL,
  `last_updated` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_by` varchar(40) default NULL,
  PRIMARY KEY  (`school`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `burgee_ibfk_1` FOREIGN KEY (`school`) REFERENCES `school` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `burgee_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `account` (`username`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB';
$con->query($q);
if (!$con->error)
  echo "Table created.\n";

$r = $con->query('select id, burgee from school where burgee is not null');
while ($obj = $r->fetch_object()) {
  $filename = sprintf('../www/%s', $obj->burgee);
  $q = sprintf('replace into burgee (school, filedata, updated_by) values ("%s", "%s", "paez@mit.edu")',
               $obj->id, base64_encode(file_get_contents($filename)));
  $con->query($q);
  printf("Did burgee for %s.\n", $obj->id);
}

$con->close();
?>