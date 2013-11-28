<?php
/*
 * Cache values of RP status for all regattas
 *
 * @author Dayan Paez
 * @version 2013-11-28
 */

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('regatta/Regatta.php');

foreach (DB::getAll(DB::$REGATTA, new DBCond('start_time', DB::$NOW, DBCond::LE)) as $reg) {
  printf("Regatta: (%s) %s\n", $reg->getSeason(), $reg->name);
  $rp = $reg->getRpManager();
  foreach ($reg->getTeams() as $team) {
    printf("  %s...", $team);
    $rp->setCacheComplete($team);
    printf("%s\n", ($team->dt_complete_rp === null) ? "N" : "Y");
  }
  DB::commit();
  DB::resetCache();
}
?>