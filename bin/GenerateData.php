<?php
/**
 * Scours the regatta database, updating the information about each of
 * the finalized regatta's and compiling that data into the dt_*
 * tables for easier retrieval by update scripts, etc.
 *
 * This script is meant to be run from the command line
 *
 * @author Dayan Paez
 * @version 2011-01-18
 */

ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../lib'));
require_once('../lib/conf.php');

$con = Preferences::getConnection();
// get all finalized, non-personal regattas and go to town!
$res = $con->query('select id from regatta where type <> "personal" and finalized is not null');
while ($obj = $res->fetch_object()) {
  $reg = new Regatta($obj->id);
  $data = array();
  $data['id'] = $reg->id();
  $data['name'] = $reg->get(Regatta::NAME);
  $data['nick'] = $reg->get(Regatta::NICK_NAME);
  $data['start_time'] = $reg->get(Regatta::START_TIME)->format('Y-m-d H:i:s');
  $data['end_date']   = $reg->get(Regatta::END_DATE)->format('Y-m-d');
  $data['type'] = $reg->get(Regatta::TYPE);
  $data['finalized'] = $reg->get(Regatta::FINALIZED)->format('Y-m-d H:i:s');
  $data['scoring'] = $reg->get(Regatta::SCORING);

  $data['venue'] = $reg->get(Regatta::VENUE);
  if ($data['venue'] != null)
    $data['venue'] = $data['venue']->id;
  
  $divs = $reg->getDivisions();
  $races = $reg->getScoredRaces();
  $data['num_divisions'] = count($divs);
  $data['num_races'] = count($races) / $data['num_divisions'];

  // hosts and conferences
  $confs = array();
  $hosts = array();
  foreach ($reg->getHosts() as $host) {
    $confs[$host->school->conference->id] = $host->school->conference->nick;
    $hosts[$host->school->id] = $host->school->id;
  }
  $data['hosts'] = implode(',', $hosts);
  $data['confs'] = implode(',', $confs);
  unset($hosts, $confs);

  // boats
  $boats = array();
  foreach ($reg->getBoats() as $boat)
    $boats[$boat->id] = $boat->name;
  $data['boats'] = implode(',', $boats);

  $data['singlehanded'] = "NULL";
  if ($reg->isSingleHanded())
    $data['singlehanded'] = 1;
  
  $data['season'] = $reg->get(Regatta::SEASON)->getSeason();

  // prep super query
  $cols = array_keys($data);
  $q = sprintf('replace into dt_regatta (%s) values ("%s")',
	       implode(',', $cols),
	       implode('","', $data));
  $q = str_replace('"NULL"', 'NULL', $q);
  $con->query($q);

  // teams
  $teams = array();
  foreach ($reg->scorer->rank($reg) as $i => $rank) {
    $teams[] = $rank->team;
    $data = array();
    $data['id'] = $rank->team->id;
    $data['regatta'] = $reg->id();
    $data['school'] = $rank->team->school->id;
    $data['name'] = $rank->team->name;
    $data['rank'] = $i + 1;
    $data['rank_explanation'] = $rank->explanation;

    $cols = array_keys($data);
    $q = sprintf('replace into dt_team (%s) values ("%s")',
		 implode(',', $cols), implode('","', $data));
    $con->query($q);
  }

  foreach ($teams as $team) {
    foreach ($races as $race) {
      $finish = $reg->getFinish($race, $team);
      if ($finish !== null) {
	$data = array();
	$data['dt_team'] = $team->id;
	$data['race_num'] = $race->number;
	$data['division'] = $race->division;
	$data['place'] = $finish->score->place;
	$data['score'] = $finish->score->score;
	$data['explanation'] = $finish->score->explanation;

	$cols = array_keys($data);
	$q = sprintf('replace into dt_score (%s) values ("%s")',
		     implode(',', $cols),
		     implode('","', $data));
	$con->query($q);
      }
    }
  }

  // rp information
  $man = $reg->getRpManager();
  foreach ($teams as $team) {
    foreach ($divs as $div) {
      foreach (array(RP::SKIPPER, RP::CREW) as $role) {
	foreach ($man->getRP($team, $div, $role) as $rp) {
	  foreach ($rp->races_nums as $race_num) {
	    $data = array();
	    $data['dt_team'] = $team->id;
	    $data['race_num'] = $race_num;
	    $data['division'] = $div;
	    $data['sailor'] = $rp->sailor->id;
	    $data['boat_role'] = $role;

	    $cols = array_keys($data);
	    $q = sprintf('replace into dt_rp (%s) values ("%s")',
			 implode(',', $cols),
			 implode('","', $data));
	    $con->query($q);
	  }
	}
      }
    }
  }

  printf("(%3d) Imported regatta %s\n", $reg->id(), $reg->get(Regatta::NAME));
}
?>