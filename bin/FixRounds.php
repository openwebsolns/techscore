<?php
/**
 * Backfill data for team racing rounds
 *
 * @author Dayan Paez
 * @created 2014-01-20
 */

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('regatta/Regatta.php');

foreach (DB::getAll(DB::$ROUND) as $round) {
  if ($round->regatta !== null && $round->num_teams == 0) {
    printf("%4d: %35s from %s", $round->id, $round, $round->regatta->name);

    $divisions = $round->regatta->getDivisions();
    array_shift($divisions);

    // Number of teams
    $teams = $round->regatta->getTeamsInRound($round);
    $round->num_teams = count($teams);

    // Seeds
    $masters = $round->getMasters();
    $orig_rounds = array();
    foreach ($masters as $parent_round) {
      foreach ($round->regatta->getTeamsInRound($parent_round->master) as $team) {
        $orig_rounds[$team->id] = $parent_round->master;
      }
    }
    $seeds = array();
    $team2seed = array(); // map team ID to seed number
    foreach ($teams as $num => $team) {
      $seed = new Round_Seed();
      $seed->team = $team;
      $seed->seed = $num + 1;
      if (isset($orig_rounds[$team->id]))
        $seed->original_round = $orig_rounds[$team->id];
      $seeds[] = $seed;
      $team2seed[$team->id] = $num + 1;
    }
    $round->setSeeds($seeds);

    // Rotation frequency: always frequent
    $round->rotation_frequency = Race_Order::FREQUENCY_FREQUENT;

    // Boat
    $races = $round->regatta->getRacesInRound($round, Division::A(), false);
    $round->boat = $races[0]->boat;

    // Re-construct the race order
    $rotation = $round->regatta->getRotation();
    $order = array();
    $sails = array();
    $colors = array();
    foreach ($races as $race) {
      $order[] = sprintf("%d-%d", $team2seed[$race->tr_team1->id], $team2seed[$race->tr_team2->id]);
      $sail = $rotation->getSail($race, $race->tr_team1);
      if ($sail !== null) {
        $sails[$sail->sail] = $sail->sail;
        $colors[$sail->sail] = $sail->color;
        foreach ($divisions as $div) {
          $r = $round->regatta->getRace($div, $race->number);
          $sail = $rotation->getSail($r, $race->tr_team1);
          $sails[$sail->sail] = $sail->sail;
          $colors[$sail->sail] = $sail->color;
        }

        $sail = $rotation->getSail($race, $race->tr_team2);
        $sails[$sail->sail] = $sail->sail;
        $colors[$sail->sail] = $sail->color;
        foreach ($divisions as $div) {
          $r = $round->regatta->getRace($div, $race->number);
          $sail = $rotation->getSail($r, $race->tr_team2);
          $sails[$sail->sail] = $sail->sail;
          $colors[$sail->sail] = $sail->color;
        }
      }
    }
    $round->race_order = $order;

    // Rotation?
    if (count($sails) > 0) {
      $round->setRotation($sails, $colors);
      $round->num_boats = count($sails);
    }
    else {
      $round->num_boats = 6 * (1 + count($divisions));
    }

    DB::set($round);
    DB::commit();
    print("...done\n");
  }
}
?>