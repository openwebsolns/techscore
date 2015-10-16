<?php
namespace rotation;

/**
 * Iterates over all divisions at once.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class SimilarStyleRacesRotator extends RacesRotator {

  public function nextRaces($racesPerSet) {
    $racesPerSet = (int) $racesPerSet;
    $list = array();
    foreach ($this->racesByDivision as &$races) {
      $added = 0;
      while ($added < $racesPerSet && count($races) > 0) {
        $list[] = array_shift($races);
        $added++;
      }
    }
    return $list;
  }

}