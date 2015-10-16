<?php
namespace regatta\rotation;

use \Round;
use \Sail;
use \SailsList;

/**
 * Assign sails in a round based on infrequent rotation.
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
class InfrequentTeamSailAssigner implements TeamSailAssigner {

  public function assignSails(Round $round, SailsList $sails, Array $teams, Array $divisions) {
    $list = array();

    // Group sails by number of divisions, indexed non-numerically
    $sail_groups = array();
    for ($i = 0; $i < $sails->count(); $i++) {
      $num = floor($i / count($divisions));
      $id = 'group' . $num;
      if (!isset($sail_groups[$id]))
        $sail_groups[$id] = array();

      $div = $divisions[$i % count($divisions)];

      $sail = new Sail();
      $sail->sail = $sails->sails[$i];
      $sail->color = $sails->colors[$i];
      $sail_groups[$id][(string) $div] = $sail;
    }

    // Group races into flights
    $race_groups = array();
    $flight_size = $sails->count() / (2 * count($divisions));
    for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
      $flight = floor($i / $flight_size);
      if (!isset($race_groups[$flight]))
        $race_groups[$flight] = array();

      $race_groups[$flight][] = $i;
    }

    // Keep teams on same sail numbers when going from one flight to
    // the next. Organize by "sail group index"
    $group_names = array_keys($sail_groups);
    $prev_flight = array();
    $next_flight = null;

    foreach ($race_groups as $group) {
      $next_flight = array();
      $available_groups = $group_names;

      // First, assign all carry overs, then distribute remaining
      foreach ($group as $race_num) {
        $pair = $round->getRaceOrderPair($race_num);
        $list[$race_num] = array();

        // First team
        if (isset($prev_flight[$pair[0]])) {
          $group_name = $prev_flight[$pair[0]];
          $i = array_search($group_name, $available_groups);
          array_splice($available_groups, $i, 1);
          $list[$race_num][$pair[0]] = $sail_groups[$group_name];
          $next_flight[$pair[0]] = $group_name;
        }

        // Second team
        if (isset($prev_flight[$pair[1]])) {
          $group_name = $prev_flight[$pair[1]];
          $i = array_search($group_name, $available_groups);
          array_splice($available_groups, $i, 1);
          $list[$race_num][$pair[1]] = $sail_groups[$group_name];
          $next_flight[$pair[1]] = $group_name;
        }
      }

      // Next, use up the boats not yet assigned
      foreach ($group as $race_num) {
        $pair = $round->getRaceOrderPair($race_num);

        if (!isset($list[$race_num][$pair[0]])) {
          $group_name = array_shift($available_groups);
          $list[$race_num][$pair[0]] = $sail_groups[$group_name];
          $next_flight[$pair[0]] = $group_name;
        }

        if (!isset($list[$race_num][$pair[1]])) {
          $group_name = array_shift($available_groups);
          $list[$race_num][$pair[1]] = $sail_groups[$group_name];
          $next_flight[$pair[1]] = $group_name;
        }
      }
      $prev_flight = $next_flight;
    }
    return $list;
  }
}