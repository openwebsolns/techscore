<?php
/*
 * This file is part of TechScore
 *
 * @package scripts
 */

/**
 * Database connectivity for the purpose of updating the public site.
 *
 * @author Dayan Paez
 * @version 2010-10-08
 * @see scripts/Daemon
 */
class UpdateManager {

  /**
   * Queues the given request type for the given regatta.
   *
   * @param Regatta $reg the regatta to queue
   * @param Const $type one of the UpdateRequest activity types
   * @param String $arg the optional argument to include in the request
   * @throws InvalidArgumentException if type not supported
   */
  public static function queueRequest(FullRegatta $reg, $type, $arg = null) {
    if (!in_array($type, UpdateRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateRequest();
    $obj->regatta = $reg;
    $obj->activity = $type;
    $obj->argument = $arg;
    DB::set($obj);
  }

  /**
   * @see queueRequest
   */
  public static function queueSchool(School $school, $type, Season $season = null, $arg = null) {
    if (!in_array($type, UpdateSchoolRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateSchoolRequest();
    $obj->school = $school;
    $obj->activity = $type;
    $obj->season = $season;
    $obj->argument = $arg;
    DB::set($obj);
  }

  /**
   * @see queueRequest
   */
  public static function queueSailor(Sailor $sailor, $type, Season $season = null, $arg = null) {
    if (!in_array($type, UpdateSailorRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateSailorRequest();
    $obj->sailor = $sailor;
    $obj->activity = $type;
    $obj->season = $season;
    $obj->argument = $arg;
    DB::set($obj);
  }

  /**
   * Will not queue if setting is off
   *
   * @see queueRequest
   */
  public static function queueConference(Conference $conf, $type, Season $season = null, $arg = null) {
    if (!in_array($type, UpdateConferenceRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");
    if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) === null && $type != UpdateConferenceRequest::ACTIVITY_DISPLAY)
      return;

    $obj = new UpdateConferenceRequest();
    $obj->conference = $conf;
    $obj->activity = $type;
    $obj->season = $season;
    $obj->argument = $arg;
    DB::set($obj);
  }

  /**
   * @see queueRequest
   */
  public static function queueSeason(Season $season, $type) {
    if (!in_array($type, UpdateSeasonRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateSeasonRequest();
    $obj->season = $season;
    $obj->activity = $type;
    DB::set($obj);
  }

  /**
   * @see queueRequest
   */
  public static function queueFile(Pub_File_Summary $file) {
    $obj = new UpdateFileRequest();
    $obj->file = $file->id;
    DB::set($obj);
  }

  /**
   * Update the /init.js file
   *
   * @see queueRequest
   * @see public/InitJs
   */
  public static function queueInitJsFile() {
    $obj = new UpdateFileRequest();
    $obj->file = Pub_File::INIT_FILE;
    DB::set($obj);
  }

  private static function getLastCompleted(AbstractUpdate $obj) {
    $obj->db_set_order(array('completion_time' => false));
    $all = DB::getAll($obj, new DBCond('completion_time', null, DBCond::NE), 1);
    $res = null;
    if (count($all) > 0) {
      $res = $all[0];
    }
    $obj->db_set_order();
    return $res;
  }

  /**
   * Get the last completed entry.
   *
   * @return UpdateRequest, or null.
   */
  public static function getLastRegattaCompleted() {
    return self::getLastCompleted(DB::T(DB::UPDATE_REQUEST));
  }

  /**
   * Get the last completed entry.
   *
   * @return UpdateSeasonRequest, or null.
   */
  public static function getLastSeasonCompleted() {
    return self::getLastCompleted(DB::T(DB::UPDATE_SEASON));
  }

  /**
   * Get the last completed entry.
   *
   * @return UpdateSchoolRequest, or null.
   */
  public static function getLastSchoolCompleted() {
    return self::getLastCompleted(DB::T(DB::UPDATE_SCHOOL));
  }

  /**
   * Get the last completed entry.
   *
   * @return UpdateConferenceRequest, or null.
   */
  public static function getLastConferenceCompleted() {
    return self::getLastCompleted(DB::T(DB::UPDATE_CONFERENCE));
  }

  /**
   * Get the last completed entry.
   *
   * @return UpdateSailorRequest, or null.
   */
  public static function getLastSailorCompleted() {
    return self::getLastCompleted(DB::T(DB::UPDATE_SAILOR));
  }

  /**
   * Get the last completed entry.
   *
   * @return UpdateFileRequest, or null.
   */
  public static function getLastFileCompleted() {
    return self::getLastCompleted(DB::T(DB::UPDATE_FILE));
  }

  /**
   * Fetches all pending items from the queue in the order in which
   * they are found
   *
   * @return Array:UpdateRequest objects with properties 'regatta' and
   * 'activity', with 'regatta' being an ID
   */
  public static function getPendingRequests() {
    return DB::getAll(DB::T(DB::UPDATE_REQUEST), new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingSchools() {
    return DB::getAll(DB::T(DB::UPDATE_SCHOOL), new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingConferences() {
    return DB::getAll(DB::T(DB::UPDATE_CONFERENCE), new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingSeasons() {
    return DB::getAll(DB::T(DB::UPDATE_SEASON), new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingFiles() {
    return DB::getAll(DB::T(DB::UPDATE_FILE), new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingSailors() {
    return DB::getAll(DB::T(DB::UPDATE_SAILOR), new DBCond('completion_time', null));
  }

  /**
   * Logs the given request as completed
   *
   * @param UpdateRequest $req the update request to log
   */
  public static function log(AbstractUpdate $req) {
    $req->completion_time = new DateTime();
    DB::set($req, true);
  }
}
?>
