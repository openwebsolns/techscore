<?php
namespace users\membership\eligibility;

use \model\StudentProfile;

/**
 * Determine what season a sailor's eligible to sail in.
 */
interface EligibilityEnforcer {
  /**
   * Filter seasons of eligibility from given list.
   *
   * @param StudentProfile $profile The sailor
   * @param Array:Season $seasons list of seasons to enforce.
   * @return Array:EligibilityResult for each requested season.
   */
  public function calculateEligibleSeasons(StudentProfile $profile, $seasons);
}