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

    $con = Preferences::getConnection();
    $arg = ($arg === null) ? 'NULL' : sprintf('"%s"', $con->real_escape_string($arg));
    Preferences::query(sprintf('insert into %s (regatta, activity, argument) values (%d, "%s", %s)',
			       UpdateRequest::TABLES, $id, $type, $arg));
  }

  /**
   * Fetches all pending items from the queue in the order in which
   * they are found
   *
   * @return Array:UpdateRequest objects with properties 'regatta' and
   * 'activity', with 'regatta' being an ID
   */
  public static function getPendingRequests() {
    $r = Preferences::query(sprintf('select %s from %s where id not in ' .
				    '(select request from pub_update_log where return_code <= 0)',
				    UpdateRequest::FIELDS, UpdateRequest::TABLES));
    $list = array();
    while ($obj = $r->fetch_object("UpdateRequest"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Logs the response to the given request
   *
   * @param UpdateRequest $req the update request to log
   * @param int $code the code to use (0 = pending, -1 = good, -2 = "assumed", > 0: error
   */
  public static function log(UpdateRequest $req, $code = -1, $mes = "") {
    Preferences::query(sprintf('insert into pub_update_log (request, return_code, return_mess) values ("%s", %d, "%s")',
			       $req->id, $code, addslashes($mes)));
  }

  /**
   * Logs the update attempt for the given season
   *
   * @param Season $season the season
   */
  public static function logSeason(Season $season) {
    Preferences::query(sprintf('insert into pub_update_season (season) values ("%s")', $season));
  }
}
?>
