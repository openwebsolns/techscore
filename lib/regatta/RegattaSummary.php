<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('conf.php');

/**
 * Encapsulates a (flat) regatta object. Note that comments are
 * suppressed due to speed considerations.
 *
 * @author Dayan Paez
 * @created 2009-11-30
 */
class RegattaSummary {

  // Variables
  public $id;
  public $name;
  public $nick;
  public $start_time;
  public $end_date;
  public $type;
  public $finalized;
  public $participant;

  const FIELDS = "regatta.id, regatta.name, regatta.nick, regatta.start_time, regatta.type,
                  regatta.end_date, regatta.finalized, regatta.participant";
  const TABLES = "regatta";

  public function __construct() {
    $this->name = stripslashes($this->name);
    try {
      $this->start_time = new DateTime($this->start_time);
      $this->end_date   = new DateTime($this->end_date);
      if ($this->finalized !== null)
	$this->finalized  = new DateTime($this->finalized);
      $this->season = new Season($this->start_time);
    }
    catch (Exception $e) {
      throw new InvalidArgumentException("Invalid start time.");
    }
  }

  // Comparators
  
  /**
   * Compares two regattas based on start_time
   *
   * @param RegattaSummary $r1 a regatta
   * @param RegattaSummary $r2 a regatta
   */
  public static function cmpStart(RegattaSummary $r1, RegattaSummary $r2) {
    if ($r1->start_time < $r2->start_time)
      return -1;
    if ($r1->start_time > $r2->start_time)
      return 1;
    return 0;
  }

  /**
   * Compares two regattas based on start_time, descending
   *
   * @param RegattaSummary $r1 a regatta
   * @param RegattaSummary $r2 a regatta
   */
  public static function cmpStartDesc(RegattaSummary $r1, RegattaSummary $r2) {
    return -1 * self::cmpStart($r1, $r2);
  }
}
?>