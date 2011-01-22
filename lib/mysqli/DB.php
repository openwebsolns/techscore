<?php
/**
 * A different way of serializing and deserializing objects using the
 * DBM class created by Dayan Paez.
 *
 * @author Dayan Paez
 * @version 2011-01-22
 * @package mysql
 */

/**
 * Provides some more functionality
 *
 */
class DBME extends DBM {
  /**
   * Empty objects to serve as prototypes
   */
  public static $REGATTA = new Dt_Regatta();
  public static $SCHOOL = new Dt_School();
  public static $SAILOR = new Dt_Sailor();
  public static $SCORE = new Dt_Score();
  public static $VENUE = new Dt_Venue();
  public static $TEAM = new Dt_Team();
  public static $NOW = new DateTime();
  public static $RP = new Dt_Rp();
}

class Dt_Regatta extends DBObject {
  public $name;
  public $nick;
  protected $start_time;
  protected $end_date;
  protected $venue;
  public $type;
  protected $finalized;
  public $scoring;
  public $num_divisions;
  public $num_races;
  public $hosts;
  public $confs;
  public $boats;
  public $singlehanded;
  public $season;

  public function db_type($field) {
    switch ($field) {
    case 'start_time':
    case 'end_date':
      return DBME::$NOW;

    case 'venue':
      return DBME::$VENUE;

    default:
      return parent::db_type($field);
    }
  }
  public function db_cache() { return true; }
}

class Dt_Venue extends DBObject {
  public $name;
  public $address;
  public $city;
  public $state;
  public $zipcode;

  public function db_name() { return 'venue'; }
}

class Dt_Team extends DBObject {
  protected $regatta;
  protected $school;
  public $name;
  public $rank;
  public $rank_explanation;

  public function db_type($field) {
    switch ($field) {
    case 'regatta':
      return DBME::$REGATTA;
    case 'school':
      return DBME::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
}

class Dt_School extends DBObject {
  public $name;
  public $nick_name;
  public $conference;
  public $city;
  public $state;

  public function db_name() { return 'school'; }
  public function db_cache() { return true; }
}

class Dt_Score extends DBObject {
  protected $dt_team;
  public $race_num;
  public $division;
  public $place;
  public $score;
  public $explanation;

  public function db_type($field) {
    if ($field == 'dt_team')
      return DBME::$TEAM:
    return parent::db_type($field);
  }
}

class Dt_Rp extends DBObject {
  protected $dt_team;
  public $race_num;
  public $division;
  protected $sailor;
  public $boat_role;

  public function db_type($field) {
    if ($field == 'dt_team')
      return DBME::$TEAM;
    if ($field == 'sailor')
      return DBME::$SAILOR;
    return parent::db_type($field);
  }
}

class Dt_Sailor extends DBObject {
  public $icsa_id;
  protected $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;

  public function db_name() { return 'sailor'; }
  public function db_type($field) {
    if ($field == 'school')
      return DBME::$SCHOOL;
    return parent::db_type($field);
  }
}

?>