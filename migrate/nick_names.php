<?php
/**
 * Give nick names to regatta that have none
 *
 */

require_once('conf.local.php');

$con = Preferences::getConnection();
$r = $con->query('select id from regatta where nick is null');
while ($reg = $r->fetch_object()) {
  $reg = new Regatta($reg->id);
  $nick = $reg->createNick();
  $reg->set(Regatta::NICK_NAME, $nick);
  printf("%4d: %15s -> %s\n", $reg->id(), $reg->get(Regatta::NAME), $nick);
}
?>