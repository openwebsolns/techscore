<?php
namespace data\finalize;

use \Regatta;

/**
 * Simple criterion to always return true.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class DummyCriterion extends FinalizeCriterion {
  public function canApplyTo(Regatta $regatta) {
    return true;
  }
  public function getFinalizeStatuses(Regatta $regatta) {
    return array();
  }
}