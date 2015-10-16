<?php
namespace regatta\rotation;

use \Round;
use \Sail;
use \SailsList;

/**
 * Assigns sails for given round with no change!
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
class ConstantTeamSailAssigner implements TeamSailAssigner {

  public function assignSails(Round $round, SailsList $sails, Array $teams, Array $divisions) {
    $list = array();
    // Assign the sails to the teams
    $sailIndex = 0;

    $team_sails = array();
    foreach ($teams as $i => $team) {
      $team_sails[$i] = array();
      foreach ($divisions as $div) {
        $sail = new Sail();
        $sail->sail = $sails->sails[$sailIndex];
        $sail->color = $sails->colors[$sailIndex];
        $team_sails[$i][(string) $div] = $sail;

        $sailIndex++;
      }
    }

    for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
      $pair = $round->getRaceOrderPair($i);
      $list[] = array($pair[0] => $team_sails[$pair[0] - 1],
                      $pair[1] => $team_sails[$pair[1] - 1]);
    }
    return $list;
  }
}