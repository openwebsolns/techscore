<?php
namespace regatta\rotation;

use \Round;
use \Sail;
use \SailsList;

/**
 * Assigns sails based on frequent rotation style.
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
class FrequentTeamSailAssigner implements TeamSailAssigner {

  public function assignSails(Round $round, SailsList $sails, Array $teams, Array $divisions) {
    $list = array();
    $sailIndex = 0;
    $num_divs = count($divisions);
    for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
      $pair = $round->getRaceOrderPair($i);
      $list[$i] = array($pair[0] => array(), $pair[1] => array());
      foreach ($divisions as $div) {
        $sail = new Sail();
        $sail->sail = $sails->sails[$sailIndex];
        $sail->color = $sails->colors[$sailIndex];
        $list[$i][$pair[0]][(string)$div] = $sail;

        $sail = new Sail();
        $sail->sail = $sails->sails[$sailIndex + $num_divs];
        $sail->color = $sails->colors[$sailIndex + $num_divs];
        $list[$i][$pair[1]][(string) $div] = $sail;

        $sailIndex = ($sailIndex + 1) % $sails->count();
      }
      $sailIndex = ($sailIndex + $num_divs) % $sails->count();
    }
    return $list;
  }
}