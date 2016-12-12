<?php
namespace users\membership\eligibility;

use \Season;

/**
 * The result of eligibility calculation by enforcer.
 */
class EligibilityResult {
  const STATUS_OK = 'OK';
  const STATUS_INELIGIBLE = 'INELIGIBLE';

  private $season;
  private $status;
  private $reason;

  public function __construct(Season $season, $status, $reason = null) {
    $this->season = $season;
    $this->status = $status;
    $this->reason = $reason;
  }

  public function getSeason() {
    return $this->season;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getReason() {
    return $this->reason;
  }
}