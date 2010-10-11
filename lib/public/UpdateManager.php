<?php
/**
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
  private static $con;

  /**
   * Sends query. Returns result object
   *
   */
  private static function query($q) {
    if (self::$con === null)
      self::$con = Preferences::getConnection();
    
    $res = self::$con->query($q);
    if (!empty(self::$con->error))
      throw new BadFunctionCallException(sprintf("MySQL error (%s): %s", $q, self::$con->error));
    return $res;
  }

  /**
   * Fetches all pending items from the queue in the order in which
   * they are found
   *
   * @return Array:UpdateRequest objects with properties 'regatta' and
   * 'activity', with 'regatta' being an ID
   */
  public static function getPendingRequests() {
    $r = self::query(sprintf('select %s from %s where id not in ' .
			     '(select request from pub_update_log where return code <= 0)',
			     UpdateRequest::FIELDS, UpdateRequest::TABLES));
    $list = array();
    while ($obj = $r->fetch_object())
      $list[] = $obj;
    return $list;
  }

  /**
   * Logs the response to the given request
   *
   * @param UpdateRequest $req the update request to log
   * @param int $code the code to use (0 = good, -1 = "assumed", > 0: error
   */
  public static function log(UpdateRequest $req, $code = 0) {
    self::query(sprintf('insert into pub_update_log (request, return_code) values ("%s", %d)',
			$req->id, $code));
  }
}
?>