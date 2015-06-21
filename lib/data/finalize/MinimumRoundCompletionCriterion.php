<?php
namespace data\finalize;

use \Regatta;
use \Round;

/**
 * Enforces that a minimum percent of the round has been sailed.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class MinimumRoundCompletionCriterion extends FinalizeCriterion {

  private static $THRESHOLD = 80;

  public function canApplyTo(Regatta $regatta) {
    return ($regatta->scoring == Regatta::SCORING_TEAM);
  }

  public function getFinalizeStatuses(Regatta $regatta) {
    $list = array();
    foreach ($regatta->getRounds() as $round) {
      if (!$this->isRoundScoredAboveThreshold($regatta, $round)) {
        $list[] = new FinalizeStatus(
          FinalizeStatus::ERROR,
          sprintf(
            "Fewer than %d%% of races scored for \"%s\". You may need to delete the round.",
            self::$THRESHOLD,
            $round
          )
        );
      }
    }
    return $list;
  }

  private function isRoundScoredAboveThreshold(Regatta $regatta, Round $round) {
    $numRaces = count($regatta->getRacesInRound($round));
    if ($numRaces == 0) {
      return false;
    }

    $numScored = count($regatta->getScoredRacesInRound($round));
    $percent = ($numScored / $numRaces) * 100;
    return ($percent >= self::$THRESHOLD);
  }
}