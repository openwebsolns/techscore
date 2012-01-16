<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Encapsulates a (flat) regatta object. Note that comments are
 * suppressed due to speed considerations.
 *
 * @author Dayan Paez
 * @version 2009-11-30
 */
class RegattaSummary extends DBObject {

  /**
   * Standard scoring
   */
  const SCORING_STANDARD = "standard";

  /**
   * Combined scoring
   */   
  const SCORING_COMBINED = "combined";

  /**
   * Women's regatta
   */
  const PARTICIPANT_WOMEN = "women";
  
  /**
   * Coed regatta (default)
   */
  const PARTICIPANT_COED = "coed";
  
  // Variables
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  public $type;
  protected $finalized;
  public $participant;

  public function db_name() { return 'regatta'; }
  protected function db_order() { return array('start_time'=>false); }
  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
    case 'finalized':
      return DB::$NOW;
    default:
      return parent::db_type($field);
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
DB::$REGATTA_SUMMARY = new RegattaSummary();
?>