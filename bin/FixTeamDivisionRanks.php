<?php
/**
 * Set division ranks based on stored team rank for a regatta passed by ID
 *
 * @author Dayan Paez
 * @version 2011-01-24
 * @package bin
 */

function usage() {
  global $argv;
  printf("usage: %s <regatta-id>\n", $argv[0]);
  exit(1);
}

if (count($argv) < 2)
  usage();

ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../lib'));
require_once('conf.php');
require_once('scripts/UpdateRegatta.php');
try {
  $reg = DB::getRegatta($argv[1]);
  if ($reg->scoring != Regatta::SCORING_TEAM)
    throw new InvalidArgumentException("Only team-racing regattas are allowed.");

  $teams = array();
  foreach ($reg->getTeams() as $team)
    $teams[$team->id] = $team;

  $ranker = $reg->getRanker();
  $divs = $reg->getDivisions();
  foreach ($ranker->rank($reg) as $rank) {
    $rank->rank = $teams[$rank->team->id]->dt_rank;
    $rank->explanation = $teams[$rank->team->id]->dt_explanation;
    foreach ($divs as $div)
      $reg->setDivisionRank($div, $rank);
  }
  $reg->setRpData();
}
catch (Exception $e) {
  printf("Invalid regatta ID provided: %s\n\n", $argv[1]);
  echo $e->getMessage(), "\n";
  usage();
}
?>
