<?php
/*
 * This file is part of TechScore
 *
 * @package scripts
 */

require_once('public/UpdateRequest.php');

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
  public static function queueSchool(School $school, $type, Season $season = null) {
    if (!in_array($type, UpdateSchoolRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateSchoolRequest();
    $obj->school = $school;
    $obj->activity = $type;
    $obj->season = $season;
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
   * Fetches all pending items from the queue in the order in which
   * they are found
   *
   * @return Array:UpdateRequest objects with properties 'regatta' and
   * 'activity', with 'regatta' being an ID
   */
  public static function getPendingRequests() {
    return DB::getAll(DB::$UPDATE_REQUEST, new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingSchools() {
    return DB::getAll(DB::$UPDATE_SCHOOL, new DBCond('completion_time', null));
  }

  /**
   * @see getPendingRequests
   */
  public static function getPendingSeasons() {
    return DB::getAll(DB::$UPDATE_SEASON, new DBCond('completion_time', null));
  }

  /**
   * Logs the given request as completed
   *
   * @param UpdateRequest $req the update request to log
   */
  public static function log(AbstractUpdate $req) {
    $req->completion_time = DB::$NOW;
    DB::set($req, true);
  }
}
?>
