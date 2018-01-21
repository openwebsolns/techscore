<?php
namespace eligibility;

/**
 * Reason for being considered eligible or not to sail.
 */
class EligibilityReason {
  private $isEligible;
  private $reason;

  public function __construct($isEligible, $reason = '') {
    $this->isEligible = ($isEligible !== false);
    $this->reason = $reason;
  }

  public function isEligible() {
    return $this->isEligible;
  }

  public function getReason() {
    return $this->reason;
  }
}
