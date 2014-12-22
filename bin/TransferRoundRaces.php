<?php
/**
 * Transfer round's race order as entries
 *
 * @author Dayan Paez
 * @created 2014-04-02
 */

require_once(dirname(__DIR__) . '/lib/conf.php');

$to_add = array();
foreach (DB::getAll(DB::T(DB::ROUND)) as $round) {
  if (count(DB::getAll(DB::T(DB::ROUND_TEMPLATE), new DBCond('round', $round))) > 0)
    continue;

  try {
    if ($round->race_order !== null) {
      foreach ($round->race_order as $entry) {
	$pair = explode('-', $entry);

	$elem = new Round_Template();
	$elem->round = $round;
	$elem->team1 = $pair[0];
	$elem->team2 = $pair[1];
	$elem->boat = $round->boat;

	$to_add[] = $elem;
      }
    }
  }
  catch (Exception $e) { echo "ERROR: $round\n";}
}

DB::insertAll($to_add);
?>