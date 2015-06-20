<?php
namespace data\finalize;

use \Regatta;

/**
 * Enforces PR 24: number of races sailed in each division.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class Pr24Criterion extends FinalizeCriterion {

  public function canApplyTo(Regatta $regatta) {
    return (
      $regatta->scoring == Regatta::SCORING_STANDARD
      && count($regatta->getDivisions()) > 1
    );
  }

  public function getFinalizeStatuses(Regatta $regatta) {
    $message = $this->passesPr24($regatta);
    if ($message === null) {
      return array(new FinalizeStatus(FinalizeStatus::VALID));
    }
    return array(new FinalizeStatus(FinalizeStatus::ERROR, $message));
  }

  /**
   * Determine if regatta passes PR 24 mandate.
   *
   * @param Regatta $regatta the regatta to evaluate.
   * @return String with message of invalidity, or null if OK.
   */
  public function passesPr24(Regatta $regatta) {
    $divisions = $regatta->getDivisions();
    $max = 0;
    $min = null;
    foreach ($divisions as $division) {
      $num = count($regatta->getScoredRaces($division));
      if ($num > $max)
        $max = $num;
      if ($min === null || $num < $min)
        $min = $num;
    }
    if ($regatta->getDuration() == 1) {
      if ($max != $min)
        return "PR 24b: Final regatta scores shall be based only on the scores of the races in which each division has completed an equal number.";
      return null;
    }
    elseif (($max - $min) > 2)
      return "PR 24b(i): Multi-day events: no more than two (2) additional races shall be scored in any one division more than the division with the least number of races.";
    return null;
  }
}