<?php
/*
 * This file is part of TechScore
 *
 * @package scripts
 */


abstract class AbstractUpdate extends DBObject {
  public $activity;
  protected $request_time;
  protected $completion_time;

  public function db_type($field) {
    switch ($field) {
    case 'request_time':
    case 'completion_time':
      return DB::T(DB::NOW);
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
      return DB::T(DB::FULL_REGATTA);
    }
    return parent::db_type($field);
  }

  const ACTIVITY_RP = "rp";
  const ACTIVITY_SCORE = "score";
  const ACTIVITY_DETAILS = "details";
  const ACTIVITY_SUMMARY = "summary";
  const ACTIVITY_ROTATION = "rotation";
  const ACTIVITY_FINALIZED = 'finalized';
  const ACTIVITY_URL = 'url';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_RANK = 'rank';
  const ACTIVITY_TEAM = 'team';
  const ACTIVITY_DOCUMENT = 'document';

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
                 self::ACTIVITY_FINALIZED => self::ACTIVITY_FINALIZED,
                 self::ACTIVITY_URL => self::ACTIVITY_URL,
                 self::ACTIVITY_SEASON => self::ACTIVITY_SEASON,
                 self::ACTIVITY_RANK => self::ACTIVITY_RANK,
                 self::ACTIVITY_TEAM => self::ACTIVITY_TEAM,
                 self::ACTIVITY_DOCUMENT => self::ACTIVITY_DOCUMENT,
                 self::ACTIVITY_ROTATION => self::ACTIVITY_ROTATION);
  }

  /**
   * Returns a unique identifier for this request, constituting of the
   * regatta ID, the activity, and the argument
   */
  public function hash() {
    $id = ($this->regatta instanceof FullRegatta) ? $this->regatta->id : $this->regatta;
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
  public $argument;

  public function db_name() { return 'pub_update_school'; }
  public function db_type($field) {
    if ($field == 'school')
      return DB::T(DB::SCHOOL);
    if ($field == 'season')
      return DB::T(DB::SEASON);
    return parent::db_type($field);
  }

  const ACTIVITY_BURGEE = 'burgee';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_URL = 'url';

  public static function getTypes() {
    return array(self::ACTIVITY_BURGEE => self::ACTIVITY_BURGEE,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
                 self::ACTIVITY_URL => self::ACTIVITY_URL,
                 self::ACTIVITY_SEASON => self::ACTIVITY_SEASON);
  }

  public function hash() {
    $id = ($this->school instanceof School) ? $this->school->id : $this->school;
    $season = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s-%s', $id, $this->activity, $season);
  }
}

/**
 * Request to updatae a conference page
 *
 * @author Dayan Paez
 * @version 2014-06-20
 */
class UpdateConferenceRequest extends AbstractUpdate {
  protected $conference;
  protected $season;
  public $argument;

  public function db_name() { return 'pub_update_conference'; }
  public function db_type($field) {
    if ($field == 'conference')
      return DB::T(DB::CONFERENCE);
    if ($field == 'season')
      return DB::T(DB::SEASON);
    return parent::db_type($field);
  }

  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_URL = 'url';
  const ACTIVITY_DISPLAY = 'display'; // is the setting enabled?

  public static function getTypes() {
    return array(
      self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
      self::ACTIVITY_SEASON => self::ACTIVITY_SEASON,
      self::ACTIVITY_URL => self::ACTIVITY_URL,
      self::ACTIVITY_DISPLAY => self::ACTIVITY_DISPLAY,
    );
  }

  public function hash() {
    $id = ($this->conference instanceof Conference) ? $this->conference->id : $this->conference;
    $season = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s-%s', $id, $this->activity, $season);
  }
}

/**
 * Request to update season
 *
 * @author Dayan Paez
 * @version 2012-01-15
 */
class UpdateSeasonRequest extends AbstractUpdate {
  protected $season;

  const ACTIVITY_REGATTA = 'regatta';
  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_FRONT = 'front';
  const ACTIVITY_404 = '404';
  const ACTIVITY_SCHOOL_404 = 'school404';
  const ACTIVITY_URL = 'url';

  public static function getTypes() {
    return array(self::ACTIVITY_REGATTA => self::ACTIVITY_REGATTA,
                 self::ACTIVITY_FRONT => self::ACTIVITY_FRONT,
                 self::ACTIVITY_404 => self::ACTIVITY_404,
                 self::ACTIVITY_SCHOOL_404 => self::ACTIVITY_SCHOOL_404,
                 self::ACTIVITY_URL => self::ACTIVITY_URL,
                 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS);
  }

  public function db_name() { return 'pub_update_season'; }
  public function db_type($field) {
    switch ($field) {
    case 'season': return DB::T(DB::SEASON);
    default:
      return parent::db_type($field);
    }
  }

  public function hash() {
    $id = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s', $id, $this->activity);
  }
}

/**
 * Request to update a public file.
 *
 * @author Dayan Paez
 * @version 2013-10-04
 */
class UpdateFileRequest extends AbstractUpdate {
  public $file;
  public static function getTypes() { return array(); }
  public function db_name() { return 'pub_update_file'; }
  public function hash() { return $this->file; }
}
?>