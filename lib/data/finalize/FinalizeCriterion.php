<?php
namespace data\finalize;

use \Regatta;

/**
 * Determinant of whether a regatta can be finalized or not.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
abstract class FinalizeCriterion {

  /**
   * Does this criterion apply to the given regatta?
   *
   * @param Regatta $regatta in question.
   * @return boolean true if it applies.
   */
  abstract public function canApplyTo(Regatta $regatta);

  /**
   * Get the finalize status according to this criterion.
   *
   * @param Regatta $regatta the non-finalized regatta.
   * @return Array:FinalizeStatus the statuses.
   */
  abstract public function getFinalizeStatuses(Regatta $regatta);
}