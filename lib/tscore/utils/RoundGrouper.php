<?php
namespace tscore\utils;

use \FullRegatta;
use \Round;

/**
 * Groups two or more rounds together so that their race flights are
 * interwoven: first flight from first round, second flight from
 * second round, and so on.
 *
 * A flight size is a property of the round and depends on the number
 * of boats in it. Some rounds may have two races per flight, others
 * three. Rounds with different flight sizes can still be grouped.
 *
 * When rounds are grouped together, their races are renumbered to
 * reflect their new order. Rounds to be grouped may not be already in
 * a group.
 *
 * This feature is only available to team racing events.
 */
class RoundGrouper {

  private $regatta;
  private $firstDivision;
  private $otherDivisions;

  /**
   * @param FullRegatta $regatta to fetch races, etc.
   */
  public function __construct(FullRegatta $regatta) {
    $this->regatta = $regatta;
    $this->otherDivisions = $regatta->getDivisions();
    $this->firstDivision = array_shift($this->otherDivisions);
  }

  /**
   * Actually perform the grouping.
   *
   * Assumes valid input.
   *
   * @param Array:Round $rounds the rounds to group
   * @return Array:Race the races updated by the grouping; ready to be committed.
   */
  public function group(Array $rounds) {
    // contains only first division races
    $flightsByRound = array();
    foreach ($rounds as $round) {
      $flightsByRound[] = $this->getFlightsForRound($round);
    }
    return $this->reorderRaces($flightsByRound);
  }

  /**
   * Helper function puts races in correct order and renumbers them.
   *
   * Cycles through the queues of flights, taking first available and
   * adding their races (including from other divisions) to the total
   * list of races until exhausting all queues. Renumbers the races
   * along the way.
   *
   * @param Array $flightsByRound list of queues of groups of races
   *   (each group is called a flight). Each flight is copied to the
   *   final list of races in appearance order. Only first division
   *   races are provided.
   * @return Array:Race all races from the regatta involved in the grouping.
   */
  private function reorderRaces(Array $flightsByRound) {
    $newRaceNumber = null;
    $newRaceOrder = array();
    $flightIndex = 0;
    while (count($flightsByRound) > 0) {
      $flights = &$flightsByRound[$flightIndex];
      foreach (array_shift($flights) as $race) {
        $newRaceNumber = ($newRaceNumber === null) ? $race->number : $newRaceNumber + 1;
        $this->addRacesAcrossOtherDivisions($race, $newRaceNumber, $newRaceOrder);
      }

      if (count($flights) === 0) {
        array_splice($flightsByRound, $flightIndex, 1);
      } else {
        $flightIndex++;
      }

      if ($flightIndex >= count($flightsByRound)) {
        $flightIndex = 0;
      }
    }
    return $newRaceOrder;
  }

  /**
   * Helper method to transfer and renumber given race from first
   * division and others with the same (old) number from the other
   * divisions.
   *
   * @param Race $template the original race from first division
   * @param Integer $newNumber the number to assign to all the races
   * @param Array:Race $races the running list of races to append to
   */
  private function addRacesAcrossOtherDivisions($template, $newNumber, Array &$races) {
    $races[] = $template;
    foreach ($this->otherDivisions as $division) {
      $race = $this->regatta->getRace($division, $template->number);
      $race->number = $newNumber;
      $races[] = $race;
    }
    $template->number = $newNumber;
  }

  /**
   * Fetch groups of race flights containing first division.
   *
   * @param Round $round whose first-division races to group
   * @return Array:Array:Race round's races, grouped by flights
   */
  private function getFlightsForRound(Round $round) {
    $flightSize = $round->num_boats / $this->regatta->getFleetSize();
    $flights = array();
    $flight = array();

    foreach ($this->regatta->getRacesInRound($round, $this->firstDivision) as $race) {
      $flight[] = $race;
      if (count($flight) == $flightSize) {
        $flights[] = $flight;
        $flight = array();
      }
    }
    if (count($flight) > 0) {
      $flights[] = $flight;
    }

    return $flights;
  }
}
