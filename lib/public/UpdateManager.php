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
   * @param Regatta|RegattaSummary $reg the regatta to queue
   * @param Const $type one of the UpdateRequest activity types
   * @param String $arg the optional argument to include in the request
   * @throws InvalidArgumentException if type not supported
   */
  public static function queueRequest($reg, $type, $arg = null) {
    $id = '';
    if ($reg instanceof Regatta)
      $id = $reg->id();
    elseif ($reg instanceof RegattaSummary)
      $id = $reg->id;
    else
      throw new InvalidArgumentException("Invalid reg object (must be Regatta or RegattaSummary)");
    if (!in_array($type, UpdateRequest::getTypes()))
      throw new InvalidArgumentException("Illegal update request type $type.");

    $obj = new UpdateRequest();
    $obj->regatta = $id;
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
    return DB::getAll(DB::$UPDATE_REQUEST,
		      new DBCondIn('id',
				   DB::prepGetAll(DB::$UPDATE_LOG, new DBCond('return_code', 0, DBCond::LE), array('request')),
				   DBCondIn::NOT_IN));
  }

  /**
   * Logs the response to the given request
   *
   * @param UpdateRequest $req the update request to log
   * @param int $code the code to use (0 = pending, -1 = good, -2 = "assumed", > 0: error
   */
  public static function log(UpdateRequest $req, $code = -1, $mes = "") {
    $log = new UpdateLog();
    $log->request = $req;
    $log->return_code = $code;
    $log->return_mess = $mess;
    DB::set($log);
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
