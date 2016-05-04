<?php
namespace model;

use \Boat;

/**
 * A boat with statistics.
 *
 * As it is backed by a view, this object is not settable. (TODO?)
 *
 * @author Dayan Paez
 * @version 2016-05-04
 */
class RankedBoat extends Boat {
  public $num_races;
  protected function db_order() {
    return array('num_races' => false);
  }
}