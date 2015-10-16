<?php
namespace rotation;

/**
 * Switch divisions with each iteration.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class NavyStyleRacesRotator extends RacesRotator {

  public function nextRaces($racesPerSet) {
    $racesPerSet = (int) $racesPerSet;
    $list = array();
    $races = array_shift($this->racesByDivision);
    while (count($list) < $racesPerSet && count($races) > 0) {
      $list[] = array_shift($races);
    }
    $this->racesByDivision[] = $races;
    return $list;
  }

}