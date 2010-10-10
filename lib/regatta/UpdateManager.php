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
   * @return Array:stdClass objects with properties 'regatta' and
   * 'activity', with 'regatta' being an ID
   */
  public static function getPendingRequests() {
    $r = self::query('select regatta, activity from pub_update_request ' .
		     '  where id not in (select request from pub_update_log where return code = 0)');
    $list = array();
    while ($obj = $r->fetch_object())
      $list[] = $obj;
    return $list;
  }
}
?>