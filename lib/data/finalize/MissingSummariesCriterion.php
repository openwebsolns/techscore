<?php
namespace data\finalize;

use \DateInterval;

use \Regatta;

/**
 * Are there any daily summaries missing?
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class MissingSummariesCriterion extends FinalizeCriterion {

  public function canApplyTo(Regatta $regatta) {
    return true;
  }

  public function getFinalizeStatuses(Regatta $regatta) {
    $list = $this->getMissingSummaries($regatta);
    if (count($list) == 0) {
      return array(new FinalizeStatus(FinalizeStatus::VALID, "All daily summaries completed."));
    }

    $statuses = array();
    foreach ($list as $day) {
      $statuses[] = new FinalizeStatus(
        FinalizeStatus::ERROR,
        sprintf("Missing daily summary for %s.", $day->format('l, F j'))
      );
    }
    return $statuses;
  }

  /**
   * Returns list of days that are missing summaries.
   *
   * @param Regatta $regatta whose summaries to evaluate.
   * @return Array:Date the missing days.
   */
  private function getMissingSummaries(Regatta $regatta) {
    $stime = clone $regatta->start_time;
    $missing = array();
    for ($i = 0; $i < $regatta->getDuration(); $i++) {
      $comms = $regatta->getSummary($stime);
      if (strlen($comms) == 0)
        $missing[] = clone $stime;
      $stime->add(new DateInterval('P1DT0H'));
    }
    return $missing;
  }
}