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
 * @see scripts/UpdateDaemon
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
  public static function queueRequest(Regatta $reg, $type, $arg = null) {
    if (!in_array($type, UpdateRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateRequest();
    $obj->regatta = $reg;
    $obj->activity = $type;
    $obj->argument = $arg;
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
   * Logs the given request as completed
   *
   * @param UpdateRequest $req the update request to log
   */
  public static function log(UpdateRequest $req) {
    $req->completion_time = DB::$NOW;
    DB::set($req, true);
  }

  /**
   * Logs the update attempt for the given season
   *
   * @param Season $season the season
   */
  public static function logSeason(Season $season) {
    $log = new UpdateLogSeason();
    $log->season = (string)$season;
    DB::set($log);
  }
}
?>
