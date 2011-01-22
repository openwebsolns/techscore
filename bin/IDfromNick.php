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

$season = Season::parse($argv[1]);
if ($season === null) {
  printf("Invalid season code provided: %s\n", $argv[1]);
  usage();
}

foreach ($season->getRegattas() as $reg) {
  if ($reg->nick == $argv[2]) {
    printf("%d\n", $reg->id);
    exit;
  }
}
exit(255);
?>