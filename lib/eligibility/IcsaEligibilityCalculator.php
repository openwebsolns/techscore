<?php
namespace eligibility;

use \Sailor;
use \Season;

/**
 * Calculates whether a sailor is eligible to participate in the "next" season.
 */
class IcsaEligibilityCalculator implements EligibilityCalculator {

  const MAX_TOTAL_SEASONS = 8;
  const MAX_TOTAL_YEARS = 5;
  const MAX_TOTAL_YEARS_AFTER_2018 = 4;

  /**
   * @param Sailor $sailor the sailor to evaluate
   * @param Season $season can sailor participate in this season?
   * @return EligibilityReason with explanation.
   */
  public function checkEligibility(Sailor $sailor, Season $season) {
    $seasonsActive = array();
    foreach ($sailor->getSeasonsActive() as $s) {
      if (Season::cmp($s, $season) <= 0) {
        $seasonsActive[$s->id] = $s;
      }
    }

    // ...has 8 semesters of eligibility...
    $seasonsUsed = count($seasonsActive);
    if (array_key_exists($season->id, $seasonsActive)) {
      $seasonsUsed--;
    }
    if ($seasonsUsed >= self::MAX_TOTAL_SEASONS) {
      return new EligibilityReason(false, sprintf('Sailed %d seasons', self::MAX_TOTAL_SEASONS));
    }

    // ...to use within 5 years of first event
    $seasonsSailed = $this->getAttending($sailor, $seasonsActive);
    $numSailed = count($seasonsSailed);
    if ($numSailed > 0) {
      $firstSeason = $seasonsSailed[$numSailed - 1];
      $maxYears = ($sailor->year >= 2018) ? self::MAX_TOTAL_YEARS_AFTER_2018 : self::MAX_TOTAL_YEARS;
      $yearsBetween = $this->calculateYearsBetween($firstSeason, $season);
      if ($yearsBetween > $maxYears) {
        $reason = sprintf('Exceed max %d years since first sailed event.', $maxYears);
        return new EligibilityReason(false, $reason);
      }
    }

    // graduated?
    if ($season->getSeason() === Season::FALL && $season->getYear() >= $sailor->year) {
      return new EligibilityReason(false, 'Graduated');
    }

    return new EligibilityReason(true);
  }

  private function calculateYearsBetween(Season $first, Season $second) {
    $diff = $first->start_date->diff($second->start_date);
    return round($diff->days / 365);
  }

  private function getAttending(Sailor $sailor, Array $seasons) {
    $participating = array();
    foreach ($seasons as $season) {
      if (count($season->getSailorAttendance($sailor)) > 0) {
        $participating[] = $season;
      }
    }
    return $participating;
  }
}
