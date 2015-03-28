<?php
namespace charts;

use \InvalidArgumentException;

use \FullRegatta;
use \Division;

/**
 * Creates charts for a fleet racing regatta.
 *
 * @author Dayan Paez
 * @version 2015-03-23
 */
class RegattaChartCreator {

  /**
   * Returns the chart for given parameters, or null.
   *
   * @param FullRegatta $regatta the fleet racing regatta.
   * @param Division $division the specific division, if any.
   * @return RaceProgressChart, or null, if unavailable.
   */
  public static function getChart(FullRegatta $regatta, Division $division = null) {
    try {
      $races = $regatta->getScoredRaces($division);
      $title = ($division === null) ?
        sprintf("Rank history across all divisions for %s", $regatta->name) :
        sprintf("Rank history across Division %s for %s", $division, $regatta->name);

      $maker = new RaceProgressChart($regatta);
      return $maker->getChart($races, $title);
    } catch (InvalidArgumentException $e) {
      return null;
    }
  }
}