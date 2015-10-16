<?php
namespace rotation;

/**
 * Rotate a regatta's set of races.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
abstract class RacesRotator {

  /**
   * @var Array:Array list of races grouped by division.
   */
  protected $racesByDivision;

  /**
   * Create a new races rotator for given regatta.
   *
   * @param Array $racesByDivision list of races grouped by division,
   *   as a list of lists. Each sublist should be of the same size.
   */
  public function __construct(Array $racesByDivision) {
    $this->racesByDivision = $racesByDivision;
  }

  /**
   * Get the next set of races in the rotation.
   *
   * @param int $count the number of races to include from the same division.
   * @return Array of races in the next group. If this value is empty,
   *   then it means that there are no more races to return.
   */
  abstract public function nextRaces($racesPerSet);
}