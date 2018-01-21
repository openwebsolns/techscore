<?php
namespace eligibility;

use \Sailor;
use \Season;

/**
 * Calculates whether a sailor is eligible to participate in the "next" season.
 */
interface EligibilityCalculator {

  /**
   * @param Sailor $sailor the sailor to evaluate
   * @param Season $season able to sail in given season?
   * @return EligibilityReason
   */
  public function checkEligibility(Sailor $sailor, Season $season);
}