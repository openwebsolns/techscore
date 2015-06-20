<?php
namespace data\finalize;

use \Regatta;

/**
 * Warn about RP completeness.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class CompleteRpCriterion extends FinalizeCriterion {

  public function canApplyTo(Regatta $regatta) {
    return true;
  }

  public function getFinalizeStatuses(Regatta $regatta) {
    if ($regatta->isRpComplete()) {
      return array(new FinalizeStatus(FinalizeStatus::VALID, "All RP info is present."));
    }
    return array(
      new FinalizeStatus(
        FinalizeStatus::WARN,
        "There is missing RP information. Note that this may be edited after finalization."
      )
    );
  }
}