<?php
/*
 * This file is part of TechScore
 *
 * @package scripts
 */

require_once('regatta/Regatta.php');

abstract class AbstractUpdate extends DBObject {
  public $activity;
  protected $request_time;
  protected $completion_time;

  public function db_type($field) {
    switch ($field) {
    case 'request_time':
    case 'completion_time':
      return DB::$NOW;
    }
    return parent::db_type($field);
  }
  protected function db_order() { return array('request_time'=>true); }

  /**
   * Unique identifier for the request, without taking ID into account
   *
   */
  abstract public function hash();
}

/**
 * Simple serialization of a public display update request
 *
 * @author Dayan Paez
 * @version 2010-10-11
 */
class UpdateRequest extends AbstractUpdate {
  protected $regatta;
  public $argument;

  public function db_name() { return 'pub_update_request'; }
  public function db_type($field) {
    if ($field == 'regatta') {
      require_once('regatta/Regatta.php');
      return DB::$REGATTA;
    }
    return parent::db_type($field);
  }

  const ACTIVITY_RP = "rp";
  const ACTIVITY_SCORE = "score";
  const ACTIVITY_DETAILS = "details";
  const ACTIVITY_SUMMARY = "summary";
  const ACTIVITY_ROTATION = "rotation";

  /**
   * Returns an associative set of the permissible types
   *
   * @return Array type constants as const => const
   */
  public static function getTypes() {
    return array(self::ACTIVITY_RP => self::ACTIVITY_RP,
                 self::ACTIVITY_SCORE => self::ACTIVITY_SCORE,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
                 self::ACTIVITY_SUMMARY => self::ACTIVITY_SUMMARY,
                 self::ACTIVITY_ROTATION => self::ACTIVITY_ROTATION);
  }

  /**
   * Returns a unique identifier for this request, constituting of the
   * regatta ID, the activity, and the argument
   */
  public function hash() {
    $id = ($this->regatta instanceof Regatta) ? $this->regatta->id : $this->regatta;
    return sprintf('%s-%s-%s', $id, $this->activity, $this->argument);
  }
}

/**
 * School updates
 *
 * @author Dayan Paez
 * @version 2012-10-05
 */
class UpdateSchoolRequest extends AbstractUpdate {
  protected $school;
  protected $season;

  public function db_name() { return 'pub_update_school'; }
  public function db_type($field) {
    if ($field == 'school')
      return DB::$SCHOOL;
    if ($field == 'season')
      return DB::$SEASON;
    return parent::db_type($field);
  }

  const ACTIVITY_BURGEE = 'burgee';
  const ACTIVITY_SEASON = 'season';

  public static function getTypes() {
    return array(self::ACTIVITY_BURGEE => self::ACTIVITY_BURGEE,
		 self::ACTIVITY_SEASON => self::ACTIVITY_SEASON);
  }

  public function hash() {
    $id = ($this->school instanceof School) ? $this->school->id : $this->school;
    return sprintf('%s-%s', $id, $this->activity);
  }
}

/**
 * Request to update season
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class UpdateLogSeason extends DBObject {
  public $season;
  protected $update_time;

  public function db_name() { return 'pub_update_season'; }
  public function db_type($field) {
    switch ($field) {
    case 'update_time': return DB::$NOW;
    default:
      return parent::db_type($field);
    }
  }
}
DB::$UPDATE_REQUEST = new UpdateRequest();
DB::$UPDATE_SCHOOL = new UpdateSchoolRequest();
DB::$UPDATE_LOG_SEASON = new UpdateLogSeason();
?>