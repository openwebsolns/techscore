<?php
/*
 * This file is part of TechScore
 *
 * @package scripts
 */

require_once('regatta/Regatta.php');

/**
 * Simple serialization of a public display update request
 *
 * @author Dayan Paez
 * @version 2010-10-11
 */
class UpdateRequest extends DBObject {
  protected $regatta;
  public $activity;
  public $argument;
  /**
   * @var DateTime the time of the request. Leave as null for current timestamp
   */
  protected $request_time;

  public function db_name() { return 'pub_update_request'; }
  public function db_type($field) {
    switch ($field) {
    case 'request_time': return DB::$NOW;
    case 'regatta': return DB::$REGATTA;
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('request_time'=>true); }

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
 * Log of completed update requests
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class UpdateLog extends DBObject {
  protected $request;
  protected $attempt_time;
  public $return_code;
  public $return_mess;

  public function db_name() { return 'pub_update_log'; }
  public function db_type($field) {
    switch ($field) {
    case 'request': return DB::$UPDATE_REQUEST;
    case 'attempt_time': returN DB::$NOW;
    default:
      parent::db_type($field);
    }
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
DB::$UPDATE_LOG = new UpdateLog();
DB::$UPDATE_LOG_SEASON = new UpdateLogSeason();
?>