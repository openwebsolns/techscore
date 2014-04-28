<?php
/**
 * Determine the if of the regatta based on the season and the
 * nick-name, passed as arguments
 *
 * @author Dayan Paez
 * @version 2011-01-22
 */

ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../lib'));
require_once('conf.php');

function usage() {
  global $argv;
  printf("usage: %s <season:f10> <nick-name:oberg>\n", $argv[0]);
  exit(1);
}

if (count($argv) < 3)
  usage();

$season = DB::getSeason($argv[1]);
if ($season === null) {
  printf("Invalid season code provided: %s\n", $argv[1]);
  usage();
}

$reg = $season->getRegattaWithURL($argv[2]);
if ($reg !== null) {
  printf("%d\n", $reg->id);
  exit;
}
exit(255);
?>