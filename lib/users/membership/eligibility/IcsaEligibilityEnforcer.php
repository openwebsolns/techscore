<?php
namespace users\membership\eligibility;

use \Metric;
use \model\StudentProfile;

/**
 * Determine what season a sailor's eligible to sail in.
 */
class IcsaEligibilityEnforcer implements EligibilityEnforcer {
  /**
   * Only 8 consecutive seasons since initial eligibility.
   *
   * @param StudentProfile $profile The sailor
   * @param Array:Season $seasons list of seasons to enforce.
   * @return Array:EligibilityResult for each requested season.
   */
  public function calculateEligibleSeasons(StudentProfile $profile, $seasons) {
    if ($profile->eligibility_start === null) {
      Metric::publish(Metric::MISSING_ELIGIBILITY_START);
      return $this->convertToStatus($seasons, EligibilityResult::STATUS_OK);
    }

    // Profile has up to 8 consecutive seasons since start of
    // eligibility to compete. If ICSA skips a season, or if it adds a
    // season (winter?), then it counts as well.
  }

  private function convertToStatus($seasons, $status, $reason = null) {
    return array_map(function($season) use ($status, $reason) {
      return new EligibilityResult($season, $status, $reason);
    }, $seasons);
  }
}