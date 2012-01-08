<?php
/*
 * This file is part of TechScore
 */

require_once('mysqli/DBM.php');

/**
 * Database serialization manager for all of TechScore.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 * @package dbm
 */
class DB extends DBM {

  // Template objects
  public static $CONFERENCE = null;
  public static $SCHOOL = null;
  public static $BURGEE = null;
  public static $NOW = null;

  public static function setConnectionParams($host, $user, $pass, $db) {
    // Template objects serialization
    self::$CONFERENCE = new Conference();
    self::$SCHOOL = new School();
    self::$BURGEE = new Burgee();
    self::$NOW = new DateTime();

    DBM::setConnectionParams($host, $user, $pass, $db);
  }

  /**
   * Returns the conference with the given ID
   *
   * @param String $id the id of the conference
   * @return Conference the conference object
   */
  public static function getConference($id) {
    return self::get(self::$CONFERENCE, $id);
  }

  /**
   * Returns a list of conference objects
   *
   * @return a list of conferences
   */
  public static function getConferences() {
    return self::getAll(self::$CONFERENCE);
  }

  /**
   * Returns a list of school objects which are in the specified
   * conference.
   *
   * @return a list of schools in the conference
   */
  public static function getSchoolsInConference(Conference $conf) {
    return self::getAll(self::$SCHOOL, new DBCond('conference', $conf));
  }
  
  /**
   * Returns the school with the given ID, or null if none exists
   *
   * @return School|null $school with the given ID
   */
  public static function getSchool($id) {
    return self::get(self::$SCHOOL, $id);
  }
}

/**
 * Encapsulates a conference
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Conference extends DBObject {
  public $name;
  public function __toString() {
    return $this->id;
  }
  protected function db_cache() { return true; }
}

/**
 * Burgees: primary key matches with (and is a foreign key) to school.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Burgee extends DBObject {
  public $filedata;
  protected $last_updated;
  protected $school;
  public $updated_by;

  public function db_type($field) {
    switch ($field) {
    case 'filedata': return DBQuery::A_BLOB;
    case 'last_updated': return DB::$NOW;
    case 'school': return DB::$SCHOOL;
    default:
      return parent::db_type($field);
    }
  }
}

/**
 * Schools
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class School extends DBObject {
  public $nick_name;
  public $name;
  public $city;
  public $state;
  protected $conference;

  public function db_name() { return 'school'; }
  public function db_type($field) {
    if ($field == 'conference')
      return DB::$CONFERENCE;
    return parent::db_type($field);
  }
  protected function db_order() { return array('name'=>true); }
  protected function db_cache() { return true; }

  /**
   * Returns this school's burgee, if any
   *
   * @return Burgee|null
   */
  public function getBurgee() {
    // @TODO!!
    return DB::get(DB::$BURGEE, $this->id);
  }
}
?>