<?php
namespace regatta\rotation;

use \Round;
use \SailsList;

/**
 * Assigns sails from a given Round and list of sails.
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
interface TeamSailAssigner {

  /**
   * Creates and returns sail #/color assignment for given frequency
   *
   * @param Round $round the round whose race_order to use
   * @param Array:Team $teams ordered list of teams
   * @param Array:Division the number of divisions
   * @return Array a map indexed first by race number, and then by
   * team index, and then by divisions
   */
  public function assignSails(Round $round, SailsList $sails, Array $teams, Array $divisions);
}