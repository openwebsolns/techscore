<?php
/**
 * Cache RP complete for teams in a given regatta
 *
 * @author Dayan Paez
 * @created 2014-02-27
 */

require_once(dirname(__DIR__) . '/lib/conf.php');

function usage() {
  global $argv;
  printf("usage: %s <regatta id>\n", $argv[0]);
  exit(1);
}

if (count($argv) < 2)
  usage();

$reg = DB::getRegatta($argv[1]);
if ($reg === null) {
  printf("Invalid regatta ID provided: %s\n", $argv[1]);
  usage();
}

$rp = $reg->getRpManager();
foreach ($reg->getTeams() as $team) {
  $rp->resetCacheComplete($team);
}
?>